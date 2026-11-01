package forums

import (
	"context"
	"database/sql"
	"errors"
	"fmt"

	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/util"
	"github.com/doug-martin/goqu/v9"
	"golang.org/x/sync/errgroup"
)

var (
	errNoRowsReturned                        = errors.New("no rows returned")
	errTopicsInThemeDisabled                 = errors.New("topics in this theme is disabled")
	errCantDeleteTopicWithModeratorAttention = errors.New(
		"cannot delete topic with moderator attention requirement",
	)
)

var visibleTopicStatuses = []string{
	string(schema.ForumsTopicStatusNormal),
	string(schema.ForumsTopicStatusClosed),
}

type Repository struct {
	db                 *goqu.Database
	commentsRepository *comments.Repository
}

func NewRepository(db *goqu.Database, commentsRepository *comments.Repository) *Repository {
	return &Repository{
		db:                 db,
		commentsRepository: commentsRepository,
	}
}

func (s *Repository) GetUserSummary(ctx context.Context, userID int64) (int, error) {
	result, err := s.db.From(schema.ForumsTopicsTable).
		Join(
			schema.CommentTopicSubscribeTable,
			goqu.On(schema.ForumsTopicsTableIDCol.Eq(schema.CommentTopicSubscribeTableItemIDCol)),
		).
		Join(
			schema.CommentTopicTable,
			goqu.On(schema.ForumsTopicsTableIDCol.Eq(schema.CommentTopicTableItemIDCol)),
		).
		Where(
			schema.CommentTopicSubscribeTableUserIDCol.Eq(userID),
			schema.CommentTopicTableTypeIDCol.Eq(schema.CommentMessageTypeIDForums),
			schema.CommentTopicSubscribeTableTypeIDCol.Eq(schema.CommentMessageTypeIDForums),
		).CountContext(ctx)
	if err != nil {
		return 0, err
	}

	return int(result), nil
}

func (s *Repository) AddTopic(
	ctx context.Context,
	themeID int64,
	name string,
	userID int64,
	remoteAddr string,
) (int64, error) {
	var disableTopics bool

	success, err := s.db.Select(schema.ForumsThemesTableDisableTopicsCol).
		From(schema.ForumsThemesTable).
		Where(schema.ForumsThemesTableIDCol.Eq(themeID)).
		ScanValContext(ctx, &disableTopics)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	if disableTopics {
		return 0, errTopicsInThemeDisabled
	}

	ctx = context.WithoutCancel(ctx)

	var topicID int64

	success, err = s.db.Insert(schema.ForumsTopicsTable).
		Cols(schema.ForumsTopicsTableThemeIDColName, schema.ForumsTopicsTableNameColName,
			schema.ForumsTopicsTableAuthorIDColName, schema.ForumsTopicsTableAuthorIPColName,
			schema.ForumsTopicsTableCreatedAtColName, schema.ForumsTopicsTableViewsColName,
			schema.ForumsTopicsTableStatusColName).
		Vals(goqu.Vals{
			themeID,
			name,
			userID,
			goqu.Func("INET", remoteAddr),
			goqu.Func("NOW"),
			0,
			schema.ForumsTopicStatusNormal,
		}).
		Returning(schema.ForumsTopicsTableIDCol).
		Executor().ScanValContext(ctx, &topicID)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, errNoRowsReturned
	}

	err = s.updateThemeStat(ctx, themeID)
	if err != nil {
		return 0, err
	}

	return topicID, nil
}

func (s *Repository) Close(ctx context.Context, id int64) error {
	return s.setStatus(ctx, id, string(schema.ForumsTopicStatusClosed))
}

