package textstorage

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres"
	"github.com/stretchr/testify/require"
)

func TestRevisions(t *testing.T) {
	t.Parallel()

	pool, err := sql.Open("postgres", config.LoadConfig("../..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	_, err = pool.ExecContext(t.Context(), `
		CREATE TEMP TABLE textstorage_text (id bigint, revision bigint);
		CREATE TEMP TABLE textstorage_revision (text_id bigint, revision bigint, text text, user_id bigint);
		INSERT INTO textstorage_text VALUES (1, 2);
		INSERT INTO textstorage_revision VALUES (1, 1, 'First', 7), (1, 2, 'Second', 8);
	`)
	require.NoError(t, err)

	repository := New(goqu.New("postgres", pool))
	latest, err := repository.LatestRevision(t.Context(), 1)
	require.NoError(t, err)
	require.EqualValues(t, 2, latest)
	row, err := repository.Revision(t.Context(), 1, 1)
	require.NoError(t, err)
	require.Equal(t, "First", row.Text)
	require.EqualValues(t, 7, row.UserID)
	_, err = repository.LatestRevision(t.Context(), 99)
	require.ErrorIs(t, err, ErrTextNotFound)
	_, err = repository.Revision(t.Context(), 1, 99)
	require.ErrorIs(t, err, ErrRevisionNotFound)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.LatestRevision(ctx, 1)
	require.ErrorIs(t, err, context.Canceled)
	_, err = repository.Revision(ctx, 1, 1)
	require.ErrorIs(t, err, context.Canceled)
}
