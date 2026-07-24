package goautowp

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

const EventsDefaultLanguage = "en"

var errNoRowsReturned = errors.New("no rows returned")

type Event struct {
	UserID   int64
	Message  string
	Users    []int64
	Pictures []int64
	Items    []int64
}

type Events struct {
	db *goqu.Database
}

func NewEvents(db *goqu.Database) *Events {
	return &Events{
		db: db,
	}
}

func (s *Events) Add(ctx context.Context, event Event) error {
	ctx = context.WithoutCancel(ctx)

	var rowID int64

	success, err := s.db.Insert(schema.LogEventTable).
		Rows(goqu.Record{
			schema.LogEventTableDescriptionColName: event.Message,
			schema.LogEventTableUserIDColName:      event.UserID,
			schema.LogEventTableCreatedAtColName:   goqu.Func("NOW"),
		}).
		Returning(schema.LogEventTableIDColName).
		Executor().ScanValContext(ctx, &rowID)
	if err != nil {
		return err
	}

	if !success {
		return errNoRowsReturned
	}

	if len(event.Users) > 0 {
		event.Users = util.RemoveDuplicate(event.Users)

		rows := make([]interface{}, len(event.Users))
		for idx, id := range event.Users {
			rows[idx] = goqu.Record{
				schema.LogEventUserTableLogEventIDColName: rowID,
				schema.LogEventUserTableUserIDColName:     id,
			}
		}

		_, err = s.db.Insert(schema.LogEventUserTable).Rows(rows...).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	if len(event.Pictures) > 0 {
		event.Pictures = util.RemoveDuplicate(event.Pictures)

		rows := make([]interface{}, len(event.Pictures))
		for idx, id := range event.Pictures {
			rows[idx] = goqu.Record{
				schema.LogEventPictureTableLogEventIDColName: rowID,
				schema.LogEventPictureTablePictureIDColName:  id,
			}
		}

		_, err = s.db.Insert(schema.LogEventPictureTable).
			Rows(rows...).
			Executor().
			ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	if len(event.Items) > 0 {
		event.Items = util.RemoveDuplicate(event.Items)

		rows := make([]interface{}, len(event.Items))
		for idx, id := range event.Items {
			rows[idx] = goqu.Record{
				schema.LogEventItemTableLogEventIDColName: rowID,
				schema.LogEventItemTableItemIDColName:     id,
			}
		}

		_, err = s.db.Insert(schema.LogEventItemTable).Rows(rows...).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	return nil
}
