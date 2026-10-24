package pictures

import (
	"context"
	"database/sql"
	"slices"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
	"github.com/lib/pq"
)

type RelatedAcceptedStat struct {
	Count  int64 `db:"count"`
	Others int64 `db:"others"`
}

// RelatedAcceptedStats counts distinct pictures sharing any linked item. All
// picture-item types participate, as in the original Count and Exists filters.
func (s *Repository) RelatedAcceptedStats(ctx context.Context, ids []int64) (map[int64]RelatedAcceptedStat, error) {
	result := make(map[int64]RelatedAcceptedStat, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	source := schema.PictureItemTable.As("source")
	related := schema.PictureItemTable.As("related")
	sourceID := goqu.T("requested").Col(schema.PictureItemTablePictureIDColName)
	pictureID := schema.PictureTableIDCol

	var (
		membership   exp.Expression = source.Col(schema.PictureItemTablePictureIDColName).Eq(sourceID)
		otherPicture exp.Expression = pictureID.Neq(sourceID)
	)
	// Zero is an unset filter in the single-row query builders. Keep that edge
	// case without weakening the indexed join used by ordinary picture IDs.
	if slices.Contains(ids, int64(0)) {
		membership = goqu.Or(sourceID.Eq(0), membership)
		otherPicture = goqu.Or(sourceID.Eq(0), otherPicture)
	}

	var rows []struct {
		RelatedAcceptedStat

		PictureID int64 `db:"picture_id"`
	}

	requested := s.db.Select(goqu.L("unnest(?::bigint[])", pq.Array(ids)).As(schema.PictureItemTablePictureIDColName)).
		As("requested")

	err := s.db.From(requested).
		Join(source, goqu.On(membership)).
		Join(related, goqu.On(
			source.Col(schema.PictureItemTableItemIDColName).Eq(related.Col(schema.PictureItemTableItemIDColName)),
		)).
		Join(schema.PictureTable, goqu.On(related.Col(schema.PictureItemTablePictureIDColName).Eq(pictureID))).
		Select(sourceID, goqu.COUNT(goqu.DISTINCT(pictureID)).As("count"),
			goqu.COUNT(goqu.DISTINCT(goqu.Case().When(otherPicture, pictureID))).
				As("others"),
		).
		Where(schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted)).
		GroupBy(sourceID).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.PictureID] = row.RelatedAcceptedStat
	}

	return result, nil
}

type SiblingIDs struct {
	Prev    sql.NullInt64 `db:"prev"`
	Next    sql.NullInt64 `db:"next"`
	PrevNew sql.NullInt64 `db:"prev_new"`
	NextNew sql.NullInt64 `db:"next_new"`
}

// PictureSiblingIDs performs the four indexed neighbor searches in one round
// trip. The source IDs need not still exist in picture, just like Picture(IDLt/IDGt).
func (s *Repository) PictureSiblingIDs(ctx context.Context, ids []int64) (map[int64]SiblingIDs, error) {
	result := make(map[int64]SiblingIDs, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	source := goqu.T("source")
	id := source.Col(schema.PictureTableIDColName)

	var (
		previousCondition exp.Expression = schema.PictureTableIDCol.Lt(id)
		nextCondition     exp.Expression = schema.PictureTableIDCol.Gt(id)
	)
	if slices.Contains(ids, int64(0)) {
		previousCondition = goqu.Or(id.Eq(0), previousCondition)
		nextCondition = goqu.Or(id.Eq(0), nextCondition)
	}

	before := s.db.From(schema.PictureTable).Select(schema.PictureTableIDCol).
		Where(previousCondition).Order(schema.PictureTableIDCol.Desc()).Limit(1)
	after := s.db.From(schema.PictureTable).Select(schema.PictureTableIDCol).
		Where(nextCondition).Order(schema.PictureTableIDCol.Asc()).Limit(1)

	var rows []struct {
		SiblingIDs

		ID int64 `db:"id"`
	}

	requested := s.db.Select(
		goqu.L("unnest(?::bigint[])", pq.Array(ids)).As(schema.PictureTableIDColName),
	).As("source")

	err := s.db.From(requested).
		Select(id, before.As("prev"), after.As("next"),
			before.Where(schema.PictureTableStatusCol.Eq(schema.PictureStatusInbox)).As("prev_new"),
			after.Where(schema.PictureTableStatusCol.Eq(schema.PictureStatusInbox)).
				As("next_new"),
		).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ID] = row.SiblingIDs
	}

	return result, nil
}

func (s *Repository) PictureAuthorSuggestionsBatch(
	ctx context.Context,
	ids []int64,
) ([]schema.PictureAuthorSuggestionRow, error) {
	rows := make([]schema.PictureAuthorSuggestionRow, 0)
	if len(ids) == 0 {
		return rows, nil
	}

	err := s.db.From(schema.PictureAuthorSuggestionTable).
		Select(schema.PictureAuthorSuggestionTablePictureIDCol, schema.PictureAuthorSuggestionTableItemIDCol,
			schema.PictureAuthorSuggestionTableSourceCol, schema.PictureAuthorSuggestionTableRawValueCol).
		Where(schema.PictureAuthorSuggestionTablePictureIDCol.In(ids)).
		Order(schema.PictureAuthorSuggestionTableItemIDCol.Asc()).
		ScanStructsContext(ctx, &rows)

	return rows, err
}
