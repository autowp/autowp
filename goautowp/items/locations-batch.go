package items

import (
	"context"

	"github.com/autowp/goautowp/schema"
)

func (s *Repository) ItemLocations(ctx context.Context, ids []int64) (map[int64]schema.NullPoint, error) {
	result := make(map[int64]schema.NullPoint, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ItemID int64            `db:"item_id"`
		Point  schema.NullPoint `db:"point"`
	}

	err := s.db.From(schema.ItemPointTable).Select(schema.ItemPointTableItemIDCol, schema.ItemPointTablePointCol).
		Where(schema.ItemPointTableItemIDCol.In(ids)).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = row.Point
	}

	return result, nil
}
