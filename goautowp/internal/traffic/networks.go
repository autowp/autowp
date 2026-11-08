package traffic

import (
	"context"
	"errors"
	"fmt"
	"net"
	"net/netip"
	"time"

	"github.com/autowp/goautowp/internal/ban"
	"github.com/autowp/goautowp/internal/logging"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	networkDailyLimit     = 100000
	networkHourlyLimit    = 20000
	networkTenMinsLimit   = 5000
	networkOneMinLimit    = 1000
	networkCandidateLimit = 1000
	bitsPerByte           = 8
	networkTenMinuteBan   = 10 * time.Minute
	networkMinuteBan      = 2 * time.Minute
)

// NetworkAutobanProfiles use current calendar windows, like the per-IP profiles.
// Each ban outlasts the buckets that can trigger it. The minute profile also
// checks the previous minute to allow for delayed counter flushes.
var NetworkAutobanProfiles = []AutobanProfile{
	{Limit: networkDailyLimit, Reason: "network daily limit", Time: hoursInDay * time.Hour},
	{
		Limit: networkHourlyLimit, Reason: "network hourly limit", Time: time.Hour,
		Group: []interface{}{schema.IPMonitoringTableHourCol},
	},
	{
		Limit: networkTenMinsLimit, Reason: "network ten min limit", Time: networkTenMinuteBan,
		Group: []interface{}{schema.IPMonitoringTableHourCol, schema.IPMonitoringTableTenminuteCol},
	},
	{
		Limit: networkOneMinLimit, Reason: "network min limit", Time: networkMinuteBan,
		Group: []interface{}{
			schema.IPMonitoringTableHourCol, schema.IPMonitoringTableTenminuteCol,
			schema.IPMonitoringTableMinuteCol,
		},
	},
}

type NetworkTraffic struct {
	Network   string         `db:"network"`
	Count     int64          `db:"count"`
	Addresses int64          `db:"addresses"`
	Bans      []ban.RangeBan `db:"-"`
}

func networkExpression() exp.LiteralExpression {
	return goqu.L("NETWORK(SET_MASKLEN(?, CASE WHEN FAMILY(?) = 4 THEN 24 ELSE 64 END))",
		schema.IPMonitoringTableIPCol, schema.IPMonitoringTableIPCol)
}

// networkCounts aggregates existing per-IP minute counters without storing duplicate metrics.
func (s *Monitoring) networkCounts() *goqu.SelectDataset {
	return s.db.From(schema.IPMonitoringTable).Where(
		schema.IPMonitoringTableDayDateCol.Eq(goqu.L("CURRENT_DATE")),
		goqu.L("NOT EXISTS ?", s.db.From(schema.IPWhitelistTable).Select(goqu.V(1)).
			Where(schema.IPWhitelistTableIPCol.Eq(schema.IPMonitoringTableIPCol))),
	)
}

func (s *Monitoring) ListOfTopNetworks(ctx context.Context, limit uint) ([]NetworkTraffic, error) {
	rows := make([]NetworkTraffic, 0)
	err := s.networkCounts().Select(goqu.Cast(networkExpression(), "text").As("network"),
		goqu.SUM(schema.IPMonitoringTableCountCol).As("count"),
		goqu.COUNT(goqu.DISTINCT(schema.IPMonitoringTableIPCol)).As("addresses")).
		GroupBy(networkExpression()).Order(goqu.I("count").Desc(), goqu.I("network").Asc()).Limit(limit).
		ScanStructsContext(ctx, &rows)

	return rows, err
}

// ListNetworkIPs uses a range over the leading inet column of the existing primary key.
func (s *Monitoring) ListNetworkIPs(ctx context.Context, prefix netip.Prefix, limit uint) ([]ListOfTopItem, error) {
	prefix = prefix.Masked()
	first := prefix.Addr()
	lastBytes := first.As16()

	bits := prefix.Bits()
	if first.Is4() {
		bits += 96
	}

	for bit := bits; bit < len(lastBytes)*bitsPerByte; bit++ {
		lastBytes[bit/bitsPerByte] |= 1 << (bitsPerByte - 1 - bit%bitsPerByte)
	}

	last := netip.AddrFrom16(lastBytes)
	if first.Is4() {
		last = last.Unmap()
	}

	var rows []struct {
		IP    schema.NullInet `db:"ip"`
		Count int             `db:"count"`
	}

	err := s.networkCounts().Select(schema.IPMonitoringTableIPCol,
		goqu.SUM(schema.IPMonitoringTableCountCol).As("count")).Where(
		schema.IPMonitoringTableIPCol.Gte(first.String()), schema.IPMonitoringTableIPCol.Lte(last.String()),
	).GroupBy(schema.IPMonitoringTableIPCol).Order(goqu.I("count").Desc(), schema.IPMonitoringTableIPCol.Asc()).
		Limit(limit).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	result := make([]ListOfTopItem, 0, len(rows))
	for _, row := range rows {
		result = append(result, ListOfTopItem{IP: net.IP(row.IP.Addr().AsSlice()), Count: row.Count})
	}

	return result, nil
}

