package ban

import (
	"database/sql"
	"net"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	_ "github.com/lib/pq"                               // enable postgres driver
	"github.com/stretchr/testify/require"
)

func createBanService(t *testing.T) *Repository {
	t.Helper()

	cfg := config.LoadConfig("..")

	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquDB := goqu.New("postgres", db)

	s, err := NewRepository(goquDB)
	require.NoError(t, err)

	return s
}

func TestAddRemove(t *testing.T) {
	t.Parallel()

	repo := createBanService(t)

	ctx := t.Context()

	ip := net.IPv4(66, 249, 73, 139)

	err := repo.Add(ctx, ip, time.Hour, 1, "Test")
	require.NoError(t, err)

	exists, err := repo.Exists(ctx, ip)
	require.NoError(t, err)
	require.True(t, exists)

	err = repo.Remove(ctx, ip)
	require.NoError(t, err)

	exists, err = repo.Exists(ctx, ip)
	require.NoError(t, err)
	require.False(t, exists)
}
