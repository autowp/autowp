package traffic

import (
	"database/sql"
	"net"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres"
	_ "github.com/golang-migrate/migrate/v4/database/postgres" // enable postgres migrations
	"github.com/stretchr/testify/require"
)

func createMonitoringService(t *testing.T) *Monitoring {
	t.Helper()

	cfg := config.LoadConfig("..")

	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	db.SetMaxOpenConns(2)
	t.Cleanup(func() { require.NoError(t, db.Close()) })

	goquDB := goqu.New("postgres", db)

	s, err := NewMonitoring(goquDB)
	require.NoError(t, err)

	return s
}

func TestMonitoringAdd(t *testing.T) {
	t.Parallel()

	svc := createMonitoringService(t)

	ctx := t.Context()

	err := svc.Add(ctx, net.IPv4(192, 168, 0, 1), time.Now())
	require.NoError(t, err)

	err = svc.Add(ctx, net.IPv6loopback, time.Now())
	require.NoError(t, err)
}

func TestMonitoringGC(t *testing.T) {
	t.Parallel()

	svc := createMonitoringService(t)

	ctx := t.Context()

	err := svc.Clear(ctx)
	require.NoError(t, err)

	err = svc.Add(ctx, net.IPv4(192, 168, 0, 77), time.Now())
	require.NoError(t, err)

	affected, err := svc.GC(ctx)
	require.NoError(t, err)
	require.Zero(t, affected)

	_, err = svc.ListOfTop(ctx, 10)
	require.NoError(t, err)
}

func TestListByBanProfile(t *testing.T) { //nolint:paralleltest
	svc := createMonitoringService(t)

	ctx := t.Context()

	err := svc.Clear(ctx)
	require.NoError(t, err)

	for range 2 {
		err = svc.Add(ctx, net.IPv4(192, 168, 0, 77), time.Now())
		require.NoError(t, err)
	}

	ips, err := svc.ListByBanProfile(ctx, AutobanProfile{
		Limit:  1,
		Reason: "test",
		Group:  []interface{}{},
		Time:   time.Hour,
	})
	require.NoError(t, err)
	require.NotEmpty(t, ips)

	require.Equal(t, "192.168.0.77", ips[0].String())
}

func TestMonitoringBatchCounts(t *testing.T) { //nolint:paralleltest // Shares the monitoring table with cleanup tests.
	svc := createMonitoringService(t)
	ctx := t.Context()
	ip := net.ParseIP("192.0.2.231").To4()

	require.NoError(t, svc.ClearIP(ctx, ip))
	defer func() { require.NoError(t, svc.ClearIP(ctx, ip)) }()

	minute := time.Now().UTC().Truncate(time.Minute)
	require.NoError(t, svc.AddCounts(ctx, []MonitoringCount{{IP: ip.String(), Timestamp: minute, Count: 20}}))
	require.NoError(t, svc.AddCounts(ctx, []MonitoringCount{{IP: ip.String(), Timestamp: minute, Count: 30}}))

	profile := AutobanProfile{Limit: 49}
	ips, err := svc.ListByBanProfile(ctx, profile)
	require.NoError(t, err)
	require.Contains(t, ips, ip)

	profile.Limit = 50
	ips, err = svc.ListByBanProfile(ctx, profile)
	require.NoError(t, err)
	require.NotContains(t, ips, ip)
}

//nolint:paralleltest // Shares monitoring tables.
func TestMonitoringExcludesWhitelistedIP(t *testing.T) {
	svc := createTrafficService(t)
	ctx := t.Context()
	ip := net.ParseIP("2001:db8::231")
	require.NoError(t, svc.Whitelist.Remove(ctx, ip))

	require.NoError(t, svc.Monitoring.ClearIP(ctx, ip))
	defer func() {
		require.NoError(t, svc.Whitelist.Remove(ctx, ip))
		require.NoError(t, svc.Monitoring.ClearIP(ctx, ip))
	}()

	timestamp := time.Now()
	require.NoError(
		t,
		svc.Monitoring.AddCounts(ctx, []MonitoringCount{{IP: ip.String(), Timestamp: timestamp, Count: 20}}),
	)
	require.NoError(t, svc.Whitelist.Add(ctx, ip, "monitoring test"))

	// Even pre-existing monitoring rows must not enter the autoban candidate list.
	candidates, err := svc.Monitoring.ListByBanProfile(ctx, AutobanProfile{Limit: 1})
	require.NoError(t, err)
	require.NotContains(t, candidates, ip)
	require.NoError(t, svc.AutoWhitelistIP(ctx, ip))
	exists, err := svc.Monitoring.ExistsIP(ctx, ip)
	require.NoError(t, err)
	require.False(t, exists)

	// A buffered batch or legacy AMQP event cannot recreate the cleared rows.
	require.NoError(
		t,
		svc.Monitoring.AddCounts(ctx, []MonitoringCount{{IP: ip.String(), Timestamp: timestamp, Count: 30}}),
	)
	require.NoError(t, svc.Monitoring.Add(ctx, ip, timestamp))
	exists, err = svc.Monitoring.ExistsIP(ctx, ip)
	require.NoError(t, err)
	require.False(t, exists)

	snapshot, err := svc.Whitelist.IPSet(ctx)
	require.NoError(t, err)
	require.Contains(t, snapshot, ip.String())

	require.NoError(t, svc.Whitelist.Remove(ctx, ip))
	require.NoError(t, svc.Monitoring.Add(ctx, ip, timestamp))
	exists, err = svc.Monitoring.ExistsIP(ctx, ip)
	require.NoError(t, err)
	require.True(t, exists)
}
