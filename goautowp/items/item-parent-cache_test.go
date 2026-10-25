package items

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func TestCollectParentInfoWithSingleConnection(t *testing.T) {
	t.Parallel()

	db, err := sql.Open("postgres", config.LoadConfig("..").PostgresDSN)
	require.NoError(t, err)
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, db.Close()) })

	ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
	defer cancel()

	_, err = db.ExecContext(ctx, `
CREATE TEMP TABLE item_parent (item_id bigint, parent_id bigint, type smallint);
INSERT INTO item_parent (item_id, parent_id, type) VALUES (4, 3, 1), (3, 2, 0), (2, 1, 0);`)
	require.NoError(t, err)

	repository := NewItemParentCacheRepository(goqu.New("postgres", db))
	parents, err := repository.collectParentInfo(ctx, 4, 1)
	require.NoError(t, err)
	require.Equal(t, map[int64]parentInfo{
		3: {Diff: 1, Tuning: true},
		2: {Diff: 2, Tuning: true},
		1: {Diff: 3, Tuning: true},
	}, parents)

	parents, err = repository.collectParentInfo(ctx, 1, 1)
	require.NoError(t, err)
	require.Empty(t, parents)
}
