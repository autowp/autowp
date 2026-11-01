package goautowp

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
)

func newItemLanguagesBatchTestServer(t *testing.T) (*ItemsGRPCServer, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	db, pool, logger := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
		CREATE TEMP TABLE item_language
			(item_id bigint, language text, name text, text_id integer, full_text_id integer);
		CREATE TEMP TABLE textstorage_text (id integer PRIMARY KEY, text text);
		INSERT INTO textstorage_text VALUES (0, 'Unused zero'), (1, 'Shared'), (2, ''), (3, 'Full text');
		INSERT INTO item_language
		SELECT 10, 'lang-' || n, 'Name ' || n, 1, CASE WHEN n % 2 = 0 THEN 2 ELSE 3 END
		FROM generate_series(1, 30) AS n;
		INSERT INTO item_language (item_id, language) VALUES (10, 'no-text'), (11, 'no-text');
		INSERT INTO item_language VALUES (10, 'nonpositive', '', 0, -1);
	`)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO item_language VALUES (10, $1, 'Default', 99, 99)`,
		schema.DefaultLanguageCode)
	require.NoError(t, err)

	return &ItemsGRPCServer{
		auth:                  &Auth{},
		repository:            items.NewRepository(db, 0, nil, nil, nil),
		textstorageRepository: textstorage.New(db),
	}, pool, logger
}

func TestGetItemLanguagesBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		name            string
		itemID          int64
		expectedQueries int64
	}{
		{name: "shared and empty texts", itemID: 10, expectedQueries: 2},
		{name: "no text references", itemID: 11, expectedQueries: 1},
		{name: "no languages", itemID: 12, expectedQueries: 1},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			server, _, logger := newItemLanguagesBatchTestServer(t)
			ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{Roles: []string{users.RoleModer}})
			rows, err := server.repository.ItemLanguageList(ctx, test.itemID)
			require.NoError(t, err)

			expected := &ItemLanguages{Items: make([]*ItemLanguage, len(rows))}
			for index, row := range rows {
				item := &ItemLanguage{
					ItemId: row.ItemID, Language: row.Language, Name: row.Name,
					TextId: row.TextID, FullTextId: row.FullTextID,
				}
				if row.TextID > 0 {
					item.Text, err = server.textstorageRepository.Text(ctx, row.TextID)
					require.NoError(t, err)
				}

				if row.FullTextID > 0 {
					item.FullText, err = server.textstorageRepository.Text(ctx, row.FullTextID)
					require.NoError(t, err)
				}

				expected.Items[index] = item
			}

			logger.queries.Store(0)

			actual, err := server.GetItemLanguages(ctx, &GetItemLanguagesRequest{ItemId: test.itemID})
			require.NoError(t, err)
			require.True(t, proto.Equal(expected, actual), "expected %v, got %v", expected, actual)
			require.Equal(t, test.expectedQueries, logger.queries.Load())
		})
	}
}

func TestGetItemLanguagesBatchErrors(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		name             string
		query            string
		expectedNotFound bool
	}{
		{name: "missing text", query: "UPDATE item_language SET text_id=99 WHERE item_id=11", expectedNotFound: true},
		{
			name: "missing full text", query: "UPDATE item_language SET full_text_id=99 WHERE item_id=11",
			expectedNotFound: true,
		},
		{name: "null text", query: "UPDATE textstorage_text SET text=NULL WHERE id=1"},
		{name: "text query failure", query: "ALTER TABLE textstorage_text RENAME COLUMN text TO broken_text"},
		{name: "language query failure", query: "ALTER TABLE item_language RENAME COLUMN name TO broken_name"},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			server, pool, _ := newItemLanguagesBatchTestServer(t)
			_, err := pool.ExecContext(t.Context(), test.query)
			require.NoError(t, err)

			itemID := int64(10)
			if test.expectedNotFound {
				itemID = 11
			}

			ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{Roles: []string{users.RoleModer}})
			actual, err := server.GetItemLanguages(ctx, &GetItemLanguagesRequest{ItemId: itemID})
			require.Nil(t, actual)
			require.Equal(t, codes.Internal, status.Code(err))

			if test.expectedNotFound {
				_, expectedErr := server.textstorageRepository.Text(ctx, 99)
				require.ErrorIs(t, expectedErr, textstorage.ErrTextNotFound)
				require.Equal(t, expectedErr.Error(), status.Convert(err).Message())
			}
		})
	}
}

func TestGetItemLanguagesBatchCancellation(t *testing.T) {
	t.Parallel()

	server, _, logger := newItemLanguagesBatchTestServer(t)

	ctx, cancel := context.WithCancel(context.WithValue(t.Context(), authenticatedUserKey{},
		UserContext{Roles: []string{users.RoleModer}}))
	defer cancel()

	logger.beforeQuery = func() {
		if logger.queries.Load() == 2 {
			cancel()
		}
	}

	actual, err := server.GetItemLanguages(ctx, &GetItemLanguagesRequest{ItemId: 10})
	require.Nil(t, actual)
	require.Equal(t, codes.Internal, status.Code(err))
	require.Contains(t, status.Convert(err).Message(), context.Canceled.Error())
}
