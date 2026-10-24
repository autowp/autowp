package goautowp

import (
	"context"

	eventlog "github.com/autowp/goautowp/log"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

func legacyLogEvents(
	ctx context.Context, db *goqu.Database,
	options eventlog.ListOptions,
) ([]eventlog.Event, *util.Pages, error) {
	sqSelect := db.Select(schema.LogEventTableIDCol, schema.LogEventTableUserIDCol,
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
		ItemCountPerPage:  40,
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

	var rows []eventlog.Event

	err = sqSelect.ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, nil, err
	}

	for idx, row := range rows {
		err = db.Select(schema.LogEventItemTableItemIDCol).
			From(schema.LogEventItemTable).
			Where(schema.LogEventItemTableLogEventIDCol.Eq(row.ID)).
			ScanValsContext(ctx, &rows[idx].Items)
		if err != nil {
			return nil, nil, err
		}

		err = db.Select(schema.LogEventPictureTablePictureIDCol).
			From(schema.LogEventPictureTable).
			Where(schema.LogEventPictureTableLogEventIDCol.Eq(row.ID)).
			ScanValsContext(ctx, &rows[idx].Pictures)
		if err != nil {
			return nil, nil, err
		}
	}

	return rows, pages, nil
}
