package pictures

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func TestAcceptedCountsByOwnerIDs(t *testing.T) {
	t.Parallel()

	empty, err := (&Repository{}).AcceptedCountsByOwnerIDs(t.Context(), nil)
	require.NoError(t, err)
	require.Empty(t, empty)

	cfg := config.LoadConfig("..")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, db.Close()) })
	_, err = db.ExecContext(t.Context(), `
		CREATE TEMP TABLE picture (id integer PRIMARY KEY, owner_id bigint, status text NOT NULL);
		INSERT INTO picture VALUES
			(1, 7, 'accepted'), (2, 7, 'accepted'), (3, 7, 'inbox'),
			(4, 8, 'accepted'), (5, 8, 'removed'), (6, NULL, 'accepted'), (7, 99, 'accepted');
	`)
	require.NoError(t, err)

	repository := &Repository{db: goqu.New("postgres", db)}
	counts, err := repository.AcceptedCountsByOwnerIDs(t.Context(), []int64{7, 8, 9})
	require.NoError(t, err)
	require.Equal(t, map[int64]int32{7: 2, 8: 1}, counts)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.AcceptedCountsByOwnerIDs(ctx, []int64{7})
	require.ErrorIs(t, err, context.Canceled)
}
