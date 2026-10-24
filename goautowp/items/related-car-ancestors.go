package items

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

func (s *Repository) relatedCarAncestors(ctx context.Context, ids []int64) (map[int64][]int64, error) {
	result := make(map[int64][]int64, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ItemID   int64 `db:"item_id"`
		ParentID int64 `db:"parent_id"`
	}

	err := s.db.From(schema.ItemParentCacheTable).
		Join(schema.ItemTable, goqu.On(schema.ItemParentCacheTableParentIDCol.Eq(schema.ItemTableIDCol))).
		Select(schema.ItemParentCacheTableItemIDCol, schema.ItemParentCacheTableParentIDCol).
		Where(schema.ItemParentCacheTableItemIDCol.In(ids),
			schema.ItemParentCacheTableItemIDCol.Neq(schema.ItemParentCacheTableParentIDCol),
			schema.ItemTableItemTypeIDCol.In([]schema.ItemTableItemTypeID{
				schema.ItemTableItemTypeIDVehicle, schema.ItemTableItemTypeIDEngine,
			})).Order(schema.ItemParentCacheTableDiffCol.Desc()).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = append(result[row.ItemID], row.ParentID)
	}

	return result, nil
}
