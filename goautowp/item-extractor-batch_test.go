package goautowp

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

type itemBatchQueryLogger struct {
	queries atomic.Int64
}

func (s *itemBatchQueryLogger) Printf(_ string, _ ...interface{}) {
	s.queries.Add(1)
}

func newItemBatchTestExtractor(t *testing.T) (*ItemExtractor, *itemBatchQueryLogger) {
	t.Helper()

	cfg := config.LoadConfig(".")
	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	_, err = database.ExecContext(t.Context(), `
		CREATE TEMP TABLE item_language (item_id bigint, language text, name text, full_text_id bigint);
		CREATE TEMP TABLE textstorage_text (id bigint, text text);
		CREATE TEMP TABLE item_link (item_id bigint);
		CREATE TEMP TABLE user_item_subscribe (item_id bigint, user_id bigint);
		CREATE TEMP TABLE attrs_values (item_id bigint, attribute_id bigint);
		CREATE TEMP TABLE comment_topic (item_id bigint, type_id integer, messages integer);
		INSERT INTO textstorage_text VALUES (1, 'Full text'), (2, ''), (3, NULL);
		INSERT INTO item_language VALUES
			(1, 'xx', 'First', 2), (1, 'en', 'First EN', 1), (1, 'ru', 'First RU', 1),
			(2, 'xx', 'Second', 2), (2, 'ru', 'Second RU', 3), (100, 'xx', 'Outside', 1);
		INSERT INTO item_link VALUES (1), (1), (2), (100);
		INSERT INTO user_item_subscribe VALUES (1, 7), (2, 8), (100, 7);
		INSERT INTO attrs_values VALUES (1, 11), (1, 12), (2, 11), (100, 11);
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO comment_topic VALUES
		(1, $1, 12), (2, $1, 4), (100, $1, 99), (1, $2, 500)`,
		schema.CommentMessageTypeIDItems, schema.CommentMessageTypeIDPictures)
	require.NoError(t, err)

	logger := &itemBatchQueryLogger{}
	goquDB := goqu.New("postgres", database)
	goquDB.Logger(logger)
	container := &Container{
		itemsRepository:    items.NewRepository(goquDB, 0, nil, nil, nil),
		attrsRepository:    attrs.NewRepository(goquDB, nil, nil, nil, nil, nil, nil),
		commentsRepository: comments.NewRepository(goquDB, nil, nil, nil, nil),
	}

	return NewItemExtractor(container), logger
}

func TestItemExtractorBatchesAdditionalFields(t *testing.T) {
	t.Parallel()

	extractor, logger := newItemBatchTestExtractor(t)
	fields := &ItemFields{
		Meta: true, SpecificationsCount: true, Subscription: true, HasText: true,
		CommentsCount: true, ItemLanguageCount: true, LinksCount: true,
	}
	userCtx := UserContext{UserID: 7, Roles: []string{users.RoleModer}}

	rows := []*items.Item{{ID: 2}, {ID: 1}}
	for id := int64(3); id <= 50; id++ {
		rows = append(rows, &items.Item{ID: id})
	}

	rows = append(rows, &items.Item{ID: 1})

	result, err := extractor.ExtractRows(t.Context(), rows, fields, "ru", userCtx)
	require.NoError(t, err)
	require.Len(t, result, len(rows))
	require.EqualValues(t, 7, logger.queries.Load(), "one SQL query per selected field, independent of list size")

	for index, row := range rows {
		actual := result[index]
		require.Equal(t, row.ID, actual.GetId())

		name, err := extractor.container.itemsRepository.LanguageName(t.Context(), row.ID, schema.DefaultLanguageCode)
		require.NoError(t, err)
		require.Equal(t, name, actual.GetName())

		languages, err := extractor.container.itemsRepository.ItemLanguageCount(
			t.Context(),
			&query.ItemLanguageListOptions{ItemID: row.ID, ExcludeLanguage: schema.DefaultLanguageCode},
		)
		require.NoError(t, err)
		require.Equal(t, languages, actual.GetItemLanguageCount())

		links, err := extractor.container.itemsRepository.LinksCount(
			t.Context(),
			&query.LinkListOptions{ItemID: row.ID},
		)
		require.NoError(t, err)
		require.Equal(t, links, actual.GetLinksCount())

		subscribed, err := extractor.container.itemsRepository.UserItemSubscribed(t.Context(), row.ID, userCtx.UserID)
		require.NoError(t, err)
		require.Equal(t, subscribed, actual.GetSubscription())

		fullText, err := extractor.container.itemsRepository.HasFullText(t.Context(), row.ID)
		require.NoError(t, err)
		require.Equal(t, fullText, actual.GetHasText())

		specifications, err := extractor.container.attrsRepository.ValuesCount(t.Context(), query.AttrsValueListOptions{
			ItemID: row.ID,
		})
		require.NoError(t, err)
		require.Equal(t, specifications, actual.GetSpecificationsCount())

		messages, err := extractor.container.commentsRepository.TopicStat(
			t.Context(), schema.CommentMessageTypeIDItems, row.ID,
		)
		require.NoError(t, err)
		require.Equal(t, messages, actual.GetCommentsCount())
	}

	require.EqualValues(t, 7+7*len(rows), logger.queries.Load(), "the old methods need seven queries per item")
}

