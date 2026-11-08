package traffic

import (
	"context"
	"fmt"
	"net/netip"

	"github.com/autowp/goautowp/internal/ban"
	"github.com/autowp/goautowp/internal/schema"
)

func (s *Repository) SourcesTraffic(ctx context.Context) ([]SourceTraffic, error) {
	rows, err := s.Monitoring.ListSources(ctx)
	if err != nil {
		return nil, err
	}

	active, err := s.Ban.ActiveRanges(ctx)
	if err != nil {
		return nil, err
	}

	if len(active) == 0 {
		return rows, nil
	}

	var identities []struct {
		IP       schema.NullInet `db:"ip"`
		SourceID string          `db:"source_id"`
	}

	err = s.Monitoring.db.From(schema.IPSourceTable).
		Select(schema.IPSourceTableIPCol, schema.IPSourceTableSourceIDCol).ScanStructsContext(ctx, &identities)
	if err != nil {
		return nil, fmt.Errorf("read verified source addresses for bans: %w", err)
	}

	for i := range rows {
		ranges := make([]netip.Prefix, 0, len(rows[i].Networks))
		for _, network := range rows[i].Networks {
			if prefix, err := netip.ParsePrefix(network); err == nil {
				ranges = append(ranges, prefix)
			}
		}

		for _, identity := range identities {
			if identity.SourceID == rows[i].ID && identity.IP.IsValid() {
				address := identity.IP.Addr().Unmap()
				ranges = append(ranges, netip.PrefixFrom(address, address.BitLen()))
			}
		}

		rows[i].Bans = intersectingBans(ranges, active)
	}

	return rows, nil
}

func (s *Repository) NetworksTraffic(ctx context.Context, limit uint) ([]NetworkTraffic, error) {
	rows, err := s.Monitoring.ListOfTopNetworks(ctx, limit)
	if err != nil {
		return nil, err
	}

	active, err := s.Ban.ActiveRanges(ctx)
	if err != nil {
		return nil, err
	}

	for i := range rows {
		prefix, err := netip.ParsePrefix(rows[i].Network)
		if err != nil {
			return nil, fmt.Errorf("parse monitored network: %w", err)
		}

		rows[i].Bans = intersectingBans([]netip.Prefix{prefix}, active)
	}

	return rows, nil
}

func intersectingBans(ranges []netip.Prefix, active []ban.RangeBan) []ban.RangeBan {
	matches := make([]ban.RangeBan, 0)

	for _, blocked := range active {
		prefix, err := netip.ParsePrefix(blocked.Network)
		if err != nil {
			address, parseErr := netip.ParseAddr(blocked.Network)
			if parseErr != nil {
				continue
			}

			prefix = netip.PrefixFrom(address, address.BitLen())
		}

		for _, network := range ranges {
			if network.Overlaps(prefix) {
				matches = append(matches, blocked)

				break
			}
		}
	}

	return matches
}
