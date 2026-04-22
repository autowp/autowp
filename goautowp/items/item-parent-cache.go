package items

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

type ItemParentCacheRepository struct {
	db *goqu.Database
}

func NewItemParentCacheRepository(db *goqu.Database) *ItemParentCacheRepository {
	return &ItemParentCacheRepository{db: db}
}

func (s *ItemParentCacheRepository) RebuildCache(ctx context.Context, itemID int64) (int64, error) {
	parentInfos, err := s.collectParentInfo(ctx, itemID, 1)
	if err != nil {
		return 0, err
	}

	parentInfos[itemID] = parentInfo{
		Diff:   0,
		Tuning: false,
		Sport:  false,
		Design: false,
	}

	var (
		updates int64
		records = make([]goqu.Record, len(parentInfos))
		idx     = 0
	)

	for parentID, info := range parentInfos {
		records[idx] = goqu.Record{
			schema.ItemParentCacheTableItemIDColName:   itemID,
			schema.ItemParentCacheTableParentIDColName: parentID,
			schema.ItemParentCacheTableDiffColName:     info.Diff,
			schema.ItemParentCacheTableTuningColName:   info.Tuning,
			schema.ItemParentCacheTableSportColName:    info.Sport,
			schema.ItemParentCacheTableDesignColName:   info.Design,
		}
		idx++
	}

	ctx = context.WithoutCancel(ctx)

	result, err := s.db.Insert(schema.ItemParentCacheTable).
		Rows(records).
		OnConflict(
			goqu.DoUpdate(schema.ItemParentCacheTableItemIDColName+","+schema.ItemParentCacheTableParentIDColName,
				goqu.Record{
					schema.ItemParentCacheTableDiffColName:   schema.Excluded(schema.ItemParentCacheTableDiffColName),
					schema.ItemParentCacheTableTuningColName: schema.Excluded(schema.ItemParentCacheTableTuningColName),
					schema.ItemParentCacheTableSportColName:  schema.Excluded(schema.ItemParentCacheTableSportColName),
					schema.ItemParentCacheTableDesignColName: schema.Excluded(schema.ItemParentCacheTableDesignColName),
				},
			),
		).
		Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	affected, err := result.RowsAffected()
	if err != nil {
		return 0, err
	}

	updates += affected

	keys := make([]int64, len(parentInfos))

	i := 0

	for k := range parentInfos {
		keys[i] = k
		i++
	}

	_, err = s.db.Delete(schema.ItemParentCacheTable).Where(
		schema.ItemParentCacheTableItemIDCol.Eq(itemID),
		schema.ItemParentCacheTableParentIDCol.NotIn(keys),
	).Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	childs, err := s.getChildItemsIDs(ctx, itemID)
	if err != nil {
		return 0, err
	}

	for _, child := range childs {
		affected, err = s.RebuildCache(ctx, child)
		if err != nil {
			return 0, err
		}

		updates += affected
	}

	return updates, nil
}

func (s *ItemParentCacheRepository) collectParentInfo(
	ctx context.Context,
	id int64,
	diff int64,
) (map[int64]parentInfo, error) {
	//nolint: sqlclosecheck
	rows, err := s.db.Select(schema.ItemParentTableParentIDCol, schema.ItemParentTableTypeCol).
		From(schema.ItemParentTable).
		Where(schema.ItemParentTableItemIDCol.Eq(id)).
		Executor().QueryContext(ctx)
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	result := make(map[int64]parentInfo, 0)

	for rows.Next() {
		var (
			parentID int64
			typeID   schema.ItemParentType
		)

		err = rows.Scan(&parentID, &typeID)
		if err != nil {
			return nil, err
		}

		isTuning := typeID == schema.ItemParentTypeTuning
		isSport := typeID == schema.ItemParentTypeSport
		isDesign := typeID == schema.ItemParentTypeDesign
		result[parentID] = parentInfo{
			Diff:   diff,
			Tuning: isTuning,
			Sport:  isSport,
			Design: isDesign,
		}

		parentInfos, err := s.collectParentInfo(ctx, parentID, diff+1)
		if err != nil {
			return nil, err
		}

		for pid, info := range parentInfos {
			val, ok := result[pid]

			if !ok || info.Diff < val.Diff {
				val = info
				val.Tuning = result[pid].Tuning || isTuning
				val.Sport = result[pid].Sport || isSport
				val.Design = result[pid].Design || isDesign
				result[pid] = val
			}
		}
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return result, nil
}

func (s *ItemParentCacheRepository) getChildItemsIDs(ctx context.Context, parentID int64) ([]int64, error) {
	vals := make([]int64, 0)

	err := s.db.Select(schema.ItemParentTableItemIDCol).
		From(schema.ItemParentTable).
		Where(schema.ItemParentTableParentIDCol.Eq(parentID)).
		Executor().ScanValsContext(ctx, &vals)
	if err != nil {
		return nil, err
	}

	return vals, nil
}
