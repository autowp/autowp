package itemofday

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	_ "github.com/lib/pq"                               // enable postgres driver
	"github.com/stretchr/testify/require"
)

func createRepository(t *testing.T) *Repository {
	t.Helper()

	cfg := config.LoadConfig("..")

	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquDB := goqu.New("postgres", db)

	s := NewRepository(goquDB)

	return s
}

func TestPickItemOfDay(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.Pick(t.Context())
	require.NoError(t, err)
}
