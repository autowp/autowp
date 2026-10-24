package itemofday

import (
	"context"

	"github.com/autowp/goautowp/schema"
)

func (s *Repository) CompliesBatch(ctx context.Context, ids []int64) (map[int64]bool, error) {
	result := make(map[int64]bool, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	for _, id := range ids {
		if id == 0 {
			return nil, errItemIDMustBeDefined
		}
	}

	var rows []CandidateRecord
	if err := s.CandidateQuery().Where(schema.ItemTableIDCol.In(ids)).ScanStructsContext(ctx, &rows); err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = row.ItemID != 0
	}

	return result, nil
}
