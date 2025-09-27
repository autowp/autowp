package itemofday

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/mysql" // enable mysql dialect
	_ "github.com/go-sql-driver/mysql"               // enable mysql driver
	"github.com/stretchr/testify/require"
)

func createRepository(t *testing.T) *Repository {
	t.Helper()

	cfg := config.LoadConfig("..")

	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)

	s := NewRepository(goquDB)

	return s
}

func TestGetUserNewMessagesCount(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.Pick(t.Context())
	require.NoError(t, err)
}