func (s *Repository) Open(ctx context.Context, id int64) error {
	_, err := s.db.Update(schema.ForumsTopicsTable).
		Set(goqu.Record{
			schema.ForumsTopicsTableStatusColName:    string(schema.ForumsTopicStatusNormal),
			schema.ForumsTopicsTableDeletedAtColName: nil,
		}).
		Where(schema.ForumsTopicsTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) Delete(ctx context.Context, id int64) error {
	var themeID int64

	success, err := s.db.Select(schema.ForumsTopicsTableThemeIDCol).
		From(schema.ForumsTopicsTable).
		Where(schema.ForumsTopicsTableIDCol.Eq(id)).
		ScanValContext(ctx, &themeID)
	if err != nil {
		return err
	}

	if !success {
		return sql.ErrNoRows
	}

	var needAttention bool

	success, err = s.db.Select(goqu.L("1")).
		From(schema.CommentMessageTable).
		Where(
			schema.CommentMessageTableItemIDCol.Eq(id),
			schema.CommentMessageTableTypeIDCol.Eq(schema.CommentMessageTypeIDForums),
			schema.CommentMessageTableModeratorAttentionCol.Eq(schema.CommentMessageModeratorAttentionRequired),
		).
		ScanValContext(ctx, &needAttention)
	if err != nil {
		return err
	}

	if success {
		return errCantDeleteTopicWithModeratorAttention
	}

	ctx = context.WithoutCancel(ctx)

	_, err = s.db.Update(schema.ForumsTopicsTable).
		Set(goqu.Record{
			schema.ForumsTopicsTableStatusColName:    string(schema.ForumsTopicStatusDeleted),
			schema.ForumsTopicsTableDeletedAtColName: goqu.Func("NOW"),
		}).
		Where(schema.ForumsTopicsTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return s.updateThemeStat(ctx, themeID)
}

func (s *Repository) MoveTopic(ctx context.Context, id int64, themeID int64) error {
	var oldThemeID int64

	success, err := s.db.Select(schema.ForumsTopicsTableThemeIDCol).
		From(schema.ForumsTopicsTable).
		Where(schema.ForumsTopicsTableIDCol.Eq(id)).
		ScanValContext(ctx, &oldThemeID)
	if err != nil {
		return err
	}

	if !success {
		return sql.ErrNoRows
	}

	ctx = context.WithoutCancel(ctx)

	_, err = s.db.Update(schema.ForumsTopicsTable).
		Set(goqu.Record{
			schema.ForumsTopicsTableThemeIDColName: themeID,
		}).
		Where(schema.ForumsTopicsTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	err = s.updateThemeStat(ctx, themeID)
	if err != nil {
		return err
	}

	return s.updateThemeStat(ctx, oldThemeID)
}

func (s *Repository) Theme(ctx context.Context, themeID int64, isModerator bool) (*schema.ForumsThemeRow, error) {
	sqSelect := s.db.Select(
		schema.ForumsThemesTableIDCol,
		schema.ForumsThemesTableNameCol,
		schema.ForumsThemesTableTopicsCol,
		schema.ForumsThemesTableMessagesCol,
		schema.ForumsThemesTableDisableTopicsCol,
		schema.ForumsThemesTableDescriptionCol,
	).
		From(schema.ForumsThemesTable).
		Where(schema.ForumsThemesTableIDCol.Eq(themeID))

	if !isModerator {
		sqSelect = sqSelect.Where(schema.ForumsThemesTableIsModeratorCol.IsFalse())
	}

	var row schema.ForumsThemeRow

	success, err := sqSelect.ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, nil //nolint:nilnil
	}

	return &row, nil
}

func (s *Repository) Themes(
	ctx context.Context,
	themeID int64,
	isModerator bool,
) ([]*schema.ForumsThemeRow, error) {
	sqSelect := s.db.Select(
		schema.ForumsThemesTableIDCol,
		schema.ForumsThemesTableNameCol,
		schema.ForumsThemesTableTopicsCol,
		schema.ForumsThemesTableMessagesCol,
		schema.ForumsThemesTableDisableTopicsCol,
		schema.ForumsThemesTableDescriptionCol,
	).
		From(schema.ForumsThemesTable).Order(schema.ForumsThemesTablePositionCol.Asc())

	if themeID > 0 {
		sqSelect = sqSelect.Where(schema.ForumsThemesTableParentIDCol.Eq(themeID))
	} else {
		sqSelect = sqSelect.Where(schema.ForumsThemesTableParentIDCol.IsNull())
	}

	if !isModerator {
		sqSelect = sqSelect.Where(schema.ForumsThemesTableIsModeratorCol.IsFalse())
	}

	rows := make([]*schema.ForumsThemeRow, 0)

	err := sqSelect.ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	return rows, nil
}

func (s *Repository) Topic(
	ctx context.Context,
	topicID int64,
	userID int64,
	isModerator bool,
) (*schema.ForumsTopicRow, error) {
	sqSelect := s.topicsSelect(isModerator).
		Where(schema.ForumsTopicsTableIDCol.Eq(topicID)).
		Limit(1)

	topic := schema.ForumsTopicRow{}

	success, err := sqSelect.ScanStructContext(ctx, &topic)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, nil //nolint:nilnil
	}

	err = s.prepareTopic(ctx, &topic, userID)
	if err != nil {
		return nil, err
	}

	return &topic, nil
}

func (s *Repository) LastTopic(
	ctx context.Context,
	themeID int64,
	userID int64,
	isModerator bool,
) (*schema.ForumsTopicRow, error) {
	sqSelect := s.topicsSelect(isModerator).
		Join(
			schema.ForumsThemeParentTable,
			goqu.On(schema.ForumsTopicsTableThemeIDCol.Eq(schema.ForumsThemeParentTableForumThemeIDCol)),
		).
		Where(schema.ForumsThemeParentTableParentIDCol.Eq(themeID)).
		Order(schema.CommentTopicTableLastUpdateCol.Desc()).
		Limit(1)

	topic := schema.ForumsTopicRow{}

	success, err := sqSelect.ScanStructContext(ctx, &topic)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, nil //nolint:nilnil
	}

	err = s.prepareTopic(ctx, &topic, userID)
	if err != nil {
		return nil, err
	}

	return &topic, nil
}

