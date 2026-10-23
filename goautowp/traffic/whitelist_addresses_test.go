package traffic

import (
	"database/sql"
	"net"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func createTemporaryWhitelistService(t *testing.T) *Whitelist {
	t.Helper()

	cfg := config.LoadConfig("..")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, db.Close()) })

	_, err = db.ExecContext(t.Context(), `CREATE TEMP TABLE ip_whitelist (
		ip inet PRIMARY KEY, description text NOT NULL
	)`)
	require.NoError(t, err)

	svc, err := NewWhitelist(goqu.New("postgres", db))
	require.NoError(t, err)

	return svc
}

func TestWhitelistAddresses(t *testing.T) {
	t.Parallel()

	svc := createTemporaryWhitelistService(t)
	ctx := t.Context()

	for _, address := range []string{"192.0.2.201", "::ffff:192.0.2.202", "2001:db8::201"} {
		t.Run(address, func(t *testing.T) {
			t.Parallel()

			ip := net.ParseIP(address)
			require.NotNil(t, ip)

			require.NoError(t, svc.Add(ctx, ip, "address round trip"))

			item, err := svc.Get(ctx, ip)
			require.NoError(t, err)
			require.Equal(t, ip.String(), item.IP.String())
			require.Equal(t, "address round trip", item.Description)

			items, _, err := svc.List(ctx, 1)
			require.NoError(t, err)

			found := false

			for _, item := range items {
				if item.IP.String() == ip.String() {
					require.Equal(t, "address round trip", item.Description)

					found = true
				}
			}

			require.True(t, found, "address %s missing from whitelist", address)
		})
	}
}

func TestWhitelistPagination(t *testing.T) {
	t.Parallel()

	svc := createTemporaryWhitelistService(t)
	ctx := t.Context()

	items, pages, err := svc.List(ctx, 2147483647)
	require.NoError(t, err)
	require.Empty(t, items)
	require.Zero(t, pages.PageCount)
	require.Zero(t, pages.TotalItemCount)
	require.EqualValues(t, 1, pages.Current)

	const total = 105

	rows := make([]goqu.Record, 0, total)
	for i := total; i > 0; i-- {
		rows = append(rows, goqu.Record{
			schema.IPWhitelistTableIPColName:          net.IPv4(192, 0, 2, byte(i)).String(),
			schema.IPWhitelistTableDescriptionColName: "pagination test",
		})
	}

	_, err = svc.db.Insert(schema.IPWhitelistTable).Rows(rows).Executor().ExecContext(ctx)
	require.NoError(t, err)

	for _, tc := range []struct {
		name    string
		page    int32
		current int32
		count   int
		first   string
		last    string
	}{
		{name: "default", page: 0, current: 1, count: 50, first: "192.0.2.1", last: "192.0.2.50"},
		{name: "negative", page: -1, current: 1, count: 50, first: "192.0.2.1", last: "192.0.2.50"},
		{name: "second", page: 2, current: 2, count: 50, first: "192.0.2.51", last: "192.0.2.100"},
		{name: "last", page: 3, current: 3, count: 5, first: "192.0.2.101", last: "192.0.2.105"},
		{name: "out of range", page: 99, current: 3, count: 5, first: "192.0.2.101", last: "192.0.2.105"},
	} {
		items, pages, err := svc.List(ctx, tc.page)
		require.NoError(t, err, tc.name)
		require.Len(t, items, tc.count, tc.name)
		require.Equal(t, tc.current, pages.Current, tc.name)
		require.EqualValues(t, 3, pages.PageCount, tc.name)
		require.EqualValues(t, total, pages.TotalItemCount, tc.name)
		require.Equal(t, tc.first, items[0].IP.String(), tc.name)
		require.Equal(t, tc.last, items[len(items)-1].IP.String(), tc.name)
	}

	_, err = svc.db.Delete(schema.IPWhitelistTable).
		Where(schema.IPWhitelistTableIPCol.Gte("192.0.2.101")).Executor().ExecContext(ctx)
	require.NoError(t, err)
	items, pages, err = svc.List(ctx, 3)
	require.NoError(t, err)
	require.Len(t, items, 50)
	require.EqualValues(t, 2, pages.Current)
	require.EqualValues(t, 2, pages.PageCount)
	require.EqualValues(t, 100, pages.TotalItemCount)
}

func TestWhitelistListMappedAddress(t *testing.T) {
	t.Parallel()

	svc := createTemporaryWhitelistService(t)
	_, err := svc.db.Insert(schema.IPWhitelistTable).Rows(goqu.Record{
		schema.IPWhitelistTableIPColName:          "::ffff:5.255.253.147",
		schema.IPWhitelistTableDescriptionColName: "mapped address",
	}).Executor().ExecContext(t.Context())
	require.NoError(t, err)

	items, _, err := svc.List(t.Context(), 1)
	require.NoError(t, err)
	require.Len(t, items, 1)
	require.Equal(t, "5.255.253.147", items[0].IP.String())
}
