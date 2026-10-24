package goautowp

import (
	"context"
	"database/sql"
	"net"
	"sync"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/users"
	"github.com/stretchr/testify/require"
)

func newVisitTestRepository(t *testing.T) (*users.Repository, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	db, database, logger := newRelatedGroupTestDB(t)
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE users (id bigint PRIMARY KEY, last_online timestamptz, last_ip inet);
		INSERT INTO users (id, last_online) VALUES (1, NULL), (2, NULL), (3, NULL);
	`)
	require.NoError(t, err)

	return users.NewRepository(db, "", nil, nil, config.KeycloakConfig{}, 0, nil), database, logger
}

func visitTestState(t *testing.T, database *sql.DB, userID int64) (time.Time, string) {
	t.Helper()

	var (
		online time.Time
		ip     sql.NullString
	)

	err := database.QueryRowContext(t.Context(), "SELECT last_online, HOST(last_ip) FROM users WHERE id = $1", userID).
		Scan(&online, &ip)
	require.NoError(t, err)

	return online, ip.String
}

func TestRegisterVisitSkipsRepeatedDatabaseQueries(t *testing.T) {
	t.Parallel()

	repository, database, logger := newVisitTestRepository(t)
	ip := net.ParseIP("192.0.2.1")
	require.NoError(t, repository.RegisterVisit(t.Context(), 1, ip))
	require.EqualValues(t, 2, logger.queries.Load())
	online, storedIP := visitTestState(t, database, 1)
	require.Equal(t, ip.String(), storedIP)
	require.WithinDuration(t, time.Now(), online, time.Second)

	for range 100 {
		require.NoError(t, repository.RegisterVisit(t.Context(), 1, net.IPv4(192, 0, 2, 1)))
	}

	require.EqualValues(t, 2, logger.queries.Load(), "same-IP repeats do not access the database")
	require.NoError(t, repository.RegisterVisit(t.Context(), 1, net.ParseIP("2001:db8::1")))
	require.EqualValues(t, 4, logger.queries.Load())
	afterChange, storedIP := visitTestState(t, database, 1)
	require.Equal(t, "2001:db8::1", storedIP)
	require.Equal(t, online, afterChange, "an IP-only change preserves last_online")
	require.NoError(t, repository.RegisterVisit(t.Context(), 1, ip))
	require.EqualValues(t, 6, logger.queries.Load(), "returning to the previous IP must save it again")
	_, storedIP = visitTestState(t, database, 1)
	require.Equal(t, ip.String(), storedIP)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()
	require.ErrorIs(t, repository.RegisterVisit(ctx, 1, ip), context.Canceled)
	require.EqualValues(t, 6, logger.queries.Load())

	logger.queries.Store(0)

	const callers = 64

	var workers sync.WaitGroup

	results := make(chan error, callers)
	for range callers {
		workers.Go(func() { results <- repository.RegisterVisit(t.Context(), 2, ip) })
	}

	workers.Wait()

	for range callers {
		require.NoError(t, <-results)
	}

	require.EqualValues(t, 2, logger.queries.Load(), "concurrent misses perform one visit check and write")
}

func TestRegisterVisitRetriesFailedWritesAndMissingUsers(t *testing.T) {
	t.Parallel()

	repository, database, logger := newVisitTestRepository(t)
	_, err := database.ExecContext(
		t.Context(),
		`ALTER TABLE users ADD CONSTRAINT visit_test_ip CHECK (last_ip IS NULL)`,
	)
	require.NoError(t, err)

	ip := net.ParseIP("192.0.2.1")
	for range 2 {
		require.Error(t, repository.RegisterVisit(t.Context(), 1, ip))
	}

	require.EqualValues(t, 4, logger.queries.Load(), "failed writes cannot populate the cache")
	_, err = database.ExecContext(t.Context(), "ALTER TABLE users DROP CONSTRAINT visit_test_ip")
	require.NoError(t, err)
	require.NoError(t, repository.RegisterVisit(t.Context(), 1, ip))
	require.NoError(t, repository.RegisterVisit(t.Context(), 1, ip))
	require.EqualValues(t, 6, logger.queries.Load())
	_, storedIP := visitTestState(t, database, 1)
	require.Equal(t, ip.String(), storedIP)

	logger.queries.Store(0)
	require.NoError(t, repository.RegisterVisit(t.Context(), 99, ip))
	require.NoError(t, repository.RegisterVisit(t.Context(), 99, ip))
	require.EqualValues(t, 2, logger.queries.Load(), "missing users are not cached")
	_, err = database.ExecContext(t.Context(), "INSERT INTO users (id) VALUES (99)")
	require.NoError(t, err)
	require.NoError(t, repository.RegisterVisit(t.Context(), 99, ip))
	require.NoError(t, repository.RegisterVisit(t.Context(), 99, ip))
	require.EqualValues(t, 4, logger.queries.Load())
}

func TestRegisterVisitWithoutIPPreservesStoredAddress(t *testing.T) {
	t.Parallel()

	repository, database, logger := newVisitTestRepository(t)
	require.NoError(t, repository.RegisterVisit(t.Context(), 3, nil))
	require.NoError(t, repository.RegisterVisit(t.Context(), 3, nil))
	require.EqualValues(t, 2, logger.queries.Load())
	online, storedIP := visitTestState(t, database, 3)
	require.Empty(t, storedIP)
	require.NoError(t, repository.RegisterVisit(t.Context(), 3, net.ParseIP("192.0.2.3")))
	require.EqualValues(t, 4, logger.queries.Load())
	afterChange, storedIP := visitTestState(t, database, 3)
	require.Equal(t, "192.0.2.3", storedIP)
	require.Equal(t, online, afterChange)
	require.NoError(t, repository.RegisterVisit(t.Context(), 3, nil))
	require.NoError(t, repository.RegisterVisit(t.Context(), 3, nil))
	require.EqualValues(t, 5, logger.queries.Load(), "unknown IPs never clear the persisted address")
	_, storedIP = visitTestState(t, database, 3)
	require.Equal(t, "192.0.2.3", storedIP)
}
