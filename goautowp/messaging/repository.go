package messaging

import (
	"context"
	"database/sql"
	"errors"
	"strings"
	"time"

	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/nicksnyder/go-i18n/v2/i18n"
)

var (
	errMessageIsEmpty = errors.New("message is empty")
	errTooLongMessage = errors.New("too long message")
)

type Options struct {
	AllMessagesLink bool
}

const (
	MaxText         = 2000
	MessagesPerPage = 20
)

type Repository struct {
	db                    *goqu.Database
	createMessageCallback CreateMessageCallback
	i18n                  *i18nbundle.I18n
}

type Message struct {
	ID               int64
	AuthorID         *int64
	Text             string
	IsNew            bool
	CanDelete        bool
	Date             time.Time
	CanReply         bool
	DialogCount      int32
	AllMessagesLink  bool
	ToUserID         int64
	DialogWithUserID int64
}

type CreateMessageCallback func(ctx context.Context, fromUserID int64, toUserID int64, text string) error

func NewRepository(
	db *goqu.Database,
	createMessageCallback CreateMessageCallback,
	i18n *i18nbundle.I18n,
) *Repository {
	return &Repository{
		db:                    db,
		createMessageCallback: createMessageCallback,
		i18n:                  i18n,
	}
}

func (s *Repository) GetUserNewMessagesCount(ctx context.Context, userID int64) (int32, error) {
	paginator := util.Paginator{
		SQLSelect: s.getReceivedSelect(userID).
			Where(schema.PersonalMessagesTableReadenCol.IsNotTrue()),
	}

	return paginator.GetTotalItemCount(ctx)
}

func (s *Repository) GetInboxCount(ctx context.Context, userID int64) (int32, error) {
	paginator := util.Paginator{
		SQLSelect: s.getInboxSelect(userID),
	}

	return paginator.GetTotalItemCount(ctx)
}

func (s *Repository) GetInboxNewCount(ctx context.Context, userID int64) (int32, error) {
	paginator := util.Paginator{
		SQLSelect: s.getInboxSelect(userID).
			Where(schema.PersonalMessagesTableReadenCol.IsNotTrue()),
	}

	return paginator.GetTotalItemCount(ctx)
}

func (s *Repository) GetSentCount(ctx context.Context, userID int64) (int32, error) {
	paginator := util.Paginator{
		SQLSelect: s.getSentSelect(userID),
	}

	return paginator.GetTotalItemCount(ctx)
}

func (s *Repository) GetSystemCount(ctx context.Context, userID int64) (int32, error) {
	paginator := util.Paginator{
		SQLSelect: s.getSystemSelect(userID),
	}

	return paginator.GetTotalItemCount(ctx)
}

func (s *Repository) GetSystemNewCount(ctx context.Context, userID int64) (int32, error) {
	paginator := util.Paginator{
		SQLSelect: s.getSystemSelect(userID).
			Where(schema.PersonalMessagesTableReadenCol.IsNotTrue()),
	}

	return paginator.GetTotalItemCount(ctx)
}

func (s *Repository) GetDialogCount(
	ctx context.Context,
	userID int64,
	withUserID int64,
) (int32, error) {
	paginator := util.Paginator{
		SQLSelect: s.getDialogSelect(userID, withUserID),
	}

	return paginator.GetTotalItemCount(ctx)
}

func (s *Repository) DeleteMessage(ctx context.Context, userID int64, messageID int64) error {
	ctx = context.WithoutCancel(ctx)

	_, err := s.db.Update(schema.PersonalMessagesTable).
		Set(goqu.Record{schema.PersonalMessagesTableDeletedByFromColName: true}).
		Where(
			schema.PersonalMessagesTableFromUserIDCol.Eq(userID),
			schema.PersonalMessagesTableIDCol.Eq(messageID),
		).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	_, err = s.db.Update(schema.PersonalMessagesTable).
		Set(goqu.Record{schema.PersonalMessagesTableDeletedByToColName: true}).
		Where(
			schema.PersonalMessagesTableToUserIDCol.Eq(userID),
			schema.PersonalMessagesTableIDCol.Eq(messageID),
		).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) ClearSent(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.PersonalMessagesTable).
		Set(goqu.Record{schema.PersonalMessagesTableDeletedByFromColName: true}).
		Where(schema.PersonalMessagesTableFromUserIDCol.Eq(userID)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) ClearSystem(ctx context.Context, userID int64) error {
	_, err := s.db.Delete(schema.PersonalMessagesTable).
		Where(
			schema.PersonalMessagesTableToUserIDCol.Eq(userID),
			schema.PersonalMessagesTableFromUserIDCol.IsNull(),
		).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) CreateMessageFromTemplate(
	ctx context.Context,
	fromUserID int64,
	toUserID int64,
	messageID string,
	templateData map[string]interface{},
	lang string,
) error {
	localizer := s.i18n.Localizer(lang)

	text, err := localizer.Localize(&i18n.LocalizeConfig{
		DefaultMessage: &i18n.Message{ID: messageID},
		TemplateData:   templateData,
	})
	if err != nil {
		return err
	}

	return s.CreateMessage(ctx, fromUserID, toUserID, text)
}

