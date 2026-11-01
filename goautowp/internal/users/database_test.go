package users_test

import (
	"database/sql"
	"sync/atomic"
	"testing"

	"github.com/autowp/goautowp/internal/config"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

type queryCounter struct {
	queries atomic.Int64
}

func (s *queryCounter) Printf(_ string, _ ...any) {
	s.queries.Add(1)
}

func newUsersTestDB(t *testing.T) (*goqu.Database, *sql.DB, *queryCounter) {
	t.Helper()

	database, err := sql.Open("postgres", config.LoadConfig("../..").PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	logger := &queryCounter{}
	db := goqu.New("postgres", database)
	db.Logger(logger)

	return db, database, logger
}
