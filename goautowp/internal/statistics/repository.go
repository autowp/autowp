package statistics

import (
	"context"
	"errors"
	"time"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
)

const topUploadersLimit = 20

type PulseGrouping int

const (
	PulseGroupHour PulseGrouping = iota + 1
	PulseGroupDay
	PulseGroupMonth
)

var errFailedToFetchRow = errors.New("failed to fetch row")

type Repository struct {
	db *goqu.Database
}

func NewRepository(db *goqu.Database) *Repository {
	return &Repository{db: db}
}

func (s *Repository) TotalUsers(ctx context.Context) (int64, error) {
	return s.db.From(schema.UserTable).Where(schema.UserTableDeletedCol.IsFalse()).CountContext(ctx)
}

func (s *Repository) TotalItems(ctx context.Context) (int64, error) {
	return s.db.From(schema.ItemTable).CountContext(ctx)
}

func (s *Repository) TotalComments(ctx context.Context) (int64, error) {
	return s.db.From(schema.CommentMessageTable).Where(schema.CommentMessageTableDeletedCol.IsFalse()).CountContext(ctx)
}

func (s *Repository) PictureStats(ctx context.Context) (schema.PictureStatisticsRow, error) {
	var row schema.PictureStatisticsRow

	found, err := s.db.Select(
		goqu.COUNT(goqu.Star()).As("count"),
		goqu.L("SUM(?) / 1024 / 1024", schema.PictureTable.Col(schema.PictureTableFilesizeColName)).As("size"),
	).From(schema.PictureTable).ScanStructContext(ctx, &row)
	if err != nil {
		return schema.PictureStatisticsRow{}, err
	}

	if !found {
		return schema.PictureStatisticsRow{}, errFailedToFetchRow
	}

	return row, nil
}

func (s *Repository) Contributors(ctx context.Context) ([]string, error) {
	contributors := make([]string, 0)

	err := s.db.Select(schema.UserTableIDCol).
		From(schema.UserTable).
		Where(
			schema.UserTableDeletedCol.IsFalse(),
			schema.UserTableGreenCol.IsTrue(),
			goqu.Or(schema.UserTableIdentityCol.IsNull(), schema.UserTableIdentityCol.Neq("autowp")),
			schema.UserTableLastOnlineCol.Gt(goqu.L("CURRENT_DATE - INTERVAL '6 MONTH'")),
		).ScanValsContext(ctx, &contributors)
	if err != nil {
		return nil, err
	}

	picturesUsers := make([]string, 0)

	err = s.db.Select(schema.UserTableIDCol).
		From(schema.UserTable).
		Where(schema.UserTableDeletedCol.IsFalse()).
		Order(schema.UserTablePicturesTotalCol.Desc()).
		Limit(topUploadersLimit).ScanValsContext(ctx, &picturesUsers)
	if err != nil {
		return nil, err
	}

	seen := make(map[string]bool, len(contributors)+len(picturesUsers))
	result := make([]string, 0, len(contributors)+len(picturesUsers))

	for _, id := range append(contributors, picturesUsers...) {
		if !seen[id] {
			seen[id] = true
			result = append(result, id)
		}
	}

	return result, nil
}

func (s *Repository) Pulse(
	ctx context.Context,
	from time.Time,
	to time.Time,
	grouping PulseGrouping,
) ([]schema.StatisticsPulseRow, error) {
	var dateFormat string

	switch grouping {
	case PulseGroupHour:
		dateFormat = "YYYY-MM-DD HH24"
	case PulseGroupDay:
		dateFormat = "YYYY-MM-DD"
	case PulseGroupMonth:
		dateFormat = "YYYY-MM"
	default:
		dateFormat = "YYYY-MM-DD HH24"
	}

	const dateAlias = "date"

	rows := make([]schema.StatisticsPulseRow, 0)

	err := s.db.Select(
		schema.LogEventTableUserIDCol.As("user_id"),
		goqu.Func("TO_CHAR", schema.LogEventTableCreatedAtCol, dateFormat).As(dateAlias),
		goqu.COUNT(goqu.Star()).As("value"),
	).From(schema.LogEventTable).
		Join(schema.UserTable, goqu.On(schema.UserTableIDCol.Eq(schema.LogEventTableUserIDCol))).
		Where(
			schema.LogEventTableCreatedAtCol.Gte(from),
			schema.LogEventTableCreatedAtCol.Lt(to),
			schema.UserTableDeletedCol.IsFalse(),
		).
		GroupBy(schema.LogEventTableUserIDCol, goqu.C(dateAlias)).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	return rows, nil
}
