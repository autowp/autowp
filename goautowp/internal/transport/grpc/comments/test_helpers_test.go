package comments

import (
	"database/sql"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/transport/grpc/commentextractor"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

type queryLogger struct {
	queries     atomic.Int64
	beforeQuery func()
}

func (s *queryLogger) Printf(_ string, _ ...interface{}) {
	s.queries.Add(1)

	if s.beforeQuery != nil {
		s.beforeQuery()
	}
}

func newTestDB(t *testing.T) (*goqu.Database, *sql.DB, *queryLogger) {
	t.Helper()

	pool, err := sql.Open("postgres", config.LoadConfig("../../../..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })

	logger := &queryLogger{}
	db := goqu.New("postgres", pool)
	db.Logger(logger)

	return db, pool, logger
}

// Block before SQL acquisition so one-connection fixtures can verify concurrent reads.
func blockQueries(t *testing.T, logger *queryLogger, count int) func() {
	t.Helper()

	started := make(chan struct{}, count)
	release := make(chan struct{})

	var once sync.Once

	unblock := func() { once.Do(func() { close(release) }) }
	t.Cleanup(unblock)

	var observed atomic.Int64

	logger.beforeQuery = func() {
		if observed.Add(1) > int64(count) {
			return
		}

		started <- struct{}{}

		<-release
	}

	return func() {
		t.Helper()

		defer unblock()

		timer := time.NewTimer(5 * time.Second)
		defer timer.Stop()

		for range count {
			select {
			case <-started:
			case <-timer.C:
				t.Fatal("independent comment reads did not start together")
			}
		}
	}
}

func newTestServer(
	t *testing.T,
) (*Server, *goqu.Database, *sql.DB) {
	t.Helper()

	db, pool, _ := newTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
  CREATE TEMP TABLE picture (id bigint, identity text, status text);
  CREATE TEMP TABLE comment_message (
   id bigint, type_id integer, item_id bigint, parent_id bigint,
   datetime timestamptz, updated_at timestamptz, deleted boolean,
   moderator_attention integer, author_id bigint, ip inet, message text, vote integer
  );
  CREATE TEMP TABLE comment_vote (user_id bigint, comment_id bigint, vote integer);
  CREATE TEMP TABLE comment_topic_view (user_id bigint, type_id integer, item_id bigint, timestamp timestamptz);
  INSERT INTO picture VALUES (10, 'comment-picture', 'accepted');
  INSERT INTO comment_message VALUES
   (1, 1, 10, NULL, '2020-01-01', '2020-02-01', false, 1, 7, '127.0.0.1', 'Root message', 5),
   (2, 1, 10, 1, '2020-01-02', NULL, false, 0, 8, '127.0.0.2', 'First reply', 2),
   (3, 1, 10, 1, '2020-01-03', NULL, true, 2, NULL, '127.0.0.3', 'Deleted reply', -1),
   (4, 1, 10, 2, '2020-01-04', NULL, false, 0, 9, '127.0.0.4', 'Nested reply', 1);
  INSERT INTO comment_vote VALUES (7, 1, 1), (7, 2, -1);
  INSERT INTO comment_topic_view VALUES (7, 1, 10, '2020-01-02');
 `)
	require.NoError(t, err)

	repository := comments.NewRepository(db, nil, nil, nil, nil)
	picturesRepository := pictures.NewRepository(
		db,
		nil,
		nil,
		nil,
		nil,
		"", config.DuplicateFinderConfig{},
		nil,
		nil,
		nil,
		nil,
	)
	server := NewServer(nil, repository, nil, commentextractor.New(repository, picturesRepository), nil)

	db.Logger(nil)

	return server, db, pool
}

func allCommentMessageFields() *pb.CommentMessageFields {
	return &pb.CommentMessageFields{
		IsNew: true, Preview: true, Text: true, Vote: true, UserVote: true, Route: true, Replies: true, Status: true,
	}
}
