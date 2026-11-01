package schema_test

import (
	"net/netip"
	"testing"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/jackc/pgx/v5/pgtype"
	"github.com/stretchr/testify/require"
)

func TestNullInetScan(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name   string
		input  any
		prefix string
		fail   bool
	}{
		{name: "IPv4 address", input: "192.0.2.123", prefix: "192.0.2.123/32"},
		{name: "IPv4 prefix", input: "192.0.2.123/24", prefix: "192.0.2.123/24"},
		{name: "IPv6 address", input: []byte("2001:db8::123"), prefix: "2001:db8::123/128"},
		{name: "IPv6 prefix", input: []byte("2001:db8::123/64"), prefix: "2001:db8::123/64"},
		{name: "mapped IPv4", input: "::ffff:192.0.2.123", prefix: "::ffff:192.0.2.123/128"},
		{name: "NULL", input: nil},
		{name: "nil bytes", input: []byte(nil)},
		{name: "invalid address", input: "not an IP", fail: true},
		{name: "invalid mask", input: "192.0.2.123/33", fail: true},
		{name: "unsupported value", input: 123, fail: true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			value := schema.NullInet{Prefix: netip.MustParsePrefix("127.0.0.1/32")}

			err := value.Scan(tt.input)
			if tt.fail {
				require.Error(t, err)
				require.False(t, value.IsValid())

				return
			}

			require.NoError(t, err)
			encoded, err := value.Value()
			require.NoError(t, err)

			if tt.prefix == "" {
				require.False(t, value.IsValid())
				require.Nil(t, encoded)

				return
			}

			require.Equal(t, netip.MustParsePrefix(tt.prefix), value.Prefix)
			require.Equal(t, tt.prefix, encoded)
		})
	}
}

func TestNullInetPGXCodec(t *testing.T) {
	t.Parallel()

	for _, prefix := range []string{"192.0.2.123/24", "2001:db8::123/64", "::ffff:192.0.2.123/128", ""} {
		t.Run(prefix, func(t *testing.T) {
			t.Parallel()

			var source schema.NullInet
			if prefix != "" {
				source.Prefix = netip.MustParsePrefix(prefix)
			}

			types := pgtype.NewMap()
			encoded, err := types.Encode(pgtype.InetOID, pgtype.BinaryFormatCode, source, nil)
			require.NoError(t, err)

			destination := schema.NullInet{Prefix: netip.MustParsePrefix("127.0.0.1/32")}
			err = types.Scan(pgtype.InetOID, pgtype.BinaryFormatCode, encoded, &destination)
			require.NoError(t, err)
			require.Equal(t, source, destination)
		})
	}
}

func TestInetAndUUIDPostgresRoundTrip(t *testing.T) {
	t.Parallel()

	db, _, _ := testutil.NewPostgres(t, "../..")
	guid := uuid.MustParse("1e946ea4-0a48-4119-b3ab-0a7debd7669b")

	for _, prefix := range []string{"192.0.2.123/24", "2001:db8::123/64", "::ffff:192.0.2.123/128", ""} {
		t.Run(prefix, func(t *testing.T) {
			t.Parallel()

			var (
				address    schema.NullInet
				identifier uuid.NullUUID
			)
			if prefix != "" {
				address.Prefix = netip.MustParsePrefix(prefix)
				identifier = uuid.NullUUID{UUID: guid, Valid: true}
			}

			row := struct {
				IP   schema.NullInet `db:"ip"`
				UUID uuid.NullUUID   `db:"uuid"`
			}{
				IP:   schema.NullInet{Prefix: netip.MustParsePrefix("127.0.0.1/32")},
				UUID: uuid.NullUUID{UUID: guid, Valid: true},
			}
			success, err := db.Select(
				goqu.Cast(goqu.V(address), "inet").As("ip"),
				goqu.Cast(goqu.V(identifier), "uuid").As("uuid"),
			).
				ScanStructContext(t.Context(), &row)
			require.NoError(t, err)
			require.True(t, success)
			require.Equal(t, address, row.IP)
			require.Equal(t, identifier, row.UUID)
		})
	}
}