func (s *Monitoring) ListNetworksByBanProfile(ctx context.Context, profile AutobanProfile) ([]netip.Prefix, error) {
	dataset := s.networkCounts()
	// Select only the current bucket: an old burst must not renew a network ban all day.
	current := []exp.Expression{
		schema.IPMonitoringTableHourCol.Eq(goqu.L("EXTRACT(HOUR FROM CURRENT_TIMESTAMP)")),
		schema.IPMonitoringTableTenminuteCol.Eq(goqu.L("FLOOR(EXTRACT(MINUTE FROM CURRENT_TIMESTAMP)/10)")),
		schema.IPMonitoringTableMinuteCol.Eq(goqu.L("EXTRACT(MINUTE FROM CURRENT_TIMESTAMP)")),
	}
	if len(profile.Group) == len(current) {
		// The scheduler and recorder run independently. Include the just-completed
		// minute so a burst is still detected after the counter batch is flushed.
		dataset = dataset.Where(goqu.L(
			"(? + ? * INTERVAL '1 hour' + ? * INTERVAL '1 minute') BETWEEN "+
				"DATE_TRUNC('minute', CURRENT_TIMESTAMP) - INTERVAL '1 minute' "+
				"AND DATE_TRUNC('minute', CURRENT_TIMESTAMP)",
			schema.IPMonitoringTableDayDateCol, schema.IPMonitoringTableHourCol, schema.IPMonitoringTableMinuteCol,
		))
	} else {
		for idx := range profile.Group {
			dataset = dataset.Where(current[idx])
		}
	}

	var addresses []string

	err := dataset.Select(goqu.Cast(networkExpression(), "text").As("network")).Distinct().
		GroupBy(append([]interface{}{networkExpression()}, profile.Group...)...).
		Having(goqu.SUM(schema.IPMonitoringTableCountCol).Gt(profile.Limit)).
		Order(goqu.I("network").Asc()).
		Limit(networkCandidateLimit).
		ScanValsContext(ctx, &addresses)
	if err != nil {
		return nil, err
	}

	result := make([]netip.Prefix, 0, len(addresses))
	for _, address := range addresses {
		prefix, err := netip.ParsePrefix(address)
		if err != nil {
			return nil, fmt.Errorf("parse monitoring network: %w", err)
		}

		result = append(result, prefix)
	}

	return result, nil
}

func (s *Repository) AutoBanNetworksByProfile(ctx context.Context, profile AutobanProfile) error {
	networks, err := s.Monitoring.ListNetworksByBanProfile(ctx, profile)
	if err != nil {
		return fmt.Errorf("find networks exceeding quota: %w", err)
	}

	for _, prefix := range networks {
		active, err := s.Ban.NetworkBan(ctx, prefix)
		if err == nil {
			// Keep an active ban's deadline stable, but allow a stronger quota to
			// replace a shorter automatic ban. Unknown/manual reasons are preserved.
			if !canUpgradeNetworkBan(active, prefix, profile.Time) {
				continue
			}
		}

		if err != nil && !errors.Is(err, ban.ErrBanItemNotFound) {
			return err
		}

		reason := profile.Reason + " " + prefix.String()
		if err := s.Ban.AddNetwork(ctx, prefix, profile.Time, reason); err != nil {
			return err
		}

		logging.Warnf(
			"autoban: network=%s reason=%q threshold=%d duration=%s",
			prefix,
			reason,
			profile.Limit,
			profile.Time,
		)
	}

	return nil
}

func canUpgradeNetworkBan(active *ban.Item, prefix netip.Prefix, duration time.Duration) bool {
	if active.ByUserID != 0 {
		return false
	}

	for _, profile := range NetworkAutobanProfiles {
		if active.Reason == profile.Reason+" "+prefix.String() {
			return profile.Time < duration
		}
	}

	return false
}
