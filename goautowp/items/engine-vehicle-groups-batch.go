package items

import (
	"context"
	"slices"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

func (s *Repository) EngineVehiclesGroupsBatch(
	ctx context.Context, engineIDs []int64, groupJoinLimit int,
) (map[int64][]int64, error) {
	result := make(map[int64][]int64, len(engineIDs))
	if len(engineIDs) == 0 {
		return result, nil
	}

	vehicles, err := s.engineVehiclesForGroups(ctx, engineIDs)
	if err != nil {
		return nil, err
	}

	byEngine := make(map[int64][]int64, len(engineIDs))

	vehicleIDs := make([]int64, 0, len(vehicles))
	for _, row := range vehicles {
		byEngine[row.EngineID] = append(byEngine[row.EngineID], row.ID)
		vehicleIDs = append(vehicleIDs, row.ID)
	}

	if len(vehicleIDs) == 0 {
		return result, nil
	}

	var edges []struct {
		ItemID   int64 `db:"item_id"`
		ParentID int64 `db:"parent_id"`
	}

	err = s.db.Select(schema.ItemParentCacheTableItemIDCol, schema.ItemParentCacheTableParentIDCol).
		From(schema.ItemParentCacheTable).
		Join(schema.ItemTable, goqu.On(schema.ItemParentCacheTableParentIDCol.Eq(schema.ItemTableIDCol))).
		Where(schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDVehicle),
			schema.ItemParentCacheTableItemIDCol.In(vehicleIDs),
			schema.ItemParentCacheTableItemIDCol.Neq(schema.ItemParentCacheTableParentIDCol)).
		Order(schema.ItemParentCacheTableDiffCol.Desc()).ScanStructsContext(ctx, &edges)
	if err != nil {
		return nil, err
	}

	parents := make(map[int64][]int64)
	for _, edge := range edges {
		parents[edge.ItemID] = append(parents[edge.ItemID], edge.ParentID)
	}

	for _, id := range engineIDs {
		if _, ok := result[id]; !ok {
			result[id] = groupEngineVehicles(byEngine[id], parents, groupJoinLimit)
		}
	}

	return result, nil
}

func groupEngineVehicles(vehicleIDs []int64, parents map[int64][]int64, groupJoinLimit int) []int64 {
	vectors := make([][]int64, 0, len(vehicleIDs))

	for _, vehicleID := range vehicleIDs {
		parentIDs := parents[vehicleID]

		// remove parents
		for _, parentID := range parentIDs {
			index := slices.Index(vehicleIDs, parentID)
			if index != -1 {
				vehicleIDs = append(vehicleIDs[:index], vehicleIDs[index+1:]...)
			}
		}

		vector := parentIDs
		vector = append(vector, vehicleID)

		vectors = append(vectors, vector)
	}

	if groupJoinLimit > 0 && len(vehicleIDs) <= groupJoinLimit {
		return vehicleIDs
	}

	for {
		matched := false
		// look for same root
		for i := 0; i < (len(vectors)-1) && !matched; i++ {
			for j := i + 1; j < len(vectors) && !matched; j++ {
				if vectors[i][0] == vectors[j][0] {
					matched = true
					// matched root
					newVector := make([]int64, 0)
					length := min(len(vectors[i]), len(vectors[j]))

					for k := 0; k < length && vectors[i][k] == vectors[j][k]; k++ {
						newVector = append(newVector, vectors[i][k])
					}

					vectors[i] = newVector
					vectors = append(vectors[:j], vectors[j+1:]...)
				}
			}
		}

		if !matched {
			break
		}
	}

	resultIDs := make([]int64, 0, len(vectors))
	for _, vector := range vectors {
		resultIDs = append(resultIDs, vector[len(vector)-1])
	}

	return resultIDs
}

// Keep each engine's original equality selection and traversal order. A single
// IN query can change that order and therefore change the legacy vector grouping.
func (s *Repository) engineVehiclesForGroups(ctx context.Context, engineIDs []int64) ([]engineGroupVehicle, error) {
	var combined *goqu.SelectDataset

	seen := make(map[int64]bool, len(engineIDs))
	for _, id := range engineIDs {
		if seen[id] {
			continue
		}

		seen[id] = true
		selection := s.db.Select(schema.ItemTableIDCol, goqu.V(id).As("engine_id"),
			goqu.ROW_NUMBER().Over(goqu.W()).As("position")).From(schema.ItemTable).
			Join(schema.ItemParentCacheTable, goqu.On(schema.ItemTableEngineItemIDCol.Eq(schema.ItemParentCacheTableItemIDCol))).
			Where(schema.ItemParentCacheTableParentIDCol.Eq(id))

		part := s.db.From(selection.As("selection")).Select(goqu.Star())
		if combined == nil {
			combined = part
		} else {
			combined = combined.UnionAll(part)
		}
	}

	var result []engineGroupVehicle

	err := s.db.From(combined.As("batch")).Select(goqu.Star()).
		Order(goqu.C("engine_id").Asc(), goqu.C("position").Asc()).ScanStructsContext(ctx, &result)

	return result, err
}

type engineGroupVehicle struct {
	EngineID int64 `db:"engine_id"`
	ID       int64 `db:"id"`
	Position int64 `db:"position"`
}