func TestItemExtractorAdditionalFieldsRespectPermissionsAndSelection(t *testing.T) {
	t.Parallel()

	extractor, logger := newItemBatchTestExtractor(t)

	rows := []*items.Item{{ID: 1}, {ID: 2}}
	fields := &ItemFields{
		Meta: true, SpecificationsCount: true, Subscription: true, HasText: true,
		CommentsCount: true, ItemLanguageCount: true, LinksCount: true,
	}
	result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", UserContext{})
	require.NoError(t, err)
	require.EqualValues(t, 2, logger.queries.Load(), "anonymous callers only load full-text presence and comments")
	require.True(t, result[0].GetHasText())
	require.EqualValues(t, 12, result[0].GetCommentsCount())

	for _, item := range result {
		require.Empty(t, item.GetName())
		require.Zero(t, item.GetSpecificationsCount())
		require.Zero(t, item.GetItemLanguageCount())
		require.Zero(t, item.GetLinksCount())
		require.False(t, item.GetSubscription())
	}

	logger.queries.Store(0)

	result, err = extractor.ExtractRows(t.Context(), rows, nil, "en", UserContext{
		UserID: 7, Roles: []string{users.RoleModer},
	})
	require.NoError(t, err)
	require.Len(t, result, 2)
	require.Zero(t, logger.queries.Load())
	require.Zero(t, result[0].GetCommentsCount())
	require.False(t, result[0].GetHasText())

	result, err = extractor.ExtractRows(
		t.Context(),
		rows,
		&ItemFields{Subscription: true},
		"en",
		UserContext{UserID: 8},
	)
	require.NoError(t, err)
	require.False(t, result[0].GetSubscription())
	require.True(t, result[1].GetSubscription())
	require.EqualValues(t, 1, logger.queries.Load())
}

func TestItemExtractorAdditionalFieldsEmptyAndCanceled(t *testing.T) {
	t.Parallel()

	batch, err := (&ItemExtractor{}).preloadFields(t.Context(), nil, &ItemFields{CommentsCount: true}, UserContext{})
	require.NoError(t, err)
	require.Empty(t, batch.comments)

	extractor, logger := newItemBatchTestExtractor(t)
	result, err := extractor.ExtractRows(t.Context(), nil, &ItemFields{CommentsCount: true}, "en", UserContext{})
	require.NoError(t, err)
	require.Empty(t, result)
	require.Zero(t, logger.queries.Load())

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = extractor.ExtractRows(ctx, []*items.Item{{ID: 1}}, &ItemFields{CommentsCount: true}, "en", UserContext{})
	require.ErrorIs(t, err, context.Canceled)
}
