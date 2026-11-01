package items_test

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/stretchr/testify/require"
)

func TestFirstCataloguePathsMatchesRecursiveSelection(t *testing.T) {
	t.Parallel()

	db, database, logger := testutil.NewPostgres(t, "../..")
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE item (id bigint PRIMARY KEY, catname text, item_type_id integer);
		CREATE TEMP TABLE item_parent (
			item_id bigint, parent_id bigint, catname text, type integer, manual_catname boolean DEFAULT false
		);
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO item VALUES
		(1, 'brand', $1), (2, 'category', $2), (3, 'person', $3),
		(10, 'root', $4), (11, 'child', $4), (12, 'tuning', $4), (13, 'missing', $4),
		(14, 'skip-category', $4), (15, 'other-root', $4)`,
		schema.ItemTableItemTypeIDBrand, schema.ItemTableItemTypeIDCategory,
		schema.ItemTableItemTypeIDPerson, schema.ItemTableItemTypeIDVehicle)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO item_parent (item_id, parent_id, catname, type) VALUES
		(10, 1, 'model', $1), (11, 10, 'child', $1),
		(12, 10, 'tuned', $2), (12, 15, 'stock', $1),
		(15, 1, 'other', $1), (14, 2, 'category-path', $1), (14, 1, 'brand-path', $2)`,
		schema.ItemParentTypeDefault, schema.ItemParentTypeTuning)
	require.NoError(t, err)

	repository := items.NewRepository(db, 0, nil, nil, nil)
	ids := []int64{11, 12, 13, 14, 10, 1, 2, 3, 999, 11}
	result, err := repository.FirstCataloguePaths(t.Context(), ids)
	require.NoError(t, err)

	batchQueries := logger.Queries.Load()
	require.EqualValues(t, 4, batchQueries, "shared ancestors are loaded in two levels")

	for _, id := range ids {
		expected, err := repository.CataloguePaths(t.Context(), id, items.CataloguePathOptions{BreakOnFirst: true})
		require.NoError(t, err)

		if len(expected) == 0 {
			require.Nil(t, result[id])

			continue
		}

		require.Equal(t, &expected[0], result[id])
	}

	logger.Queries.Store(0)

	_, err = database.ExecContext(t.Context(), `INSERT INTO item (id, item_type_id)
		SELECT n, $1 FROM generate_series(100, 149) n`, schema.ItemTableItemTypeIDVehicle)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO item_parent (item_id, parent_id, catname, type)
		SELECT n, 10, 'extra-' || n, $1 FROM generate_series(100, 149) n`, schema.ItemParentTypeDefault)
	require.NoError(t, err)

	for id := int64(100); id < 150; id++ {
		ids = append(ids, id)
	}

	_, err = repository.FirstCataloguePaths(t.Context(), ids)
	require.NoError(t, err)
	require.Equal(t, batchQueries, logger.Queries.Load(), "adding groups at the same depth adds no SQL queries")

	logger.Queries.Store(0)

	empty, err := repository.FirstCataloguePaths(t.Context(), nil)
	require.NoError(t, err)
	require.Empty(t, empty)
	require.Zero(t, logger.Queries.Load())
	_, err = repository.FirstCataloguePaths(t.Context(), []int64{0})
	require.Error(t, err)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.FirstCataloguePaths(ctx, ids)
	require.ErrorIs(t, err, context.Canceled)
}

func TestRelatedCarGroupsBatchesAncestors(t *testing.T) {
	t.Parallel()

	db, database, logger := testutil.NewPostgres(t, "../..")
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE item (id bigint, item_type_id integer);
		CREATE TEMP TABLE item_parent (item_id bigint, parent_id bigint);
		CREATE TEMP TABLE item_parent_cache (item_id bigint, parent_id bigint, diff integer);
		INSERT INTO item_parent VALUES (11, 1000), (12, 1000), (21, 1000);
		INSERT INTO item_parent_cache VALUES
			(11, 10, 1), (12, 10, 1), (21, 20, 1), (11, 11, 0), (11, 1000, 2);
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO item VALUES
		(10, $1), (11, $1), (12, $1), (20, $2), (21, $1), (1000, $3)`,
		schema.ItemTableItemTypeIDVehicle, schema.ItemTableItemTypeIDEngine, schema.ItemTableItemTypeIDBrand)
	require.NoError(t, err)

	repository := items.NewRepository(db, 0, nil, nil, nil)
	groups, err := repository.RelatedCarGroups(t.Context(), 1000)
	require.NoError(t, err)
	require.Len(t, groups, 2)
	require.ElementsMatch(t, []int64{11, 12}, groups[10])
	require.Equal(t, []int64{21}, groups[21])
	require.EqualValues(t, 2, logger.Queries.Load())

	_, err = database.ExecContext(t.Context(), `INSERT INTO item_parent
		SELECT n, 1000 FROM generate_series(100, 149) n`)
	require.NoError(t, err)
	logger.Queries.Store(0)

	groups, err = repository.RelatedCarGroups(t.Context(), 1000)
	require.NoError(t, err)
	require.Len(t, groups, 52)
	require.EqualValues(t, 2, logger.Queries.Load(), "one child lookup and one shared ancestor lookup")

	logger.Queries.Store(0)

	empty, err := repository.RelatedCarGroups(t.Context(), 999)
	require.NoError(t, err)
	require.Empty(t, empty)
	require.EqualValues(t, 1, logger.Queries.Load())
}
