package log

import (
	"context"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

const eventsPerPage = 40

type Event struct {
	ID          int64     `db:"id"`
	UserID      int64     `db:"user_id"`
	CreatedAt   time.Time `db:"created_at"`
	Description string    `db:"description"`
	Items       []int64   `db:"-"`
	Pictures    []int64   `db:"-"`
}

type ListOptions struct {
	ArticleID int64
	ItemID    int64
	PictureID int64
	UserID    int64
	Page      uint32
}

type Repository struct {
	db *goqu.Database
}

func NewRepository(db *goqu.Database) *Repository {
	return &Repository{
		db: db,
	}
}

func (s *Repository) Events(
	ctx context.Context,
	options ListOptions,
) ([]Event, *util.Pages, error) {
	sqSelect := s.db.Select(schema.LogEventTableIDCol, schema.LogEventTableUserIDCol,
		schema.LogEventTableCreatedAtCol, schema.LogEventTableDescriptionCol).
		From(schema.LogEventTable).
		Order(schema.LogEventTableCreatedAtCol.Desc(), schema.LogEventTableIDCol.Desc())

	if options.ArticleID != 0 {
		sqSelect = sqSelect.
			Join(schema.LogEventArticleTable,
				goqu.On(schema.LogEventTableIDCol.Eq(schema.LogEventArticleTableLogEventIDCol))).
			Where(schema.LogEventArticleTableArticleIDCol.Eq(options.ArticleID))
	}

	if options.ItemID != 0 {
		sqSelect = sqSelect.
			Join(schema.LogEventItemTable,
				goqu.On(schema.LogEventTableIDCol.Eq(schema.LogEventItemTableLogEventIDCol))).
			Where(schema.LogEventItemTableItemIDCol.Eq(options.ItemID))
	}

	if options.PictureID != 0 {
		sqSelect = sqSelect.
			Join(schema.LogEventPictureTable,
				goqu.On(schema.LogEventTableIDCol.Eq(schema.LogEventPictureTableLogEventIDCol))).
			Where(schema.LogEventPictureTablePictureIDCol.Eq(options.PictureID))
	}

	if options.UserID != 0 {
		sqSelect = sqSelect.Where(schema.LogEventTableUserIDCol.Eq(options.UserID))
	}

	paginator := util.Paginator{
		SQLSelect:         sqSelect,
		ItemCountPerPage:  eventsPerPage,
		CurrentPageNumber: int32(options.Page), //nolint: gosec
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	sqSelect, err = paginator.GetCurrentItems(ctx)
	if err != nil {
		return nil, nil, err
	}

	var rows []Event

	err = sqSelect.ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, nil, err
	}

	for idx, row := range rows {
		err = s.db.Select(schema.LogEventItemTableItemIDCol).
			From(schema.LogEventItemTable).
			Where(schema.LogEventItemTableLogEventIDCol.Eq(row.ID)).
			ScanValsContext(ctx, &rows[idx].Items)
		if err != nil {
			return nil, nil, err
		}

		err = s.db.Select(schema.LogEventPictureTablePictureIDCol).
			From(schema.LogEventPictureTable).
			Where(schema.LogEventPictureTableLogEventIDCol.Eq(row.ID)).
			ScanValsContext(ctx, &rows[idx].Pictures)
		if err != nil {
			return nil, nil, err
		}
	}

	return rows, pages, nil
}
