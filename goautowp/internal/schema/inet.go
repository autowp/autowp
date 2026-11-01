package schema

import (
	"database/sql/driver"
	"errors"
	"fmt"
	"net/netip"

	"github.com/jackc/pgx/v5/pgtype"
)

var errInetTextExpected = errors.New("inet text expected")

// NullInet adapts pgx's netip.Prefix representation to database/sql.
// An invalid prefix represents SQL NULL.
//
//nolint:recvcheck // Scanning mutates the value; Value only reads it.
type NullInet struct {
	netip.Prefix
}

func (n *NullInet) Scan(value any) error {
	n.Prefix = netip.Prefix{}

	var text []byte

	switch value := value.(type) {
	case nil:
		return nil
	case string:
		text = []byte(value)
	case []byte:
		text = value
	default:
		return fmt.Errorf("%w: %T", errInetTextExpected, value)
	}

	codec := pgtype.InetCodec{}

	return codec.PlanScan(nil, pgtype.InetOID, pgtype.TextFormatCode, n).Scan(text, n)
}

func (n NullInet) Value() (driver.Value, error) {
	if !n.IsValid() {
		return nil, nil //nolint:nilnil // An invalid prefix is SQL NULL.
	}

	return n.String(), nil
}

func (n *NullInet) ScanNetipPrefix(prefix netip.Prefix) error {
	n.Prefix = prefix

	return nil
}

func (n NullInet) NetipPrefixValue() (netip.Prefix, error) {
	return n.Prefix, nil
}
