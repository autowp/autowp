package traffic

import (
	"context"
	"fmt"
	"net/netip"
	"slices"

	"github.com/autowp/goautowp/internal/ban"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
)

// Source identifies an operator independently of any whitelist or quota policy.
// Networks are a reviewed snapshot; DNS identities contain only individually
// verified addresses, never all neighbors of an address with a matching PTR.
type Source struct {
	ID          string   `json:"id"`
	Name        string   `json:"name"`
	Networks    []string `json:"networks"`
	DNSSuffixes []string `json:"dns_suffixes"`
	References  []string `json:"references"`
}

type SourceTraffic struct {
	Source

	Count                int64          `db:"count"`
	Addresses            int64          `db:"addresses"`
	WhitelistedAddresses int64          `db:"whitelisted_addresses"`
	Bans                 []ban.RangeBan `db:"-"`
}

// Aggregate minutes once per IP before classifying, rather than testing every
// prefix against every minute counter. No classification runs on request writes.
func (s *Monitoring) sourceCounts(sources []Source) *goqu.SelectDataset {
	counts := s.db.From(schema.IPMonitoringTable).
		Select(schema.IPMonitoringTableIPCol, goqu.SUM(schema.IPMonitoringTableCountCol).As("count")).
		Where(schema.IPMonitoringTableDayDateCol.Eq(goqu.L("CURRENT_DATE"))).
		GroupBy(schema.IPMonitoringTableIPCol)
	ip := goqu.T("counts").Col(schema.IPMonitoringTableIPColName)
	identity := goqu.Case()
	// An individually verified DNS identity takes precedence over a cloud CIDR.
	for _, source := range sources {
		identity = identity.When(schema.IPSourceTableSourceIDCol.Eq(source.ID), source.ID)
	}

	type networkRule struct {
		sourceID string
		prefix   netip.Prefix
	}

	rules := make([]networkRule, 0)

	for _, source := range sources {
		for _, network := range source.Networks {
			prefix, err := netip.ParsePrefix(network)
			if err == nil {
				rules = append(rules, networkRule{sourceID: source.ID, prefix: prefix})
			}
		}
	}

	slices.SortStableFunc(rules, func(a, b networkRule) int { return b.prefix.Bits() - a.prefix.Bits() })

	for _, rule := range rules {
		identity = identity.When(goqu.L("? <<= ?::inet", ip, rule.prefix.String()), rule.sourceID)
	}

	return s.db.From("counts").With("counts", counts).
		LeftJoin(schema.IPSourceTable, goqu.On(schema.IPSourceTableIPCol.Eq(ip))).
		LeftJoin(schema.IPWhitelistTable, goqu.On(schema.IPWhitelistTableIPCol.Eq(ip))).
		Select(ip.As("ip"), goqu.T("counts").Col("count"), identity.Else("").As("source_id"),
			schema.IPWhitelistTableIPCol.IsNotNull().As("whitelisted"))
}

func (s *Monitoring) ListSources(ctx context.Context) ([]SourceTraffic, error) {
	sources, err := s.Sources(ctx)
	if err != nil {
		return nil, err
	}

	if len(sources) == 0 {
		return []SourceTraffic{}, nil
	}

	var totals []struct {
		ID                   string `db:"source_id"`
		Count                int64  `db:"count"`
		Addresses            int64  `db:"addresses"`
		WhitelistedAddresses int64  `db:"whitelisted_addresses"`
	}

	err = s.db.From(s.sourceCounts(sources).As("sources")).
		Select(goqu.I("source_id"), goqu.SUM(goqu.I("count")).As("count"),
			goqu.COUNT("*").As("addresses"),
			goqu.SUM(goqu.Cast(goqu.I("whitelisted"), "integer")).As("whitelisted_addresses")).
		Where(goqu.I("source_id").Neq("")).GroupBy(goqu.I("source_id")).ScanStructsContext(ctx, &totals)
	if err != nil {
		return nil, fmt.Errorf("aggregate known traffic sources: %w", err)
	}

	result := make([]SourceTraffic, 0, len(sources))
	for _, source := range sources {
		item := SourceTraffic{Source: source}

		for _, total := range totals {
			if total.ID == source.ID {
				item.Count, item.Addresses = total.Count, total.Addresses
				item.WhitelistedAddresses = total.WhitelistedAddresses
			}
		}

		result = append(result, item)
	}

	slices.SortStableFunc(result, func(a, b SourceTraffic) int {
		switch {
		case a.Count > b.Count:
			return -1
		case a.Count < b.Count:
			return 1
		default:
			return 0
		}
	})

	return result, nil
}

func (s *Monitoring) ListSourceIPs(ctx context.Context, sourceID string, limit uint) ([]ListOfTopItem, error) {
	sources, err := s.Sources(ctx)
	if err != nil {
		return nil, err
	}

	found := false

	for _, source := range sources {
		if source.ID == sourceID {
			found = true

			break
		}
	}

	if !found {
		return nil, ErrSourceNotFound
	}

	var rows []struct {
		IP    schema.NullInet `db:"ip"`
		Count int             `db:"count"`
	}

	err = s.db.From(s.sourceCounts(sources).As("sources")).Select(goqu.I("ip"), goqu.I("count")).
		Where(goqu.I("source_id").Eq(sourceID)).Order(goqu.I("count").Desc(), goqu.I("ip").Asc()).
		Limit(limit).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, fmt.Errorf("read traffic source %q addresses: %w", sourceID, err)
	}

	result := make([]ListOfTopItem, 0, len(rows))
	for _, row := range rows {
		result = append(result, ListOfTopItem{IP: row.IP.Addr().AsSlice(), Count: row.Count})
	}

	return result, nil
}
