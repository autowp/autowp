package pictures

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// AcceptedCountsByOwnerIDs counts accepted pictures for all requested users in one query.
func (s *Repository) AcceptedCountsByOwnerIDs(ctx context.Context, ids []int64) (map[int64]int32, error) {
	counts := make(map[int64]int32, len(ids))
	if len(ids) == 0 {
		return counts, nil
	}

	var rows []struct {
		OwnerID int64 `db:"owner_id"`
		Count   int64 `db:"count"`
	}

	err := s.db.Select(schema.PictureTableOwnerIDCol, goqu.COUNT(goqu.Star()).As("count")).
		From(schema.PictureTable).
		Where(schema.PictureTableOwnerIDCol.In(ids), schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted)).
		GroupBy(schema.PictureTableOwnerIDCol).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		counts[row.OwnerID] = int32(row.Count) //nolint:gosec
	}

	return counts, nil
}