func (s *Repository) LastMessage(
	ctx context.Context,
	topicID int64,
	isModerator bool,
	canViewIP bool,
) (*schema.CommentMessageRow, error) {
	columns := []interface{}{
		schema.CommentMessageTableIDCol, schema.CommentMessageTableTypeIDCol,
		schema.CommentMessageTableItemIDCol, schema.CommentMessageTableParentIDCol,
		schema.CommentMessageTableDatetimeCol, schema.CommentMessageTableDeletedCol,
		schema.CommentMessageTableModeratorAttentionCol, schema.CommentMessageTableAuthorIDCol,
	}

	if canViewIP {
		columns = append(columns, schema.CommentMessageTableIPCol)
	}

	sqSelect := s.db.Select(columns...).
		From(schema.CommentMessageTable).
		Join(
			schema.ForumsTopicsTable,
			goqu.On(schema.CommentMessageTableItemIDCol.Eq(schema.ForumsTopicsTableIDCol)),
		).
		Join(
			schema.ForumsThemeParentTable,
			goqu.On(schema.ForumsTopicsTableThemeIDCol.Eq(schema.ForumsThemeParentTableForumThemeIDCol)),
		).
		Where(
			schema.ForumsTopicsTableStatusCol.In(visibleTopicStatuses),
			schema.ForumsTopicsTableIDCol.Eq(topicID),
			schema.CommentMessageTableTypeIDCol.Eq(schema.CommentMessageTypeIDForums),
		).
		Order(schema.CommentMessageTableDatetimeCol.Desc()).
		Limit(1)

	if !isModerator {
		sqSelect = sqSelect.
			Join(
				schema.ForumsThemesTable,
				goqu.On(schema.ForumsThemeParentTableParentIDCol.Eq(schema.ForumsThemesTableIDCol)),
			).
			Where(schema.ForumsThemesTableIsModeratorCol.IsFalse())
	}

	row := schema.CommentMessageRow{}

	success, err := sqSelect.ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, nil //nolint:nilnil
	}

	return &row, nil
}

