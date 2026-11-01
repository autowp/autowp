package mosts_test

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/attrs"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/mosts"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/transport/grpc/itemextractor"
	mostsgrpc "github.com/autowp/goautowp/internal/transport/grpc/mosts"
	"github.com/autowp/goautowp/internal/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newItemBatchTestExtractor(t *testing.T) (*itemextractor.Extractor, *testutil.QueryLogger) {
	t.Helper()

	db, database, logger := testutil.NewPostgres(t, "../../../..")
	_, err := database.ExecContext(t.Context(), `
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

	itemRepository := items.NewRepository(db, 0, nil, nil, nil)
	attributes := attrs.NewRepository(db, nil, nil, nil, nil, nil, nil)
	commentRepository := comments.NewRepository(db, nil, nil, nil, nil)
	extractor := itemextractor.New(itemextractor.Dependencies{
		ItemsRepository:    func(_ context.Context) (*items.Repository, error) { return itemRepository, nil },
		AttrsRepository:    func(_ context.Context) (*attrs.Repository, error) { return attributes, nil },
		CommentsRepository: func(_ context.Context) (*comments.Repository, error) { return commentRepository, nil },
	})

	return extractor, logger
}

func TestMostsItemsBatchSharesFieldReads(t *testing.T) {
	t.Parallel()
	extractor, logger := newItemBatchTestExtractor(t)
	server := mostsgrpc.NewServer(nil, extractor, nil)
	fields := &pb.ItemFields{
		Meta: true, SpecificationsCount: true, Subscription: true, HasText: true,
		CommentsCount: true, ItemLanguageCount: true, LinksCount: true,
	}
	user := authn.User{UserID: 7, Roles: []string{users.RoleModer}}
	list := []mosts.ResultItem{
		{Item: &items.Item{ID: 2}, ValueHTML: "2 <span>kg</span>"},
		{Item: &items.Item{ID: 1}, ValueHTML: "1 <span>kg</span>"},
		{Item: &items.Item{ID: 2}, ValueHTML: "0 <span>kg</span>"},
	}

	expected := make([]*pb.MostsItem, len(list))
	for index, car := range list {
		item, err := extractor.Extract(t.Context(), car.Item, fields, "ru", user)
		require.NoError(t, err)

		expected[index] = &pb.MostsItem{Item: item, ValueHtml: car.ValueHTML, UnitId: 42}
	}

	require.EqualValues(t, 21, logger.Queries.Load())
	logger.Queries.Store(0)

	actual, err := server.ExtractItems(t.Context(), list, 42, fields, "ru", user)
	require.NoError(t, err)
	require.EqualValues(t, 7, logger.Queries.Load())
	require.True(t, proto.Equal(&pb.MostsItems{Items: expected}, &pb.MostsItems{Items: actual}))
}
