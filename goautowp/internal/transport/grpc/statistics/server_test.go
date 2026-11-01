package statistics

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/statistics"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres"
	_ "github.com/lib/pq"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

type queryLogger struct {
	started chan struct{}
}

func (s *queryLogger) Printf(_ string, _ ...interface{}) {
	s.started <- struct{}{}
}

func newTestServer(t *testing.T) (*Server, *sql.DB, *goqu.Database) {
	t.Helper()

	pool, err := sql.Open("postgres", config.LoadConfig("../../../..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	_, err = pool.ExecContext(t.Context(), `
		CREATE TEMP TABLE users (id bigint, deleted boolean, green boolean, identity text,
			last_online timestamptz, pictures_total bigint);
		CREATE TEMP TABLE item (id bigint);
		CREATE TEMP TABLE comment_message (deleted boolean);
		CREATE TEMP TABLE picture (filesize bigint);
	`)
	require.NoError(t, err)

	db := goqu.New("postgres", pool)

	return NewServer(statistics.NewRepository(db), config.AboutConfig{Developer: "Developer"}), pool, db
}

func TestAboutDataQueryFailure(t *testing.T) {
	t.Parallel()

	server, pool, _ := newTestServer(t)
	actual, err := server.GetAboutData(t.Context(), &emptypb.Empty{})
	require.NoError(t, err)
	require.Equal(t, "Developer", actual.GetDeveloper())
	require.Zero(t, actual.GetTotalPictures())

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE comment_message RENAME COLUMN deleted TO broken_deleted")
	require.NoError(t, err)
	actual, err = server.GetAboutData(t.Context(), &emptypb.Empty{})
	require.Nil(t, actual, "SQL errors must not produce a successful response filled with zeroes")
	require.Equal(t, codes.Internal, status.Code(err))
	require.Zero(t, pool.Stats().InUse, "all parallel reads must join before returning")
}

func TestAboutDataCancellation(t *testing.T) {
	t.Parallel()

	server, pool, db := newTestServer(t)

	ctx, stop := context.WithTimeout(t.Context(), 5*time.Second)
	defer stop()

	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	defer func() { require.NoError(t, connection.Close()) }()

	logger := &queryLogger{started: make(chan struct{}, 5)}
	db.Logger(logger)

	requestCtx, cancel := context.WithCancel(ctx)
	defer cancel()

	done := make(chan error, 1)

	go func() {
		_, callErr := server.GetAboutData(requestCtx, &emptypb.Empty{})
		done <- callErr
	}()

	for range 5 {
		select {
		case <-logger.started:
		case <-ctx.Done():
			t.Fatal("parallel reads did not start")
		}
	}

	cancel()

	err = <-done
	require.Equal(t, codes.Internal, status.Code(err))
	require.Contains(t, status.Convert(err).Message(), context.Canceled.Error())
	require.NoError(t, ctx.Err())
	require.Equal(t, 1, pool.Stats().InUse, "only the connection held by the fixture may remain")
}
