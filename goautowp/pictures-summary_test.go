package goautowp

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/types/known/emptypb"
)

func newPicturesSummaryTestServer(t *testing.T) (*PicturesGRPCServer, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	cfg := config.LoadConfig(".")
	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	database.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	_, err = database.ExecContext(t.Context(), `CREATE TEMP TABLE picture (id bigint, owner_id bigint, status text)`)
	require.NoError(t, err)

	_, err = database.ExecContext(t.Context(), `
  INSERT INTO picture (id, owner_id, status) VALUES
   (1, 1, $1), (2, 1, $1), (3, 1, $1), (4, 1, $2), (5, 1, $2),
   (6, 1, $3), (7, 1, $4), (8, 2, $1), (9, 2, $2), (10, NULL, $1);
 `, schema.PictureStatusAccepted, schema.PictureStatusInbox,
		schema.PictureStatusRemoving, schema.PictureStatusRemoved)
	require.NoError(t, err)

	logger := &itemBatchQueryLogger{}
	goquDB := goqu.New("postgres", database)
	goquDB.Logger(logger)
	repository := pictures.NewRepository(goquDB, nil, nil, nil, nil,
		config.DuplicateFinderConfig{}, nil, nil, nil, nil)

	return &PicturesGRPCServer{repository: repository, auth: &Auth{}}, database, logger
}

func TestPicturesUserSummaryParallelCounts(t *testing.T) {
	t.Parallel()

	server, _, logger := newPicturesSummaryTestServer(t)
	unblock := blockItemExtractionQueries(t, logger, 2)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 1})

	ctx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	type summaryResult struct {
		summary *PicturesUserSummary
		err     error
	}

	done := make(chan summaryResult, 1)

	go func() {
		summary, err := server.GetUserSummary(ctx, &emptypb.Empty{})
		done <- summaryResult{summary: summary, err: err}
	}()

	// Block before connection acquisition to prove both counts start together,
	// while keeping the temporary-table fixture on a single connection.
	unblock()

	result := <-done
	require.NoError(t, result.err)

	expected := &PicturesUserSummary{AcceptedCount: 3, InboxCount: 2}
	require.True(t, proto.Equal(expected, result.summary), "unexpected counts: %s", result.summary)
	require.EqualValues(t, 2, logger.queries.Load(), "one query per counter")

	for _, testCase := range []struct {
		userID   int64
		expected *PicturesUserSummary
	}{
		{userID: 2, expected: &PicturesUserSummary{AcceptedCount: 1, InboxCount: 1}},
		{userID: 99, expected: &PicturesUserSummary{}},
	} {
		otherCtx := context.WithValue(ctx, authenticatedUserKey{}, UserContext{UserID: testCase.userID})
		summary, err := server.GetUserSummary(otherCtx, &emptypb.Empty{})
		require.NoError(t, err)
		require.True(t, proto.Equal(testCase.expected, summary), "counts must be scoped to user %d", testCase.userID)
	}
}

func TestPicturesUserSummaryRequiresAuthentication(t *testing.T) {
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
				ctx = context.WithValue(ctx, authenticatedUserKey{}, UserContext{})
			}

			// Rejected callers must return before accessing the absent repository.
			server := &PicturesGRPCServer{auth: &Auth{}}
			result, err := server.GetUserSummary(ctx, &emptypb.Empty{})
			require.Equal(t, codes.Unauthenticated, status.Code(err))
			require.Nil(t, result)
		})
	}
}

func TestPicturesUserSummaryPropagatesCountFailure(t *testing.T) {
	t.Parallel()

	server, database, _ := newPicturesSummaryTestServer(t)
	_, err := database.ExecContext(t.Context(), `ALTER TABLE picture DROP COLUMN status`)
	require.NoError(t, err)

	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 1})

	ctx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	result, err := server.GetUserSummary(ctx, &emptypb.Empty{})
	require.Equal(t, codes.Internal, status.Code(err))
	require.Contains(t, status.Convert(err).Message(), "status")
	require.Nil(t, result)
	require.NoError(t, ctx.Err(), "failed counts must not wait for the parent deadline")
	require.Zero(t, database.Stats().InUse, "all started queries must finish before returning")
}

func TestPicturesUserSummaryHonorsCancellation(t *testing.T) {
	t.Parallel()

	server, database, logger := newPicturesSummaryTestServer(t)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 1})

	parentCtx, stop := context.WithTimeout(ctx, 5*time.Second)
	defer stop()

	ctx, cancel := context.WithCancel(parentCtx)
	defer cancel()

	// Occupy the fixture's only connection so both counts have to await it.
	connection, err := database.Conn(ctx)
	require.NoError(t, err)

	defer func() { require.NoError(t, connection.Close()) }()

	started := make(chan struct{}, 2)
	logger.beforeQuery = func() { started <- struct{}{} }
	done := make(chan error, 1)

	go func() {
		_, err := server.GetUserSummary(ctx, &emptypb.Empty{})
		done <- err
	}()

	for range 2 {
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
