package testutil

import (
	"database/sql"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // register the fixture's SQL dialect
	_ "github.com/lib/pq"                               // register the fixture's database driver
	"github.com/stretchr/testify/require"
)

const queryBarrierTimeout = 5 * time.Second

type QueryLogger struct {
	Queries     atomic.Int64
	BeforeQuery func()
}

func (s *QueryLogger) Printf(_ string, _ ...any) {
	s.Queries.Add(1)

	if s.BeforeQuery != nil {
		s.BeforeQuery()
	}
}

// NewPostgres keeps temporary-table fixtures on one connection and closes it after the test.
func NewPostgres(t *testing.T, configPath string) (*goqu.Database, *sql.DB, *QueryLogger) {
	t.Helper()

	database, err := sql.Open("postgres", config.LoadConfig(configPath).PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	logger := &QueryLogger{}
	db := goqu.New("postgres", database)
	db.Logger(logger)

	return db, database, logger
}

// BlockQueries holds the first count queries until the returned function observes and releases them.
func BlockQueries(t *testing.T, logger *QueryLogger, count int) func() {
	t.Helper()

	started := make(chan struct{}, count)
	release := make(chan struct{})

	var once sync.Once

	unblock := func() { once.Do(func() { close(release) }) }
	t.Cleanup(unblock)

	var observed atomic.Int64

	logger.BeforeQuery = func() {
		if observed.Add(1) > int64(count) {
			return
		}

		started <- struct{}{}

		<-release
	}

	return func() {
		t.Helper()

		defer unblock()

		timer := time.NewTimer(queryBarrierTimeout)
		defer timer.Stop()

		for range count {
			select {
			case <-started:
			case <-timer.C:
				t.Fatal("independent queries did not start together")
			}
		}
	}
}
