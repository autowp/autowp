package items

import (
	"context"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
)

type PublicRouteGraph struct {
	Parents map[int64][]*ItemParent
	Brands  map[int64]string
}

// PublicRouteAncestors loads shared ancestors once, including ancestors above brands:
// public routes continue walking after emitting a brand route.
func (s *Repository) PublicRouteAncestors(ctx context.Context, ids []int64) (*PublicRouteGraph, error) {
	result := &PublicRouteGraph{Parents: make(map[int64][]*ItemParent), Brands: make(map[int64]string)}
	seen := make(map[int64]bool)

	frontier := make([]int64, 0, len(ids))
	for _, id := range ids {
		if !seen[id] {
			seen[id] = true
			frontier = append(frontier, id)
		}
	}

	parentIDs := make([]int64, 0)

	for len(frontier) > 0 {
		rows, _, err := s.ItemParents(
			ctx,
			&query.ItemParentListOptions{ItemIDs: frontier},
			ItemParentFields{},
			ItemParentOrderByNone,
		)
		if err != nil {
			return nil, err
		}

		frontier = frontier[:0]

		for _, row := range rows {
			result.Parents[row.ItemID] = append(result.Parents[row.ItemID], row)

			parentIDs = append(parentIDs, row.ParentID)
			if !seen[row.ParentID] {
				seen[row.ParentID] = true
				frontier = append(frontier, row.ParentID)
			}
		}
	}

	if len(parentIDs) == 0 {
		return result, nil
	}

	var brands []cataloguePathNode

	err := s.db.From(schema.ItemTable).Select(schema.ItemTableIDCol, schema.ItemTableCatnameCol).
		Where(schema.ItemTableIDCol.In(parentIDs), schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDBrand)).
		ScanStructsContext(ctx, &brands)
	if err != nil {
		return nil, err
	}

	for _, brand := range brands {
		result.Brands[brand.ID] = util.NullStringToString(brand.Catname)
	}

	return result, nil
}