func (s *Repository) Topics(
	ctx context.Context,
	themeID int64, userID int64, isModerator bool, subscription bool, page int32,
) ([]*schema.ForumsTopicRow, *util.Pages, error) {
	sqSelect := s.topicsSelect(isModerator).
		Order(schema.CommentTopicTableLastUpdateCol.Desc())

	if themeID > 0 {
		sqSelect = sqSelect.Where(schema.ForumsTopicsTableThemeIDCol.Eq(themeID))
	}

	if subscription {
		sqSelect = sqSelect.Join(
			schema.CommentTopicSubscribeTable,
			goqu.On(
				schema.ForumsTopicsTableIDCol.Eq(schema.CommentTopicSubscribeTableItemIDCol),
				schema.CommentTopicSubscribeTableTypeIDCol.Eq(schema.CommentMessageTypeIDForums),
			),
		).
			Where(schema.CommentTopicSubscribeTableUserIDCol.Eq(userID))
	}

	rows := make([]*schema.ForumsTopicRow, 0)

	paginator := util.Paginator{
		SQLSelect:         sqSelect,
		CurrentPageNumber: page,
		ItemCountPerPage:  util.DefaultItemCountPerPage,
	}

	sqSelect, err := paginator.GetCurrentItems(ctx)
	if err != nil {
		return nil, nil, err
	}

	err = sqSelect.ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, nil, err
	}

	if err := s.prepareTopics(ctx, rows, userID); err != nil {
		return nil, nil, err
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	return rows, pages, nil
}

const (
	deletedTopicsRetentionDays = 30
	deletedTopicsPurgeBatch    = 1000
)

// PurgeDeletedTopics permanently removes topics (and their comments) that have sat in the
// soft-deleted state for longer than deletedTopicsRetentionDays. Returns how many were removed.
func (s *Repository) PurgeDeletedTopics(ctx context.Context) (int64, error) {
	var ids []int64

	err := s.db.Select(schema.ForumsTopicsTableIDCol).
		From(schema.ForumsTopicsTable).
		Where(
			schema.ForumsTopicsTableStatusCol.Eq(string(schema.ForumsTopicStatusDeleted)),
			schema.ForumsTopicsTableDeletedAtCol.IsNotNull(),
			schema.ForumsTopicsTableDeletedAtCol.Lt(
				goqu.L("NOW() - INTERVAL ?", fmt.Sprintf("%d DAYS", deletedTopicsRetentionDays)),
			),
		).
		Limit(deletedTopicsPurgeBatch).
		ScanValsContext(ctx, &ids)
	if err != nil {
		return 0, err
	}

	for _, id := range ids {
		ctx := context.WithoutCancel(ctx)

		err = s.commentsRepository.DeleteTopic(ctx, schema.CommentMessageTypeIDForums, id)
		if err != nil {
			return 0, err
		}

		_, err = s.db.Delete(schema.ForumsTopicsTable).
			Where(schema.ForumsTopicsTableIDCol.Eq(id)).
			Executor().ExecContext(ctx)
		if err != nil {
			return 0, err
		}
	}

	return int64(len(ids)), nil
}

func (s *Repository) updateThemeStat(ctx context.Context, themeID int64) error {
	topicsSelect := s.db.Select(goqu.COUNT(goqu.Star())).
		From(schema.ForumsTopicsTable).
		Join(
			schema.ForumsThemeParentTable,
			goqu.On(schema.ForumsTopicsTableThemeIDCol.Eq(schema.ForumsThemeParentTableForumThemeIDCol)),
		).
		Where(
			schema.ForumsThemeParentTableParentIDCol.Eq(schema.ForumsThemesTableIDCol),
			schema.ForumsTopicsTableStatusCol.In(visibleTopicStatuses),
		)

	messagesSelect := topicsSelect.
		Join(
			schema.CommentMessageTable,
			goqu.On(schema.ForumsTopicsTableIDCol.Eq(schema.CommentMessageTableItemIDCol)),
		).
		Where(schema.CommentMessageTableTypeIDCol.Eq(schema.CommentMessageTypeIDForums))

	_, err := s.db.Update(schema.ForumsThemesTable).Set(goqu.Record{
		schema.ForumsThemesTableTopicsColName:   topicsSelect,
		schema.ForumsThemesTableMessagesColName: messagesSelect,
	}).
		Where(schema.ForumsThemesTableIDCol.Eq(themeID)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) setStatus(ctx context.Context, id int64, status string) error {
	_, err := s.db.Update(schema.ForumsTopicsTable).
		Set(goqu.Record{schema.ForumsTopicsTableStatusColName: status}).
		Where(schema.ForumsTopicsTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) prepareTopics(ctx context.Context, topics []*schema.ForumsTopicRow, userID int64) error {
	if len(topics) == 0 {
		return nil
	}

	ids := make([]int64, 0, len(topics))
	for _, topic := range topics {
		ids = append(ids, topic.ID)
	}

	var (
		stats         map[int64]comments.TopicStat
		subscriptions map[int64]bool
	)

	lookups := []func(context.Context) error{
		func(ctx context.Context) error {
			var err error

			stats, err = s.commentsRepository.TopicsStatForUser(ctx, schema.CommentMessageTypeIDForums, ids, userID)

			return err
		},
	}
	if userID > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			subscriptions, err = s.commentsRepository.SubscribedTopics(
				ctx, userID, schema.CommentMessageTypeIDForums, ids,
			)

			return err
		})
	}

	if err := runLookups(ctx, lookups); err != nil {
		return err
	}

	for _, topic := range topics {
		topic.Messages = stats[topic.ID].Messages
		if userID > 0 {
			topic.NewMessages = stats[topic.ID].NewMessages
			topic.Subscription = subscriptions[topic.ID]
		}
	}

	return nil
}