func (s *Repository) CreateMessage(
	ctx context.Context,
	fromUserID int64,
	toUserID int64,
	text string,
) error {
	text = strings.TrimSpace(text)
	msgLength := len(text)

	if msgLength <= 0 {
		return errMessageIsEmpty
	}

	if msgLength > MaxText {
		return errTooLongMessage
	}

	nullableFromUserID := sql.NullInt64{Int64: fromUserID, Valid: fromUserID != 0}

	ctx = context.WithoutCancel(ctx)

	_, err := s.db.Insert(schema.PersonalMessagesTable).Rows(
		goqu.Record{
			schema.PersonalMessagesTableFromUserIDColName:  nullableFromUserID,
			schema.PersonalMessagesTableToUserIDColName:    toUserID,
			schema.PersonalMessagesTableContentsColName:    text,
			schema.PersonalMessagesTableAddDatetimeColName: goqu.Func("NOW"),
			schema.PersonalMessagesTableReadenColName:      false,
		},
	).Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return s.createMessageCallback(ctx, fromUserID, toUserID, text)
}

func (s *Repository) GetInbox(
	ctx context.Context,
	userID int64,
	page int32,
) ([]Message, *util.Pages, error) {
	paginator := util.Paginator{
		SQLSelect:         s.getInboxSelect(userID),
		ItemCountPerPage:  MessagesPerPage,
		CurrentPageNumber: page,
	}

	return s.getBox(ctx, userID, paginator, Options{AllMessagesLink: true})
}

func (s *Repository) GetSentbox(
	ctx context.Context,
	userID int64,
	page int32,
) ([]Message, *util.Pages, error) {
	paginator := util.Paginator{
		SQLSelect:         s.getSentSelect(userID),
		ItemCountPerPage:  MessagesPerPage,
		CurrentPageNumber: page,
	}

	return s.getBox(ctx, userID, paginator, Options{AllMessagesLink: true})
}

func (s *Repository) GetSystembox(
	ctx context.Context,
	userID int64,
	page int32,
) ([]Message, *util.Pages, error) {
	paginator := util.Paginator{
		SQLSelect:         s.getSystemSelect(userID),
		ItemCountPerPage:  MessagesPerPage,
		CurrentPageNumber: page,
	}

	return s.getBox(ctx, userID, paginator, Options{AllMessagesLink: false})
}

func (s *Repository) GetDialogbox(
	ctx context.Context,
	userID int64,
	withUserID int64,
	page int32,
) ([]Message, *util.Pages, error) {
	paginator := util.Paginator{
		SQLSelect:         s.getDialogSelect(userID, withUserID),
		ItemCountPerPage:  MessagesPerPage,
		CurrentPageNumber: page,
	}

	return s.getBox(ctx, userID, paginator, Options{AllMessagesLink: false})
}

func (s *Repository) Recycle(ctx context.Context) (int64, error) {
	res, err := s.db.Delete(schema.PersonalMessagesTable).Where(
		schema.PersonalMessagesTableDeletedByToCol.IsTrue(),
		goqu.Or(
			schema.PersonalMessagesTableDeletedByFromCol.IsTrue(),
			schema.PersonalMessagesTableFromUserIDCol.IsNull(),
		),
	).Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	return res.RowsAffected()
}

func (s *Repository) RecycleSystem(ctx context.Context) (int64, error) {
	res, err := s.db.Delete(schema.PersonalMessagesTable).Where(
		schema.PersonalMessagesTableFromUserIDCol.IsNull(),
		schema.PersonalMessagesTableAddDatetimeCol.Lt(
			goqu.L("NOW() - INTERVAL '6 MONTH'"),
		),
	).Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	return res.RowsAffected()
}

func (s *Repository) markReaden(ctx context.Context, ids []int64) error {
	var err error
	if len(ids) > 0 {
		_, err = s.db.Update(schema.PersonalMessagesTable).
			Set(goqu.Record{schema.PersonalMessagesTableReadenColName: true}).
			Where(schema.PersonalMessagesTableIDCol.In(ids)).
			Executor().ExecContext(ctx)
	}

	return err
}

func (s *Repository) markReadenRows(
	ctx context.Context,
	rows []schema.PersonalMessageRow,
	userID int64,
) error {
	ids := make([]int64, 0)

	for _, msg := range rows {
		if (!msg.Readen) && (msg.ToUserID == userID) {
			ids = append(ids, msg.ID)
		}
	}

	return s.markReaden(ctx, ids)
}

