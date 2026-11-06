package traffic

import (
	"net"
	"net/netip"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/ban"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func temporaryNetworkTraffic(t *testing.T) (*Repository, *goqu.Database) {
	t.Helper()
	db, sqlDB, _ := testutil.NewPostgres(t, "../..")
	_, err := sqlDB.ExecContext(t.Context(), `
 CREATE TEMP TABLE ip_monitoring (
 ip inet NOT NULL, day_date date NOT NULL, hour smallint NOT NULL,
 tenminute smallint NOT NULL, minute smallint NOT NULL, count integer NOT NULL,
 PRIMARY KEY (ip,day_date,hour,tenminute,minute));
 CREATE TEMP TABLE ip_whitelist (ip inet PRIMARY KEY, description text NOT NULL);
 CREATE TEMP TABLE ip_ban (ip inet PRIMARY KEY, until timestamptz NOT NULL, by_user_id bigint, reason text NOT NULL);`)
	require.NoError(t, err)
	bans, err := ban.NewRepository(db)
	require.NoError(t, err)
	repository, err := NewRepository(db, bans)
	require.NoError(t, err)

	return repository, db
}

func TestNetworkTraffic(t *testing.T) {
	t.Parallel()
	svc, db := temporaryNetworkTraffic(t)
	ctx := t.Context()

	var now time.Time

	_, err := db.Select(goqu.L("CURRENT_TIMESTAMP")).ScanValContext(ctx, &now)
	require.NoError(t, err)

	counts := []MonitoringCount{
		{IP: "47.79.13.1", Timestamp: now, Count: 60},
		{IP: "47.79.13.200", Timestamp: now, Count: 50},
		{IP: "47.79.14.1", Timestamp: now, Count: 70},
		{IP: "2001:db8:1::1", Timestamp: now, Count: 40},
		{IP: "2001:db8:1::ffff", Timestamp: now, Count: 45},
		{IP: "2001:db8:1:1::1", Timestamp: now, Count: 30},
		{IP: "47.79.13.2", Timestamp: now, Count: 500},
		{IP: "192.0.2.1", Timestamp: now.Add(-24 * time.Hour), Count: 1000},
	}
	require.NoError(t, svc.Monitoring.AddCounts(ctx, counts))
	require.NoError(t, svc.Whitelist.Add(ctx, net.ParseIP("47.79.13.2"), "exempt"))
	rows, err := svc.Monitoring.ListOfTopNetworks(ctx, 50)
	require.NoError(t, err)
	require.Equal(t, []NetworkTraffic{
		{Network: "47.79.13.0/24", Count: 110, Addresses: 2},
		{Network: "2001:db8:1::/64", Count: 85, Addresses: 2},
		{Network: "47.79.14.0/24", Count: 70, Addresses: 1},
		{Network: "2001:db8:1:1::/64", Count: 30, Addresses: 1},
	}, rows)

	for _, network := range []string{"47.79.13.0/24", "2001:db8:1::/64"} {
		ips, err := svc.Monitoring.ListNetworkIPs(ctx, netip.MustParsePrefix(network), 50)
		require.NoError(t, err)
		require.Len(t, ips, 2)
	}

	profile := AutobanProfile{Limit: 100, Time: time.Minute, Reason: "test network quota"}
	networks, err := svc.Monitoring.ListNetworksByBanProfile(ctx, profile)
	require.NoError(t, err)
	require.Equal(t, []netip.Prefix{netip.MustParsePrefix("47.79.13.0/24")}, networks)
	require.NoError(t, svc.AutoBanNetworksByProfile(ctx, profile))
	// A rotated address that has never appeared in monitoring is still blocked.
	for address, want := range map[string]bool{"47.79.13.250": true, "47.79.14.250": false, "47.79.13.2": false} {
		blocked, err := svc.Ban.Exists(ctx, net.ParseIP(address))
		require.NoError(t, err)
		require.Equal(t, want, blocked, address)
	}
	// Re-running does not prolong an active network ban.
	prefix := netip.MustParsePrefix("47.79.13.0/24")
	before, err := svc.Ban.NetworkBan(ctx, prefix)
	require.NoError(t, err)
	require.NoError(t, svc.AutoBanNetworksByProfile(ctx, profile))
	after, err := svc.Ban.NetworkBan(ctx, prefix)
	require.NoError(t, err)
	require.Equal(t, before.Until, after.Until)
}

func TestNetworkQuotaOnlyCurrentBucket(t *testing.T) {
	t.Parallel()
	svc, db := temporaryNetworkTraffic(t)
	ctx := t.Context()
	// Put a burst in a different minute of this hour, without crossing a date boundary.
	_, err := db.ExecContext(ctx, `INSERT INTO ip_monitoring
 SELECT '192.0.2.1', CURRENT_DATE, EXTRACT(HOUR FROM NOW()),
 FLOOR(((EXTRACT(MINUTE FROM NOW())::int + 30) % 60)/10.0),
 (EXTRACT(MINUTE FROM NOW())::int + 30) % 60, 100`)
	require.NoError(t, err)

	profile := AutobanProfile{Limit: 99, Group: NetworkAutobanProfiles[3].Group}
	networks, err := svc.Monitoring.ListNetworksByBanProfile(ctx, profile)
	require.NoError(t, err)
	require.Empty(t, networks)

	profile.Group = NetworkAutobanProfiles[0].Group
	networks, err = svc.Monitoring.ListNetworksByBanProfile(ctx, profile)
	require.NoError(t, err)
	require.Len(t, networks, 1)
}
