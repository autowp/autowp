package pictures

import (
	"context"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

type ModerVoteSummary struct {
	Count int32 `db:"count"`
	Vote  int32 `db:"vote"`
	Voted bool  `db:"voted"`
}

// ModerVoteSummaries returns totals and the current user's participation for a set of pictures.
func (s *Repository) ModerVoteSummaries(
	ctx context.Context, ids []int64, userID int64,
) (map[int64]ModerVoteSummary, error) {
	result := make(map[int64]ModerVoteSummary, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ModerVoteSummary

		PictureID int64 `db:"picture_id"`
	}

	err := s.db.Select(
		schema.PictureModerVoteTablePictureIDCol,
		goqu.COUNT(goqu.Star()).As("count"),
		goqu.SUM(goqu.L("CASE WHEN ? > 0 THEN 1 ELSE -1 END", schema.PictureModerVoteTableVoteCol)).As("vote"),
		goqu.Func("BOOL_OR", schema.PictureModerVoteTableUserIDCol.Eq(userID)).As("voted"),
	).From(schema.PictureModerVoteTable).
		Where(schema.PictureModerVoteTablePictureIDCol.In(ids)).
		GroupBy(schema.PictureModerVoteTablePictureIDCol).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.PictureID] = row.ModerVoteSummary
	}

	return result, nil
}

// ModerVotedPictureIDs looks up only the current user's votes when totals are not needed.
func (s *Repository) ModerVotedPictureIDs(ctx context.Context, ids []int64, userID int64) ([]int64, error) {
	result := make([]int64, 0)
	if len(ids) == 0 || userID == 0 {
		return result, nil
	}

	err := s.db.Select(schema.PictureModerVoteTablePictureIDCol).
		From(schema.PictureModerVoteTable).
		Where(schema.PictureModerVoteTablePictureIDCol.In(ids), schema.PictureModerVoteTableUserIDCol.Eq(userID)).
		ScanValsContext(ctx, &result)

	return result, err
}

// PictureModerVotesBatch applies the same vote filters to all requested pictures in one query.
func (s *Repository) PictureModerVotesBatch(
	ctx context.Context, ids []int64, options *query.PictureModerVoteListOptions,
) ([]*schema.PictureModerVoteRow, error) {
	rows := make([]*schema.PictureModerVoteRow, 0)
	if len(ids) == 0 {
		return rows, nil
	}

	if options == nil {
		options = &query.PictureModerVoteListOptions{}
	}

	err := s.PictureModerVoteSelect(options).
		Where(goqu.T(query.PictureModerVoteAlias).Col(schema.PictureModerVoteTablePictureIDColName).In(ids)).
		ScanStructsContext(ctx, &rows)

	return rows, err
}
