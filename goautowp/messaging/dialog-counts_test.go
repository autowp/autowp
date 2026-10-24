package messaging

import (
	"context"
	"database/sql"
	"fmt"
	"strings"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

type dialogQueryLogger struct{ queries []string }

func (s *dialogQueryLogger) Printf(_ string, args ...interface{}) {
	s.queries = append(s.queries, fmt.Sprint(args...))
}

func newDialogBatchRepository(t *testing.T) (*Repository, *sql.DB) {
	t.Helper()

	cfg := config.LoadConfig("..")
	pool, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	_, err = pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE personal_message (
 id bigint PRIMARY KEY,from_user_id bigint,to_user_id bigint,readen boolean DEFAULT false,
 contents text DEFAULT 'body',created_at timestamptz,deleted_by_from boolean,deleted_by_to boolean
 );
 INSERT INTO personal_message (id,from_user_id,to_user_id,created_at,deleted_by_from,deleted_by_to)
 SELECT row_number() OVER (),p.sender,p.recipient,
 '2020-01-01'::timestamptz + row_number() OVER () * interval '1 second',f.deleted,t.deleted
 FROM (VALUES (7,8),(8,7),(7,7),(7,9),(9,7),(8,9),(NULL,7),(7,0),(0,7)) p(sender,recipient)
 CROSS JOIN (VALUES (true),(false),(NULL)) f(deleted)
 CROSS JOIN (VALUES (true),(false),(NULL)) t(deleted);
 `)
	require.NoError(t, err)

	return NewRepository(goqu.New("postgres", pool), nil, nil, nil), pool
}

func TestPrepareListBatchesDialogCounts(t *testing.T) {
	t.Parallel()
	repo, pool := newDialogBatchRepository(t)

	var rows []schema.PersonalMessageRow
	require.NoError(t, repo.db.From(schema.PersonalMessageTable).Order(schema.PersonalMessageTableIDCol.Asc()).
		ScanStructsContext(t.Context(), &rows))

	for _, userID := range []int64{7, 8, 0} {
		raw, err := repo.prepareList(t.Context(), userID, rows, Options{})
		require.NoError(t, err)

		expected := make([]Message, len(raw))
		copy(expected, raw)

		for index := range expected {
			expected[index].AllMessagesLink = true
			if peer := expected[index].DialogWithUserID; peer != 0 {
				expected[index].DialogCount, err = repo.GetDialogCount(t.Context(), userID, peer)
				require.NoError(t, err)
			}
		}

		logger := &dialogQueryLogger{}
		repo.db.Logger(logger)
		actual, err := repo.prepareList(t.Context(), userID, rows, Options{AllMessagesLink: true})
		require.NoError(t, err)
		require.Equal(t, expected, actual)
		require.Len(t, logger.queries, 1)
		logger.queries = nil
		withoutCounts, err := repo.prepareList(t.Context(), userID, rows, Options{})
		require.NoError(t, err)
		require.Equal(t, raw, withoutCounts)
		require.Empty(t, logger.queries)
		empty, err := repo.prepareList(t.Context(), userID, nil, Options{AllMessagesLink: true})
		require.NoError(t, err)
		require.NotNil(t, empty)
		require.Empty(t, logger.queries)
		repo.db.Logger(nil)
	}

	logger := &dialogQueryLogger{}
	repo.db.Logger(logger)
	system, err := repo.prepareList(
		t.Context(),
		7,
		[]schema.PersonalMessageRow{{ToUserID: 7}},
		Options{AllMessagesLink: true},
	)
	require.NoError(t, err)
	require.Zero(t, system[0].DialogCount)
	require.Empty(t, logger.queries)
	repo.db.Logger(nil)

	_, err = pool.ExecContext(
		t.Context(),
		"ALTER TABLE pg_temp.personal_message RENAME COLUMN deleted_by_from TO broken_flag",
	)
	require.NoError(t, err)
	_, err = repo.prepareList(t.Context(), 7, rows, Options{AllMessagesLink: true})
	require.Error(t, err)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repo.prepareList(ctx, 7, rows, Options{AllMessagesLink: true})
	require.ErrorIs(t, err, context.Canceled)
}

func TestDialogBatchPreservesReadMarking(t *testing.T) {
	t.Parallel()
	repo, _ := newDialogBatchRepository(t)
	logger := &dialogQueryLogger{}
	repo.db.Logger(logger)
	messages, pages, err := repo.GetInbox(t.Context(), 7, 1)
	require.NoError(t, err)
	require.NotEmpty(t, messages)
	require.NotNil(t, pages)

	updateIndex, countIndex := -1, -1

	for index, query := range logger.queries {
		if strings.Contains(query, `UPDATE "personal_message"`) {
			updateIndex = index
		}

		if strings.Contains(query, `CASE`) {
			countIndex = index
		}
	}

	require.GreaterOrEqual(t, updateIndex, 0)
	require.Greater(t, countIndex, updateIndex, "read marking must precede dialog counts")
	repo.db.Logger(nil)

	for _, message := range messages {
		require.True(t, message.IsNew, "response retains unread state from the fetched row")

		var read bool

		found, err := repo.db.From(schema.PersonalMessageTable).Select(schema.PersonalMessageTableReadenCol).
			Where(schema.PersonalMessageTableIDCol.Eq(message.ID)).ScanValContext(t.Context(), &read)
		require.NoError(t, err)
		require.True(t, found)
		require.True(t, read)
		expected, err := repo.GetDialogCount(t.Context(), 7, message.DialogWithUserID)
		require.NoError(t, err)

		if message.DialogWithUserID != 0 {
			require.Equal(t, expected, message.DialogCount)
		}
	}
}
