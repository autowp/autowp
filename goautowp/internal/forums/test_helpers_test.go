package forums

import (
	"database/sql"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/config"
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

	pool, err := sql.Open("postgres", config.LoadConfig("../..").PostgresDSN)
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
				t.Fatal("independent forum reads did not start together")
			}
		}
	}
}
