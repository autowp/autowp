package traffic

import (
	"database/sql"
	"net"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/golang-migrate/migrate/v4/database/postgres" // enable postgres migrations
	"github.com/stretchr/testify/require"
)

func createMonitoringService(t *testing.T) *Monitoring {
	t.Helper()

	cfg := config.LoadConfig("..")

	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

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
		Time:   dailyLimitDuration,
	})
	require.NoError(t, err)
	require.NotEmpty(t, ips)

	require.Equal(t, "192.168.0.77", ips[0].String())
}
