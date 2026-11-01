package donations

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres"
	_ "github.com/lib/pq"
	"github.com/stretchr/testify/require"
)

func TestTransactions(t *testing.T) {
	t.Parallel()

	pool, err := sql.Open("postgres", config.LoadConfig("../..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	_, err = pool.ExecContext(t.Context(), `
		CREATE TEMP TABLE transaction (sum integer, currency text, date timestamptz,
			contributor text, purpose text, user_id bigint);
		INSERT INTO transaction VALUES
		(10, 'EUR', CURRENT_DATE - INTERVAL '7 months', 'Old', 'Old', 1),
		(20, 'EUR', CURRENT_DATE - INTERVAL '3 months', 'Earlier', 'First', 7),
		(30, 'EUR', CURRENT_DATE - INTERVAL '1 month', 'Later', 'Second', NULL),
		(40, 'EUR', CURRENT_DATE - INTERVAL '6 months', 'Boundary', 'Excluded', 1);
	`)
	require.NoError(t, err)

	repository := NewRepository(goqu.New("postgres", pool))
	rows, err := repository.Transactions(t.Context())
	require.NoError(t, err)
	require.Len(t, rows, 2)
	require.EqualValues(t, 20, rows[0].Sum)
	require.Equal(t, "Earlier", rows[0].Contributor)
	require.True(t, rows[0].UserID.Valid)
	require.EqualValues(t, 7, rows[0].UserID.Int64)
	require.EqualValues(t, 30, rows[1].Sum)
	require.False(t, rows[1].UserID.Valid)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.Transactions(ctx)
	require.ErrorIs(t, err, context.Canceled)
}
