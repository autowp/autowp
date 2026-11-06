package ban

import (
	"context"
	"errors"
	"net"
	"net/netip"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	IPv4NetworkBits = 24
	IPv6NetworkBits = 64
)

var errInvalidNetwork = errors.New("expected an IPv4 /24 or IPv6 /64 network")

// NetworkPrefix normalizes mapped IPv4 addresses before selecting their quota group.
func NetworkPrefix(ip net.IP) netip.Prefix {
	address, ok := netip.AddrFromSlice(ip)
	if !ok {
		return netip.Prefix{}
	}

	address = address.Unmap()

	bits := IPv6NetworkBits
	if address.Is4() {
		bits = IPv4NetworkBits
	}

	return netip.PrefixFrom(address, bits).Masked()
}

func validNetwork(prefix netip.Prefix) bool {
	return prefix.IsValid() && ((prefix.Addr().Is4() && prefix.Bits() == IPv4NetworkBits) ||
		(prefix.Addr().Is6() && !prefix.Addr().Is4In6() && prefix.Bits() == IPv6NetworkBits))
}

// NetworkBan returns only the group's ban, without treating its network address as a host.
func (s *Repository) NetworkBan(ctx context.Context, prefix netip.Prefix) (*Item, error) {
	if !validNetwork(prefix) {
		return nil, errInvalidNetwork
	}

	var row schema.IPBanRow

	found, err := s.db.From(schema.IPBanTable).Select(schema.IPBanTable.All()).
		Where(schema.IPBanTableIPCol.Eq(prefix.Masked().String()), schema.IPBanTableUntilCol.Gte(goqu.Func("NOW"))).
		ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if !found {
		return nil, ErrBanItemNotFound
	}

	return &Item{
		IP:       net.IP(prefix.Addr().AsSlice()),
		Until:    row.Until,
		ByUserID: row.ByUserID.Int64,
		Reason:   row.Reason,
		Network:  true,
	}, nil
}

// Individual bans keep their existing semantics. Only network bans exempt whitelisted hosts.
// Both candidates are exact primary-key lookups, rather than a scan of containing networks.
func (s *Repository) matchAddress(ip net.IP) exp.Expression {
	prefix := NetworkPrefix(ip)
	if !prefix.IsValid() {
		return schema.IPBanTableIPCol.Eq(ip.String())
	}

	exempt := s.db.From(schema.IPWhitelistTable).Select(goqu.V(1)).
		Where(schema.IPWhitelistTableIPCol.Eq(ip.String()))

	return goqu.Or(schema.IPBanTableIPCol.Eq(ip.String()), goqu.And(
		schema.IPBanTableIPCol.Eq(prefix.String()), goqu.L("NOT EXISTS ?", exempt),
	))
}
