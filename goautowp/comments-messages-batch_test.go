package goautowp

import (
	"context"
	"fmt"
	"strings"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestGetMessagesBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()
	server, _, db, pool := newExtractMessageTestServer(t)
	server.auth = &Auth{}
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE comment_topic_subscribe (type_id integer,item_id bigint,user_id bigint,sent boolean);
 INSERT INTO comment_topic_subscribe VALUES (1,10,7,true), (1,10,8,true);
 INSERT INTO comment_vote VALUES (8,1,-1),(7,4,1);
 INSERT INTO comment_message VALUES
 (5,2,20,NULL,'2020-01-05',NULL,false,0,7,NULL,'Other topic',0);
 INSERT INTO comment_topic_view VALUES (8,1,10,'2030-01-01'),(7,2,20,NULL);
 `)
	require.NoError(t, err)

	for _, roles := range [][]string{nil, {users.RoleModer, users.RoleCommentsModer}} {
		ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 7, Roles: roles})

		for _, fields := range []*CommentMessageFields{
			{}, {IsNew: true, UserVote: true, Text: true, Vote: true}, allCommentMessageFields(),
		} {
			ids := []int64{1, 2, 3, 4}
			if len(roles) > 0 && !fields.GetRoute() {
				ids = append(ids, 5)
			}

			expected := &CommentMessages{
				Items:     make([]*CommentMessage, 0, len(ids)),
				Paginator: &Pages{PageCount: 1, Current: 1, TotalItemCount: 4},
			}
			if len(ids) == 5 {
				expected.Paginator.TotalItemCount = 5
			}

			for _, id := range ids {
				row, err := server.repository.Message(
					ctx,
					id,
					fields.GetText() || fields.GetPreview(),
					fields.GetVote(),
					len(roles) > 0,
				)
				require.NoError(t, err)
				item, err := extractMessage(
					ctx,
					row,
					server.repository,
					server.picturesRepository,
					7,
					roles,
					len(roles) > 0,
					fields,
				)
				require.NoError(t, err)

				expected.Items = append(expected.Items, item)
			}

			request := &GetMessagesRequest{
				ItemId: 10,
				TypeId: CommentsType_PICTURES_TYPE_ID,
				Limit:  100,
				Fields: fields,
			}
			if len(roles) > 0 && !fields.GetRoute() {
				request.ItemId = 0
				request.TypeId = CommentsType_UNKNOWN
			}

			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			actual, err := server.GetMessages(ctx, request)
			require.NoError(t, err)
			db.Logger(nil)
			require.True(t, proto.Equal(expected, actual), "fields=%v roles=%v", fields, roles)

			if fields.GetIsNew() && !fields.GetReplies() {
				want := 4 // count + page + two batches
				if request.GetItemId() > 0 {
					want++
				} // subscription update

				require.EqualValues(t, want, logger.queries.Load())
			}
		}
	}

	var sent bool

	_, err = db.From("comment_topic_subscribe").
		Select("sent").
		Where(goqu.Ex{"user_id": 7}).
		ScanValContext(t.Context(), &sent)
	require.NoError(t, err)
	require.False(t, sent)
}

func TestGetMessagesParallelAndSubscriptionFailureBoundary(t *testing.T) {
	t.Parallel()
	server, _, db, pool := newExtractMessageTestServer(t)
	server.auth = &Auth{}
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE comment_topic_subscribe (type_id integer,item_id bigint,user_id bigint,sent boolean);
 INSERT INTO comment_topic_subscribe VALUES (1,10,7,true);
 `)
	require.NoError(t, err)

	ctx, cancel := context.WithTimeout(context.WithValue(t.Context(), authenticatedUserKey{},
		UserContext{UserID: 7, Roles: []string{users.RoleModer, users.RoleCommentsModer}}), 10*time.Second)
	defer cancel()

	gate := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, gate, 4)
	logger := &itemBatchQueryLogger{}
	logger.beforeQuery = func() {
		// Count and page are sequential; the next four route reads must start together.
		if logger.queries.Load() > 2 {
			gate.Printf("")
		}
	}
	db.Logger(logger)

	done := make(chan error, 1)

	go func() {
		_, err := server.GetMessages(ctx, &GetMessagesRequest{
			ItemId: 10, TypeId: CommentsType_PICTURES_TYPE_ID,
			Limit: 100, Fields: &CommentMessageFields{Route: true, Status: true},
		})
		done <- err
	}()

	unblock()
	require.NoError(t, <-done)
	db.Logger(nil)

	_, err = pool.ExecContext(
		ctx,
		`UPDATE pg_temp.comment_topic_subscribe SET sent=true;
 ALTER TABLE pg_temp.picture RENAME COLUMN status TO broken_status`,
	)
	require.NoError(t, err)
	result, err := server.GetMessages(ctx, &GetMessagesRequest{
		ItemId: 10, TypeId: CommentsType_PICTURES_TYPE_ID,
		Limit: 1, Fields: &CommentMessageFields{Status: true},
	})
	require.Error(t, err)
	require.Nil(t, result)

	var sent bool

	_, err = db.From("comment_topic_subscribe").Select("sent").ScanValContext(ctx, &sent)
	require.NoError(t, err)
	require.True(t, sent, "failed extraction must not clear subscription notification")
}

// Cancellation can discard the single connection holding temporary tables.
// Observe issued SQL directly so this check does not depend on that connection surviving.
type commentMessagesQueryLogger struct {
	updates     atomic.Int64
	queries     atomic.Int64
	beforeQuery func()
}

func (s *commentMessagesQueryLogger) Printf(_ string, args ...interface{}) {
	if strings.Contains(fmt.Sprint(args...), `UPDATE "comment_topic_subscribe"`) {
		s.updates.Add(1)
	}

	s.queries.Add(1)

	if s.beforeQuery != nil {
		s.beforeQuery()
	}
}

func TestGetMessagesBatchCancellation(t *testing.T) {
	t.Parallel()
	server, _, db, _ := newExtractMessageTestServer(t)
	server.auth = &Auth{}

	ctx, cancel := context.WithTimeout(
		context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 7}),
		10*time.Second,
	)
	defer cancel()

	started := make(chan struct{}, 2)
	logger := &commentMessagesQueryLogger{}
	logger.beforeQuery = func() {
		if logger.queries.Load() > 2 {
			started <- struct{}{}

			<-ctx.Done()
		}
	}
	db.Logger(logger)

	done := make(chan error, 1)

	go func() {
		_, err := server.GetMessages(ctx, &GetMessagesRequest{
			ItemId: 10, TypeId: CommentsType_PICTURES_TYPE_ID,
			Limit: 100, Fields: &CommentMessageFields{IsNew: true, UserVote: true},
		})
		done <- err
	}()

	for range 2 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("both batched reads must start together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
	require.Zero(t, logger.updates.Load())
}
