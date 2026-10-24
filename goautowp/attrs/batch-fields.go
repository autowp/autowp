package attrs

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// ValuesCountsByItemIDs returns counts for the requested items; absent items have zero counts.
func (s *Repository) ValuesCountsByItemIDs(ctx context.Context, ids []int64) (map[int64]int32, error) {
	result := make(map[int64]int32, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ItemID int64 `db:"item_id"`
		Count  int32 `db:"count"`
	}

	err := s.db.From(schema.AttrsValuesTable).
		Select(schema.AttrsValuesTableItemIDCol, goqu.COUNT(goqu.Star()).As("count")).
		Where(schema.AttrsValuesTableItemIDCol.In(ids)).GroupBy(schema.AttrsValuesTableItemIDCol).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = row.Count
	}

	return result, nil
}
