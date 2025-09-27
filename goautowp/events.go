package goautowp

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

const EventsDefaultLanguage = "en"

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

	res, err := s.db.Insert(schema.LogEventsTable).
		Rows(goqu.Record{
			schema.LogEventsTableDescriptionColName: event.Message,
			schema.LogEventsTableUserIDColName:      event.UserID,
			schema.LogEventsTableAddDatetimeColName: goqu.Func("NOW"),
		}).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	rowID, err := res.LastInsertId()
	if err != nil {
		return err
	}

	if len(event.Users) > 0 {
		event.Users = util.RemoveDuplicate(event.Users)

		rows := make([]interface{}, len(event.Users))
		for idx, id := range event.Users {
			rows[idx] = goqu.Record{
				schema.LogEventsUserTableLogEventIDColName: rowID,
				schema.LogEventsUserTableUserIDColName:     id,
			}
		}

		_, err = s.db.Insert(schema.LogEventsUserTable).Rows(rows...).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	if len(event.Pictures) > 0 {
		event.Pictures = util.RemoveDuplicate(event.Pictures)

		rows := make([]interface{}, len(event.Pictures))
		for idx, id := range event.Pictures {
			rows[idx] = goqu.Record{
				schema.LogEventsPicturesTableLogEventIDColName: rowID,
				schema.LogEventsPicturesTablePictureIDColName:  id,
			}
		}

		_, err = s.db.Insert(schema.LogEventsPicturesTable).
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
				schema.LogEventsItemTableLogEventIDColName: rowID,
				schema.LogEventsItemTableItemIDColName:     id,
			}
		}

		_, err = s.db.Insert(schema.LogEventsItemTable).Rows(rows...).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	return nil
}