func (s *Repository) getBox(
	ctx context.Context,
	userID int64,
	paginator util.Paginator,
	options Options,
) ([]Message, *util.Pages, error) {
	ds, err := paginator.GetCurrentItems(ctx)
	if err != nil {
		return nil, nil, err
	}

	var msgs []schema.PersonalMessageRow

	err = ds.ScanStructsContext(ctx, &msgs)
	if err != nil {
		return nil, nil, err
	}

	if userID > 0 {
		err = s.markReadenRows(ctx, msgs, userID)
		if err != nil {
			return nil, nil, err
		}
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	list, err := s.prepareList(ctx, userID, msgs, options)
	if err != nil {
		return nil, nil, err
	}

	return list, pages, nil
}

func (s *Repository) getReceivedSelect(userID int64) *goqu.SelectDataset {
	return s.db.From(schema.PersonalMessagesTable).
		Where(
			schema.PersonalMessagesTableToUserIDCol.Eq(userID),
			schema.PersonalMessagesTableDeletedByToCol.IsFalse(),
		).
		Order(schema.PersonalMessagesTableAddDatetimeCol.Desc())
}

func (s *Repository) getSystemSelect(userID int64) *goqu.SelectDataset {
	return s.getReceivedSelect(userID).Where(schema.PersonalMessagesTableFromUserIDCol.IsNull())
}

func (s *Repository) getInboxSelect(userID int64) *goqu.SelectDataset {
	return s.getReceivedSelect(userID).Where(schema.PersonalMessagesTableFromUserIDCol.IsNotNull())
}

func (s *Repository) getSentSelect(userID int64) *goqu.SelectDataset {
	return s.db.From(schema.PersonalMessagesTable).
		Where(
			schema.PersonalMessagesTableFromUserIDCol.Eq(userID),
			schema.PersonalMessagesTableDeletedByFromCol.IsNotTrue(),
		).
		Order(schema.PersonalMessagesTableAddDatetimeCol.Desc())
}

func (s *Repository) getDialogSelect(userID int64, withUserID int64) *goqu.SelectDataset {
	return s.db.From(schema.PersonalMessagesTable).
		Where(
			goqu.Or(
				goqu.And(
					schema.PersonalMessagesTableFromUserIDCol.Eq(userID),
					schema.PersonalMessagesTableToUserIDCol.Eq(withUserID),
					schema.PersonalMessagesTableDeletedByFromCol.IsNotTrue(),
				),
				goqu.And(
					schema.PersonalMessagesTableFromUserIDCol.Eq(withUserID),
					schema.PersonalMessagesTableToUserIDCol.Eq(userID),
					schema.PersonalMessagesTableDeletedByToCol.IsNotTrue(),
				),
			),
		).
		Order(schema.PersonalMessagesTableAddDatetimeCol.Desc())
}

func (s *Repository) prepareList(
	ctx context.Context,
	userID int64,
	rows []schema.PersonalMessageRow,
	options Options,
) ([]Message, error) {
	var err error

	cache := make(map[int64]int32)

	messages := make([]Message, len(rows))

	for idx, msg := range rows {
		isNew := msg.ToUserID == userID && !msg.Readen
		canDelete := msg.FromUserID.Valid && msg.FromUserID.Int64 == userID ||
			msg.ToUserID == userID
		authorIsMe := msg.FromUserID.Valid && msg.FromUserID.Int64 == userID
		canReply := msg.FromUserID.Valid && !authorIsMe //  && ! $author['deleted']

		var dialogWithUserID int64

		if msg.ToUserID == userID {
			if msg.FromUserID.Valid {
				dialogWithUserID = msg.FromUserID.Int64
			}
		} else {
			dialogWithUserID = msg.ToUserID
		}

		var dialogCount int32

		if options.AllMessagesLink && dialogWithUserID != 0 {
			var (
				ok bool
				id = dialogWithUserID
			)

			if dialogCount, ok = cache[id]; !ok {
				dialogCount, err = s.GetDialogCount(ctx, userID, id)
				if err != nil {
					return messages, err
				}

				cache[id] = dialogCount
			}
		}

		messages[idx] = Message{
			ID:               msg.ID,
			AuthorID:         util.SQLNullInt64ToPtr(msg.FromUserID),
			Text:             msg.Contents,
			IsNew:            isNew,
			CanDelete:        canDelete,
			Date:             msg.AddDatetime,
			CanReply:         canReply,
			DialogCount:      dialogCount,
			AllMessagesLink:  options.AllMessagesLink,
			ToUserID:         msg.ToUserID,
			DialogWithUserID: dialogWithUserID,
		}
	}

	return messages, nil
}
