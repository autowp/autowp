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
	CreatedAt   time.Time `db:"add_datetime"`
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
	sqSelect := s.db.Select(schema.LogEventsTableIDCol, schema.LogEventsTableUserIDCol,
		schema.LogEventsTableAddDatetimeCol, schema.LogEventsTableDescriptionCol).
		From(schema.LogEventsTable).
		Order(schema.LogEventsTableAddDatetimeCol.Desc(), schema.LogEventsTableIDCol.Desc())

	if options.ArticleID != 0 {
		sqSelect = sqSelect.
			Join(schema.LogEventsArticlesTable,
				goqu.On(schema.LogEventsTableIDCol.Eq(schema.LogEventsArticlesTableLogEventIDCol))).
			Where(schema.LogEventsArticlesTableArticleIDCol.Eq(options.ArticleID))
	}

	if options.ItemID != 0 {
		sqSelect = sqSelect.
			Join(schema.LogEventsItemTable,
				goqu.On(schema.LogEventsTableIDCol.Eq(schema.LogEventsItemTableLogEventIDCol))).
			Where(schema.LogEventsItemTableItemIDCol.Eq(options.ItemID))
	}

	if options.PictureID != 0 {
		sqSelect = sqSelect.
			Join(schema.LogEventsPicturesTable,
				goqu.On(schema.LogEventsTableIDCol.Eq(schema.LogEventsPicturesTableLogEventIDCol))).
			Where(schema.LogEventsPicturesTablePictureIDCol.Eq(options.PictureID))
	}

	if options.UserID != 0 {
		sqSelect = sqSelect.Where(schema.LogEventsTableUserIDCol.Eq(options.UserID))
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
		err = s.db.Select(schema.LogEventsItemTableItemIDCol).
			From(schema.LogEventsItemTable).
			Where(schema.LogEventsItemTableLogEventIDCol.Eq(row.ID)).
			ScanValsContext(ctx, &rows[idx].Items)
		if err != nil {
			return nil, nil, err
		}

		err = s.db.Select(schema.LogEventsPicturesTablePictureIDCol).
			From(schema.LogEventsPicturesTable).
			Where(schema.LogEventsPicturesTableLogEventIDCol.Eq(row.ID)).
			ScanValsContext(ctx, &rows[idx].Pictures)
		if err != nil {
			return nil, nil, err
		}
	}

	return rows, pages, nil
}
