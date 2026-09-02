package traffic

import (
	"context"
	"net"
	"time"

	"github.com/autowp/goautowp/ban"
	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const (
	autowhitelistLimit  = 1000
	banByUserID         = 9
	hoursInDay          = 24
	halfDay             = time.Hour * hoursInDay / 2
	hourlyLimitDuration = time.Hour * 5 * hoursInDay
	dailyLimitDuration  = time.Hour * 10 * hoursInDay
	dailyLimit          = 10000
	hourlyLimit         = 3600
	tenMinsLimit        = 1200
	oneMinLimit         = 700
)

// Repository Main Object.
type Repository struct {
	Monitoring *Monitoring
	Whitelist  *Whitelist
	Ban        *ban.Repository
}

// AutobanProfile AutobanProfile.
type AutobanProfile struct {
	Limit  int
	Reason string
	Group  []interface{}
	Time   time.Duration
}

// AutobanProfiles AutobanProfiles.
var AutobanProfiles = []AutobanProfile{
	{
		Limit:  dailyLimit,
		Reason: "daily limit",
		Group:  []interface{}{},
		Time:   dailyLimitDuration,
	},
	{
		Limit:  hourlyLimit,
		Reason: "hourly limit",
		Group:  []interface{}{schema.IPMonitoringTableHourCol},
		Time:   hourlyLimitDuration,
	},
	{
		Limit:  tenMinsLimit,
		Reason: "ten min limit",
		Group: []interface{}{
			schema.IPMonitoringTableHourCol,
			schema.IPMonitoringTableTenminuteCol,
		},
		Time: time.Hour * hoursInDay,
	},
	{
		Limit:  oneMinLimit,
		Reason: "min limit",
		Group: []interface{}{
			schema.IPMonitoringTableHourCol,
			schema.IPMonitoringTableTenminuteCol,
			schema.IPMonitoringTableMinuteCol,
		},
		Time: halfDay,
	},
}

// APITrafficBlacklistPostRequestBody APITrafficBlacklistPostRequestBody.
type APITrafficBlacklistPostRequestBody struct {
	IP     net.IP `json:"ip"`
	Period int    `json:"period"`
	Reason string `json:"reason"`
}

type APITrafficWhitelistPostRequestBody struct {
	IP net.IP `json:"ip"`
}

// NewRepository constructor.
func NewRepository(
	pool *goqu.Database,
	ban *ban.Repository,
) (*Repository, error) {
	monitoring, err := NewMonitoring(pool)
	if err != nil {
		logging.Error(err)

		return nil, err
	}

	whitelist, err := NewWhitelist(pool)
	if err != nil {
		logging.Error(err)

		return nil, err
	}

	return &Repository{
		Monitoring: monitoring,
		Whitelist:  whitelist,
		Ban:        ban,
	}, nil
}

func (s *Repository) AutoBanByProfile(ctx context.Context, profile AutobanProfile) error {
	ips, err := s.Monitoring.ListByBanProfile(ctx, profile)
	if err != nil {
		return err
	}

	for _, ip := range ips {
		exists, err := s.Whitelist.Exists(ctx, ip)
		if err != nil {
			return err
		}

		if exists {
			continue
		}

		logging.Infof("%s %v", profile.Reason, ip)

		if err := s.Ban.Add(ctx, ip, profile.Time, banByUserID, profile.Reason); err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) AutoBan(ctx context.Context) error {
	for _, profile := range AutobanProfiles {
		if err := s.AutoBanByProfile(ctx, profile); err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) AutoWhitelist(ctx context.Context) error {
	items, err := s.Monitoring.ListOfTop(ctx, autowhitelistLimit)
	if err != nil {
		return err
	}

	for _, item := range items {
		logging.Infof("Check IP %v", item.IP.String())

		if err = s.AutoWhitelistIP(ctx, item.IP); err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) AutoWhitelistIP(ctx context.Context, ip net.IP) error {
	ipText := ip.String()

	inWhitelist, err := s.Whitelist.Exists(ctx, ip)
	if err != nil {
		return err
	}

	match, desc := s.Whitelist.MatchAuto(ctx, ip)

	if !match {
		return nil
	}

	if inWhitelist {
		logging.Info(ipText + ": already in whitelist, skip")
	} else {
		if err = s.Whitelist.Add(ctx, ip, desc); err != nil {
			return err
		}
	}

	if err = s.Ban.Remove(ctx, ip); err != nil {
		return err
	}

	if err = s.Monitoring.ClearIP(ctx, ip); err != nil {
		return err
	}

	logging.Info(ipText + ": whitelisted")

	return nil
}
