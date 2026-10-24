package pictures

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// ModerVoteCounts follows the exact predicates used by CanAccept and CanDelete.
// Only zero votes prevent acceptance; only positive votes prevent deletion.
type ModerVoteCounts struct {
	Negative int64 `db:"negative"`
	Positive int64 `db:"positive"`
}

func (s *Repository) ModerVoteCountsBatch(ctx context.Context, ids []int64) (map[int64]ModerVoteCounts, error) {
	result := make(map[int64]ModerVoteCounts, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ModerVoteCounts

		PictureID int64 `db:"picture_id"`
	}

	err := s.db.From(schema.PictureModerVoteTable).
		Select(schema.PictureModerVoteTablePictureIDCol,
			goqu.COUNT(goqu.Case().When(schema.PictureModerVoteTableVoteCol.Eq(0), 1)).As("negative"),
			goqu.COUNT(goqu.Case().When(schema.PictureModerVoteTableVoteCol.Gt(0), 1)).As("positive")).
		Where(schema.PictureModerVoteTablePictureIDCol.In(ids)).
		GroupBy(schema.PictureModerVoteTablePictureIDCol).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.PictureID] = row.ModerVoteCounts
	}

	return result, nil
}