func (s *Repository) prepareTopic(ctx context.Context, topic *schema.ForumsTopicRow, userID int64) error {
	return runLookups(ctx, s.topicLookups(topic, userID))
}

// Single-topic reads use the same bounded runner as list batches. Each lookup
// owns distinct fields, read only after all lookups finish.
func (s *Repository) topicLookups(topic *schema.ForumsTopicRow, userID int64) []func(context.Context) error {
	if userID > 0 {
		return []func(context.Context) error{
			func(ctx context.Context) error {
				stat, err := s.commentsRepository.TopicStatForUser(
					ctx, schema.CommentMessageTypeIDForums, topic.ID, userID,
				)
				if err != nil {
					return err
				}

				topic.Messages = stat.Messages
				topic.NewMessages = stat.NewMessages

				return nil
			},
			func(ctx context.Context) error {
				subscription, err := s.commentsRepository.IsSubscribed(
					ctx, userID, schema.CommentMessageTypeIDForums, topic.ID,
				)
				if err != nil {
					return err
				}

				topic.Subscription = subscription

				return nil
			},
		}
	}

	return []func(context.Context) error{
		func(ctx context.Context) error {
			messages, err := s.commentsRepository.TopicStat(ctx, schema.CommentMessageTypeIDForums, topic.ID)
			if err != nil {
				return err
			}

			topic.Messages = messages

			return nil
		},
	}
}

func (s *Repository) topicsSelect(isModerator bool) *goqu.SelectDataset {
	sqSelect := s.db.Select(
		schema.ForumsTopicsTableIDCol,
		schema.ForumsTopicsTableNameCol,
		schema.ForumsTopicsTableStatusCol,
		schema.ForumsTopicsTableCreatedAtCol,
		schema.ForumsTopicsTableAuthorIDCol,
		schema.ForumsTopicsTableThemeIDCol,
	).
		From(schema.ForumsTopicsTable).
		Join(schema.CommentTopicTable, goqu.On(
			schema.ForumsTopicsTableIDCol.Eq(schema.CommentTopicTableItemIDCol),
			schema.CommentTopicTableTypeIDCol.Eq(schema.CommentMessageTypeIDForums),
		)).
		Where(schema.ForumsTopicsTableStatusCol.In(visibleTopicStatuses))

	if !isModerator {
		sqSelect = sqSelect.
			Join(
				schema.ForumsThemesTable,
				goqu.On(schema.ForumsTopicsTableThemeIDCol.Eq(schema.ForumsThemesTableIDCol)),
			).
			Where(schema.ForumsThemesTableIsModeratorCol.IsFalse())
	}

	return sqSelect
}

// Topic enrichment has at most two independent reads. Join both before exposing their results.
func runLookups(ctx context.Context, lookups []func(context.Context) error) error {
	group, groupCtx := errgroup.WithContext(ctx)
	for _, lookup := range lookups {
		group.Go(func() error { return lookup(groupCtx) })
	}

	return group.Wait()
}
