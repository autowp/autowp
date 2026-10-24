package goautowp

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newExtractMessageTestServer(
	t *testing.T,
) (*CommentsGRPCServer, *schema.CommentMessageRow, *goqu.Database, *sql.DB) {
	t.Helper()

	db, pool, _ := newRelatedGroupTestDB(t)
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

	server := &CommentsGRPCServer{
		repository: comments.NewRepository(db, nil, nil, nil, nil),
		picturesRepository: pictures.NewRepository(db, nil, nil, nil, nil,
			config.DuplicateFinderConfig{}, nil, nil, nil, nil),
	}
	row, err := server.repository.Message(t.Context(), 1, true, true, true)
	require.NoError(t, err)

	db.Logger(nil)

	return server, row, db, pool
}

func allCommentMessageFields() *CommentMessageFields {
	return &CommentMessageFields{
		IsNew: true, Preview: true, Text: true, Vote: true, UserVote: true, Route: true, Replies: true, Status: true,
	}
}

func TestExtractMessageConcurrentReads(t *testing.T) {
	t.Parallel()

	server, row, db, _ := newExtractMessageTestServer(t)
	fields := allCommentMessageFields()
	original := proto.Clone(fields)
	originalRow := *row
	roles := []string{users.RoleModer, users.RoleCommentsModer}

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	gate := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, gate, 4)

	var active, maximum atomic.Int64

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() {
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

	var actual *CommentMessage

	go func() {
		var extractErr error

		actual, extractErr = extractMessage(
			ctx, row, server.repository, server.picturesRepository, 7, roles, true, fields,
		)
		done <- extractErr
	}()

	unblock()
	require.NoError(t, <-done)

	db.Logger(nil)

	serialCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
	expected, err := extractMessage(
		serialCtx, row, server.repository, server.picturesRepository, 7, roles, true, fields,
	)
	require.NoError(t, err)
	require.True(t, proto.Equal(expected, actual))
	require.EqualValues(t, 4, maximum.Load(), "recursive replies must share the concurrency limit")
	require.True(t, proto.Equal(original, fields))
	require.Equal(t, originalRow, *row)
	require.False(t, actual.GetIsNew())
	require.EqualValues(t, 1, actual.GetUserVote())
	require.Equal(t, "127.0.0.1", actual.GetIpAddress())
	require.Equal(t, "Root message", actual.GetText())
	require.Equal(t, frontend.PictureRoute("comment-picture"), actual.GetRoute())
	require.Equal(t, PictureStatus_PICTURE_STATUS_ACCEPTED, actual.GetPictureStatus())
	require.Len(t, actual.GetReplies(), 2)
	require.EqualValues(t, 3, actual.GetReplies()[0].GetId())
	require.Equal(t, "Deleted reply", actual.GetReplies()[0].GetText())
	require.True(t, actual.GetReplies()[0].GetIsNew())
	require.EqualValues(t, 2, actual.GetReplies()[1].GetId())
	require.Len(t, actual.GetReplies()[1].GetReplies(), 1)
	require.EqualValues(t, 4, actual.GetReplies()[1].GetReplies()[0].GetId())
}

func TestExtractMessagePermissionsAndFields(t *testing.T) {
	t.Parallel()

	server, row, _, _ := newExtractMessageTestServer(t)
	fields := allCommentMessageFields()
	actual, err := extractMessage(
		t.Context(), row, server.repository, server.picturesRepository, 0, nil, false, fields,
	)
	require.NoError(t, err)
	require.False(t, actual.GetIsNew())
	require.Zero(t, actual.GetUserVote())
	require.Empty(t, actual.GetIpAddress())
	require.Equal(t, PictureStatus_PICTURE_STATUS_UNKNOWN, actual.GetPictureStatus())
	require.Len(t, actual.GetReplies(), 2)
	deleted := actual.GetReplies()[0]
	require.True(t, deleted.GetDeleted())
	require.Empty(t, deleted.GetText())
	require.Empty(t, deleted.GetPreview())
	require.Empty(t, deleted.GetRoute())
	require.Nil(t, deleted.GetReplies())
	require.Zero(t, deleted.GetVote())

	empty, err := extractMessage(
		t.Context(), row, nil, nil, 7, nil, false, nil,
	)
	require.NoError(t, err)
	require.Empty(t, empty.GetText())
	require.Nil(t, empty.GetReplies())
	require.Equal(t, row.ID, empty.GetId())
}

func TestExtractMessageCancellation(t *testing.T) {
	t.Parallel()

	server, row, db, pool := newExtractMessageTestServer(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	defer connection.Close()

	started := make(chan struct{}, 4)

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

	done := make(chan error, 1)

	go func() {
		_, extractErr := extractMessage(ctx, row, server.repository, server.picturesRepository, 7,
			[]string{users.RoleModer}, true, allCommentMessageFields())
		done <- extractErr
	}()

	for range 4 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("message reads did not start together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
}

func TestExtractMessageReadFailure(t *testing.T) {
	t.Parallel()

	server, row, db, pool := newExtractMessageTestServer(t)
	_, err := pool.ExecContext(t.Context(), "ALTER TABLE comment_vote RENAME COLUMN vote TO broken_vote")
	require.NoError(t, err)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	actual, err := extractMessage(ctx, row, server.repository, server.picturesRepository, 7,
		[]string{users.RoleModer}, true, allCommentMessageFields())
	require.ErrorContains(t, err, "comment_vote")
	require.Nil(t, actual)
	require.NoError(t, ctx.Err(), "a failed child must not cancel the caller's context")
	require.Zero(t, pool.Stats().InUse, "all reads must finish before returning")

	db.Logger(nil)
}
