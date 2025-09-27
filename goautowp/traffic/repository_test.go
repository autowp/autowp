package traffic

import (
	"database/sql"
	"net"
	"testing"
	"time"

	"github.com/autowp/goautowp/ban"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/go-sql-driver/mysql" // enable mysql driver
	"github.com/stretchr/testify/require"
)

func createTrafficService(t *testing.T) *Traffic {
	t.Helper()

	cfg := config.LoadConfig("..")

	autowpDB, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", autowpDB)

	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquPostgresDB := goqu.New("postgres", db)

	banRepository, err := ban.NewRepository(goquPostgresDB)
	require.NoError(t, err)

	traf, err := NewTraffic(goquPostgresDB, goquDB, banRepository)
	require.NoError(t, err)

	return traf
}

func TestAutoWhitelist(t *testing.T) { //nolint:paralleltest
	svc := createTrafficService(t)

	ctx := t.Context()

	ip := net.IPv4(66, 249, 73, 139) // google

	err := svc.Ban.Add(ctx, ip, time.Hour, 9, "test")
	require.NoError(t, err)

	exists, err := svc.Ban.Exists(ctx, ip)
	require.NoError(t, err)
	require.True(t, exists)

	err = svc.Monitoring.Add(ctx, ip, time.Now())
	require.NoError(t, err)

	exists, err = svc.Monitoring.ExistsIP(ctx, ip)
	require.NoError(t, err)
	require.True(t, exists)

	err = svc.AutoWhitelist(ctx)
	require.NoError(t, err)

	exists, err = svc.Ban.Exists(ctx, ip)
	require.NoError(t, err)
	require.False(t, exists)

	exists, err = svc.Monitoring.ExistsIP(ctx, ip)
	require.NoError(t, err)
	require.False(t, exists)

	exists, err = svc.Whitelist.Exists(ctx, ip)
	require.NoError(t, err)
	require.True(t, exists)
}

func TestAutoBanByProfile(t *testing.T) { //nolint:paralleltest
	svc := createTrafficService(t)

	ctx := t.Context()

	profile := AutobanProfile{
		Limit:  3,
		Reason: "Test",
		Group: []interface{}{
			schema.IPMonitoringTableHourCol, schema.IPMonitoringTableTenminuteCol, schema.IPMonitoringTableMinuteCol,
		},
		Time: time.Hour,
	}

	ip1 := net.IPv4(127, 0, 0, 11)
	ip2 := net.IPv4(127, 0, 0, 12)

	err := svc.Monitoring.ClearIP(ctx, ip1)
	require.NoError(t, err)
	err = svc.Monitoring.ClearIP(ctx, ip2)
	require.NoError(t, err)

	err = svc.Ban.Remove(ctx, ip1)
	require.NoError(t, err)
	err = svc.Ban.Remove(ctx, ip2)
	require.NoError(t, err)

	err = svc.Monitoring.Add(ctx, ip1, time.Now())
	require.NoError(t, err)

	for range 4 {
		err = svc.Monitoring.Add(ctx, ip2, time.Now())
		require.NoError(t, err)
	}

	err = svc.AutoBanByProfile(ctx, profile)
	require.NoError(t, err)

	exists, err := svc.Ban.Exists(ctx, ip1)
	require.NoError(t, err)
	require.False(t, exists)

	exists, err = svc.Ban.Exists(ctx, ip2)
	require.NoError(t, err)
	require.True(t, exists)
}

func TestWhitelistedNotBanned(t *testing.T) {
	t.Parallel()

	svc := createTrafficService(t)

	ctx := t.Context()

	profile := AutobanProfile{
		Limit:  3,
		Reason: "TestWhitelistedNotBanned",
		Group: []interface{}{
			schema.IPMonitoringTableHourCol,
			schema.IPMonitoringTableTenminuteCol,
			schema.IPMonitoringTableMinuteCol,
		},
		Time: time.Hour,
	}

	ip := net.IPv4(178, 154, 244, 21)

	err := svc.Whitelist.Add(ctx, ip, "TestWhitelistedNotBanned")
	require.NoError(t, err)

	for range 4 {
		err = svc.Monitoring.Add(ctx, ip, time.Now())
		require.NoError(t, err)
	}

	err = svc.AutoWhitelistIP(ctx, ip)
	require.NoError(t, err)

	err = svc.AutoBanByProfile(ctx, profile)
	require.NoError(t, err)

	exists, err := svc.Ban.Exists(ctx, ip)
	require.NoError(t, err)
	require.False(t, exists)
}

func TestTop(t *testing.T) {
	t.Parallel()

	svc := createTrafficService(t)

	ctx := t.Context()

	err := svc.Ban.Clear(ctx)
	require.NoError(t, err)

	err = svc.Monitoring.Clear(ctx)
	require.NoError(t, err)

	err = svc.Monitoring.Add(ctx, net.IPv4(192, 168, 0, 1), time.Now())
	require.NoError(t, err)

	now := time.Now()
	for range 10 {
		err = svc.Monitoring.Add(ctx, net.IPv6loopback, now)
		require.NoError(t, err)
	}
}
