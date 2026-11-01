package messaging

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/messaging"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/types/known/emptypb"
)

func newMessageSummaryTestServer(t *testing.T) (*Server, *sql.DB, *queryLogger) {
	t.Helper()

	cfg := config.LoadConfig("../../../..")
	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	_, err = database.ExecContext(t.Context(), `
  CREATE TEMP TABLE personal_message (
   from_user_id bigint, to_user_id bigint, readen boolean,
   deleted_by_from boolean DEFAULT false, deleted_by_to boolean DEFAULT false,
   created_at timestamptz DEFAULT NOW()
  );
  INSERT INTO personal_message (from_user_id, to_user_id, readen) VALUES
   (2, 1, false), (2, 1, NULL), (2, 1, true), (2, 1, true), (2, 1, true),
   (1, 2, false), (1, 2, true), (1, 3, false),
   (NULL, 1, true), (NULL, 1, NULL), (NULL, 1, true), (2, 3, false);
  INSERT INTO personal_message (from_user_id, to_user_id, readen, deleted_by_from) VALUES
   (1, 3, true, NULL), (1, 2, false, true);
  INSERT INTO personal_message (from_user_id, to_user_id, readen, deleted_by_to) VALUES
   (2, 1, false, true), (2, 1, false, NULL), (NULL, 1, false, true);
 `)
	require.NoError(t, err)

	logger := &queryLogger{}
	goquDB := goqu.New("postgres", database)
	goquDB.Logger(logger)
	repository := messaging.NewRepository(goquDB, nil, nil, nil)

	return NewServer(repository, &authn.Service{}), database, logger
}

func TestMessagingSummaryParallelCounts(t *testing.T) {
	t.Parallel()

	server, _, logger := newMessageSummaryTestServer(t)
	unblock := blockQueries(t, logger, messagingSummaryParallelism)
	ctx := authn.WithUser(t.Context(), authn.User{UserID: 1})

	ctx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	type summaryResult struct {
		summary *pb.MessageSummary
		err     error
	}

	done := make(chan summaryResult, 1)

	go func() {
		summary, err := server.GetMessagesSummary(ctx, &emptypb.Empty{})
		done <- summaryResult{summary: summary, err: err}
	}()

	// Blocking before connection acquisition lets the temporary-table fixture
	// prove four counts start together even with a single SQL connection.
	unblock()

	result := <-done
	require.NoError(t, result.err)

	expected := &pb.MessageSummary{
		InboxCount: 5, InboxNewCount: 2, SentCount: 4, SystemCount: 3, SystemNewCount: 1,
	}
	require.True(t, proto.Equal(expected, result.summary), "unexpected counts: %s", result.summary)
	require.EqualValues(t, 5, logger.queries.Load(), "one query per counter")

	otherCtx := authn.WithUser(ctx, authn.User{UserID: 99})
	empty, err := server.GetMessagesSummary(otherCtx, &emptypb.Empty{})
	require.NoError(t, err)
	require.True(t, proto.Equal(&pb.MessageSummary{}, empty), "counts must be scoped to the authenticated user")
}

func TestMessagingSummaryRequiresAuthentication(t *testing.T) {
	t.Parallel()

	for _, testCase := range []struct {
		name      string
		anonymous bool
	}{
		{name: "missing metadata"},
		{name: "anonymous", anonymous: true},
	} {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			ctx := t.Context()
			if testCase.anonymous {
				ctx = authn.WithUser(ctx, authn.User{})
			}

			// No repository is available: rejected callers must not start SQL reads.
			server := &Server{auth: &authn.Service{}}
			result, err := server.GetMessagesSummary(ctx, &emptypb.Empty{})
			require.Equal(t, codes.Unauthenticated, status.Code(err))
			require.Nil(t, result)
		})
	}
}

func TestMessagingSummaryPropagatesCountFailure(t *testing.T) {
	t.Parallel()

	server, database, _ := newMessageSummaryTestServer(t)
	_, err := database.ExecContext(t.Context(), `ALTER TABLE personal_message DROP COLUMN readen`)
	require.NoError(t, err)

	ctx := authn.WithUser(t.Context(), authn.User{UserID: 1})

	ctx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	result, err := server.GetMessagesSummary(ctx, &emptypb.Empty{})
	require.Equal(t, codes.Internal, status.Code(err))
	require.Contains(t, status.Convert(err).Message(), "readen")
	require.Nil(t, result)
	require.NoError(t, ctx.Err(), "failed counts must not wait for the parent deadline")
	require.Zero(t, database.Stats().InUse, "all started queries must finish before returning")
}

func TestMessagingSummaryHonorsCancellation(t *testing.T) {
	t.Parallel()

	server, database, logger := newMessageSummaryTestServer(t)
	ctx := authn.WithUser(t.Context(), authn.User{UserID: 1})

	parentCtx, stop := context.WithTimeout(ctx, 5*time.Second)
	defer stop()

	ctx, cancel := context.WithCancel(parentCtx)
	defer cancel()

	// Occupy the fixture's only connection so all counts have to await it.
	connection, err := database.Conn(ctx)
	require.NoError(t, err)

	defer func() { require.NoError(t, connection.Close()) }()

	started := make(chan struct{}, messagingSummaryParallelism)

	var observed atomic.Int32

	logger.beforeQuery = func() {
		if observed.Add(1) <= messagingSummaryParallelism {
			started <- struct{}{}
		}
	}
	done := make(chan error, 1)

	go func() {
		_, err := server.GetMessagesSummary(ctx, &emptypb.Empty{})
		done <- err
	}()

	for range messagingSummaryParallelism {
		select {
		case <-started:
		case <-parentCtx.Done():
			t.Fatal("counts did not start")
		}
	}

	cancel()

	err = <-done
	require.Equal(t, codes.Internal, status.Code(err), "preserve the handler's existing error mapping")
	require.Contains(t, status.Convert(err).Message(), context.Canceled.Error())
	require.NoError(t, parentCtx.Err())
	require.Equal(t, 1, database.Stats().InUse, "only the held fixture connection may remain in use")
}
