package goautowp

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func newForumsParallelTestServer(t *testing.T) (*Forums, *goqu.Database, *sql.DB) {
	t.Helper()

	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
  CREATE TEMP TABLE forums_themes (id bigint, is_moderator boolean);
  CREATE TEMP TABLE forums_topics (
   id bigint, name text, status text, created_at timestamptz, author_id bigint, theme_id bigint
  );
  CREATE TEMP TABLE comment_topic (type_id integer, item_id bigint, messages integer, last_update timestamptz);
  CREATE TEMP TABLE comment_topic_view (type_id integer, item_id bigint, user_id bigint, timestamp timestamptz);
  CREATE TEMP TABLE comment_topic_subscribe (type_id integer, item_id bigint, user_id bigint);
  CREATE TEMP TABLE comment_message (type_id integer, item_id bigint, datetime timestamptz);
  INSERT INTO forums_themes VALUES (1, false), (2, true);
  INSERT INTO forums_topics
   SELECT id, 'Topic ' || id, CASE WHEN id = 14 THEN 'deleted' WHEN id = 12 THEN 'closed' ELSE 'normal' END,
    '2020-01-01', 8, CASE WHEN id = 13 THEN 2 ELSE 1 END FROM generate_series(1, 14) id;
  INSERT INTO comment_topic SELECT 5, id, 3, '2020-01-01'::timestamptz + id * interval '1 day'
   FROM generate_series(1, 14) id;
  INSERT INTO comment_topic_view VALUES (5, 12, 7, '2020-01-02');
  INSERT INTO comment_topic_subscribe SELECT 5, id, 7 FROM generate_series(2, 14, 2) id;
  INSERT INTO comment_topic_subscribe VALUES (5, 1, 8);
  INSERT INTO comment_message SELECT 5, id, stamp::timestamptz
   FROM generate_series(1, 14) id CROSS JOIN (VALUES ('2020-01-01'), ('2020-01-03'), ('2020-01-04')) dates(stamp);
 `)
	require.NoError(t, err)

	db.Logger(nil)

	return NewForums(db, comments.NewRepository(db, nil, nil, nil, nil)), db, pool
}

func TestForumsTopicsConcurrentReads(t *testing.T) {
	t.Parallel()

	server, db, _ := newForumsParallelTestServer(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	gate := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, gate, 4)

	var seen, active, maximum atomic.Int64

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() {
		// Pagination count and topic selection precede enrichment.
		if seen.Add(1) <= 2 {
			return
		}

		current := active.Add(1)
		defer active.Add(-1)

		for old := maximum.Load(); current > old; old = maximum.Load() {
			if maximum.CompareAndSwap(old, current) {
				break
			}
		}

		gate.Printf("")
	}})

	done := make(chan error, 1)

	var actual []*ForumsTopic

	var pages *util.Pages

	go func() {
		var extractErr error

		actual, pages, extractErr = server.Topics(ctx, 1, 7, false, false, 1)
		done <- extractErr
	}()

	unblock()
	require.NoError(t, <-done)

	db.Logger(nil)
	require.EqualValues(t, 4, maximum.Load())

	expected, expectedPages, err := server.Topics(context.WithValue(ctx, itemLookupContextKey{}, true),
		1, 7, false, false, 1)
	require.NoError(t, err)
	require.Equal(t, expected, actual)
	require.Equal(t, expectedPages, pages)
	require.EqualValues(t, 12, pages.TotalItemCount)
	require.EqualValues(t, 2, pages.PageCount)
	require.Len(t, actual, 10)

	for index, topic := range actual {
		require.EqualValues(t, 12-index, topic.ID)
		require.EqualValues(t, 3, topic.Messages)
		require.Equal(t, topic.ID%2 == 0, topic.Subscription)

		if topic.ID == 12 {
			require.EqualValues(t, 2, topic.NewMessages)
		} else {
			require.EqualValues(t, 3, topic.NewMessages)
		}
	}

	// Standalone preparation also overlaps its two independent reads.
	gate = &itemBatchQueryLogger{}
	unblock = blockItemExtractionQueries(t, gate, 2)

	db.Logger(gate)

	topic := &ForumsTopic{ID: 12}

	go func() { done <- server.prepareTopic(ctx, topic, 7) }()

	unblock()
	require.NoError(t, <-done)

	db.Logger(nil)
	require.Equal(t, actual[0].Messages, topic.Messages)
	require.Equal(t, actual[0].NewMessages, topic.NewMessages)
	require.Equal(t, actual[0].Subscription, topic.Subscription)
}

func TestForumsTopicsFiltersAndPages(t *testing.T) {
	t.Parallel()

	server, _, _ := newForumsParallelTestServer(t)
	for _, testCase := range []struct {
		userID                  int64
		moderator, subscription bool
		themeID                 int64
		page                    int32
		ids                     []int64
		total                   int32
	}{
		{themeID: 1, page: 2, ids: []int64{2, 1}, total: 12},
		{userID: 7, subscription: true, ids: []int64{12, 10, 8, 6, 4, 2}, total: 6},
		{moderator: true, page: 2, ids: []int64{3, 2, 1}, total: 13},
		{themeID: 2, ids: []int64{}, total: 0},
		{themeID: 2, moderator: true, ids: []int64{13}, total: 1},
	} {
		rows, pages, err := server.Topics(t.Context(), testCase.themeID, testCase.userID,
			testCase.moderator, testCase.subscription, testCase.page)
		require.NoError(t, err)
		require.NotNil(t, rows)

		ids := make([]int64, 0, len(rows))
		for _, row := range rows {
			ids = append(ids, row.ID)
			if testCase.userID == 0 {
				require.Zero(t, row.NewMessages)
				require.False(t, row.Subscription)
			}
		}

		require.Equal(t, testCase.ids, ids)
		require.Equal(t, testCase.total, pages.TotalItemCount)
	}
}

func TestForumsTopicsCancellation(t *testing.T) {
	t.Parallel()

	server, db, pool := newForumsParallelTestServer(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	var queries atomic.Int64

	started := make(chan struct{}, 4)

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() {
		if queries.Add(1) > 2 {
			started <- struct{}{}

			<-ctx.Done()
		}
	}})

	done := make(chan error, 1)

	go func() {
		_, _, extractErr := server.Topics(ctx, 1, 7, false, false, 1)
		done <- extractErr
	}()

	for range 4 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("topic enrichment reads did not start together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
	require.Zero(t, pool.Stats().InUse)
}

func TestForumsTopicsReadFailure(t *testing.T) {
	t.Parallel()

	server, _, pool := newForumsParallelTestServer(t)
	_, err := pool.ExecContext(
		t.Context(), "ALTER TABLE comment_topic_subscribe RENAME COLUMN user_id TO broken_user_id",
	)
	require.NoError(t, err)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	rows, pages, err := server.Topics(ctx, 1, 7, false, false, 1)
	require.ErrorContains(t, err, "user_id")
	require.Nil(t, rows)
	require.Nil(t, pages)
	require.NoError(t, ctx.Err())
	require.Zero(t, pool.Stats().InUse)
}
