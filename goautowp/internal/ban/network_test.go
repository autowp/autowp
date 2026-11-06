package ban

import (
	"net"
	"net/netip"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/testutil"
	"github.com/stretchr/testify/require"
)

func TestNetworkBans(t *testing.T) {
	t.Parallel()
	db, sqlDB, _ := testutil.NewPostgres(t, "../..")
	_, err := sqlDB.ExecContext(t.Context(), `
 CREATE TEMP TABLE ip_ban (ip inet PRIMARY KEY, until timestamptz NOT NULL, by_user_id bigint, reason text NOT NULL);
 CREATE TEMP TABLE ip_whitelist (ip inet PRIMARY KEY, description text NOT NULL);`)
	require.NoError(t, err)
	svc, err := NewRepository(db)
	require.NoError(t, err)

	ctx := t.Context()
	for _, network := range []string{"192.0.2.0/24", "2001:db8:1::/64"} {
		require.NoError(t, svc.AddNetwork(ctx, netip.MustParsePrefix(network), time.Minute, "test "+network))
	}

	for address, want := range map[string]bool{
		"192.0.2.255": true, "192.0.3.0": false, "::ffff:192.0.2.2": true,
		"2001:db8:1::ffff": true, "2001:db8:1:1::1": false,
	} {
		ip := net.ParseIP(address)
		found, err := svc.Exists(ctx, ip)
		require.NoError(t, err)
		require.Equal(t, want, found, address)

		item, err := svc.Get(ctx, ip)
		if want {
			require.NoError(t, err)
			require.Contains(t, item.Reason, "test")
		} else {
			require.ErrorIs(t, err, ErrBanItemNotFound)
		}
	}

	_, err = sqlDB.ExecContext(ctx, "INSERT INTO ip_whitelist VALUES ('192.0.2.1', 'exempt')")
	require.NoError(t, err)

	ip := net.ParseIP("192.0.2.1")
	blocked, err := svc.Exists(ctx, ip)
	require.NoError(t, err)
	require.False(t, blocked)

	_, err = svc.Get(ctx, ip)
	require.ErrorIs(t, err, ErrBanItemNotFound)
	// Whitelisting one host does not exempt its neighbors or bypass an explicit host ban.
	require.NoError(t, svc.Add(ctx, ip, time.Hour, 0, "individual"))
	item, err := svc.Get(ctx, ip)
	require.NoError(t, err)
	require.Equal(t, "individual", item.Reason)
	require.NoError(t, svc.Remove(ctx, ip))
	blocked, err = svc.Exists(ctx, net.ParseIP("192.0.2.2"))
	require.NoError(t, err)
	require.True(t, blocked)

	_, err = sqlDB.ExecContext(ctx, "UPDATE ip_ban SET until = NOW() - INTERVAL '1 second'")
	require.NoError(t, err)
	blocked, err = svc.Exists(ctx, net.ParseIP("192.0.2.2"))
	require.NoError(t, err)
	require.False(t, blocked)
	require.ErrorIs(
		t,
		svc.AddNetwork(ctx, netip.MustParsePrefix("192.0.2.0/25"), time.Minute, "invalid"),
		errInvalidNetwork,
	)
}
