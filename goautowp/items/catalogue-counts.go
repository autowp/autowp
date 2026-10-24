package items

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// VehicleTypeIDsBatch matches VehicleTypeIDs for every requested item.
func (s *Repository) VehicleTypeIDsBatch(
	ctx context.Context, ids []int64, inherited bool,
) (map[int64][]int64, error) {
	result := make(map[int64][]int64, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []schema.ItemVehicleTypeRow

	err := s.db.From(schema.ItemVehicleTypeTable).
		Select(schema.ItemVehicleTypeTableItemIDCol, schema.ItemVehicleTypeTableVehicleTypeIDCol).
		Where(schema.ItemVehicleTypeTableItemIDCol.In(ids),
			schema.ItemVehicleTypeTableInheritedCol.Eq(inherited)).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = append(result[row.ItemID], row.VehicleTypeID)
	}

	return result, nil
}

// ChildsCountsBatch counts direct children by parent and relationship type.
func (s *Repository) ChildsCountsBatch(ctx context.Context, ids []int64) (map[int64][]ChildCount, error) {
	result := make(map[int64][]ChildCount, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ParentID int64                 `db:"parent_id"`
		Type     schema.ItemParentType `db:"type"`
		Count    int32                 `db:"count"`
	}

	err := s.db.From(schema.ItemParentTable).
		Select(schema.ItemParentTableParentIDCol, schema.ItemParentTableTypeCol, goqu.COUNT(goqu.Star()).As("count")).
		Where(schema.ItemParentTableParentIDCol.In(ids)).
		GroupBy(schema.ItemParentTableParentIDCol, schema.ItemParentTableTypeCol).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ParentID] = append(result[row.ParentID], ChildCount{Type: row.Type, Count: row.Count})
	}

	return result, nil
}

// EngineVehiclesCounts matches Count with EngineItemID, including inherited references
// and group items. Field permissions and the engine item type are checked by the caller.
func (s *Repository) EngineVehiclesCounts(ctx context.Context, ids []int64) (map[int64]int32, error) {
	result := make(map[int64]int32, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		EngineID int64 `db:"engine_item_id"`
		Count    int32 `db:"count"`
	}

	err := s.db.From(schema.ItemTable).
		Select(schema.ItemTableEngineItemIDCol, goqu.COUNT(goqu.Star()).As("count")).
		Where(schema.ItemTableEngineItemIDCol.In(ids)).GroupBy(schema.ItemTableEngineItemIDCol).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.EngineID] = row.Count
	}

	return result, nil
}
