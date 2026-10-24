package goautowp

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/mosts"
	"github.com/autowp/goautowp/query"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func newMostsVehicleTypesTestRepository(
	t *testing.T,
) (*mosts.Repository, *items.Repository, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	db, pool, logger := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
  CREATE TEMP TABLE vehicle_type (id bigint, parent_id bigint, catname text, name_rp text, position integer);
  INSERT INTO vehicle_type VALUES
   (10, NULL, 'root10', 'root10', 2), (20, NULL, 'root20', 'root20', 1),
   (30, NULL, 'root30', 'root30', 3), (40, NULL, 'empty', 'empty', 4),
   (11, 10, 'child11', 'child11', 5), (12, 10, 'child12', 'child12', 1),
   (21, 20, 'child21', 'child21', 3), (111, 11, 'grandchild', 'grandchild', 2);
  CREATE TEMP TABLE vehicle_type_parent (id bigint, parent_id bigint);
  INSERT INTO vehicle_type_parent SELECT id, id FROM vehicle_type;
  INSERT INTO vehicle_type_parent VALUES (11, 10), (12, 10), (21, 20), (111, 11), (111, 10);
  CREATE TEMP TABLE item_vehicle_type (item_id bigint, vehicle_type_id bigint);
  INSERT INTO item_vehicle_type VALUES (1001, 11), (1002, 11), (1003, 30), (2001, 21);
  CREATE TEMP TABLE item_parent_cache (item_id bigint, parent_id bigint);
  INSERT INTO item_parent_cache VALUES (1001, 100), (1002, 100), (1003, 100), (2001, 200);
 `)
	require.NoError(t, err)

	repository := items.NewRepository(db, 0, nil, nil, nil)

	return mosts.NewRepository(db, repository, nil), repository, pool, logger
}

func TestMostsVehicleTypesBatch(t *testing.T) {
	t.Parallel()

	repository, itemRepository, pool, logger := newMostsVehicleTypesTestRepository(t)
	actual, err := repository.VehicleTypes(t.Context(), 0)
	require.NoError(t, err)
	require.EqualValues(t, 2, logger.queries.Load())
	require.Equal(t, []mosts.CarTypesItem{
		{Catname: "root20", NameRp: "root20", Childs: []mosts.CarTypesItem{{Catname: "child21", NameRp: "child21"}}},
		{Catname: "root10", NameRp: "root10", Childs: []mosts.CarTypesItem{
			{Catname: "child12", NameRp: "child12"}, {Catname: "child11", NameRp: "child11"},
		}},
		{Catname: "root30", NameRp: "root30", Childs: []mosts.CarTypesItem{}},
		{Catname: "empty", NameRp: "empty", Childs: []mosts.CarTypesItem{}},
	}, actual)

	// Compare to the original one-child-query-per-root implementation.
	logger.queries.Store(0)

	roots, err := itemRepository.VehicleTypes(t.Context(), &query.VehicleTypeListOptions{NoParent: true})
	require.NoError(t, err)

	expected := make([]mosts.CarTypesItem, 0, len(roots))
	for _, root := range roots {
		children, err := itemRepository.VehicleTypes(t.Context(), &query.VehicleTypeListOptions{ParentID: root.ID})
		require.NoError(t, err)

		row := mosts.CarTypesItem{Catname: root.Catname, NameRp: root.NameRp, Childs: []mosts.CarTypesItem{}}
		for _, child := range children {
			row.Childs = append(row.Childs, mosts.CarTypesItem{Catname: child.Catname, NameRp: child.NameRp})
		}

		expected = append(expected, row)
	}

	require.Equal(t, expected, actual)
	require.EqualValues(t, 5, logger.queries.Load())

	logger.queries.Store(0)

	filtered, err := repository.VehicleTypes(t.Context(), 100)
	require.NoError(t, err)
	require.EqualValues(t, 2, logger.queries.Load())
	require.Equal(t, []mosts.CarTypesItem{
		{Catname: "root10", NameRp: "root10", Childs: []mosts.CarTypesItem{{Catname: "child11", NameRp: "child11"}}},
		{Catname: "root30", NameRp: "root30", Childs: []mosts.CarTypesItem{}},
	}, filtered)

	logger.queries.Store(0)

	empty, err := repository.VehicleTypes(t.Context(), 999)
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
	require.EqualValues(t, 1, logger.queries.Load())

	logger.queries.Store(0)

	emptyChildren, err := itemRepository.VehicleTypesByParentIDs(t.Context(), nil, query.VehicleTypeListOptions{})
	require.NoError(t, err)
	require.Empty(t, emptyChildren)
	require.Zero(t, logger.queries.Load())

	_, err = pool.ExecContext(t.Context(), "DELETE FROM pg_temp.vehicle_type WHERE parent_id IS NULL")
	require.NoError(t, err)
	empty, err = repository.VehicleTypes(t.Context(), 0)
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
}

func TestMostsVehicleTypesBatchCancellationAndFailure(t *testing.T) {
	t.Parallel()

	repository, _, pool, logger := newMostsVehicleTypesTestRepository(t)

	ctx, cancel := context.WithCancel(t.Context())
	defer cancel()

	// Cancellation after roots have loaded must stop the child batch.
	logger.beforeQuery = func() {
		if logger.queries.Load() == 2 {
			cancel()
		}
	}
	result, err := repository.VehicleTypes(ctx, 100)
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, result)
	require.Zero(t, pool.Stats().InUse)

	logger.beforeQuery = nil

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.vehicle_type RENAME COLUMN position TO broken_position")
	require.NoError(t, err)
	result, err = repository.VehicleTypes(t.Context(), 0)
	require.ErrorContains(t, err, "position")
	require.Nil(t, result)
}

// Keep the query builder's brand filter while selecting multiple parents.
func TestVehicleTypeBatchParentFilter(t *testing.T) {
	t.Parallel()

	options := query.VehicleTypeListOptions{ParentIDs: []int64{10, 20}, Childs: &query.VehicleTypeParentsListOptions{
		ItemVehicleTypeByID: &query.ItemVehicleTypeListOptions{
			ItemParentCacheAncestor: &query.ItemParentCacheListOptions{ParentID: 100},
		},
	}}
	dataset, err := options.Select(goqu.New("postgres", nil), query.VehicleTypeTableAlias)
	require.NoError(t, err)
	sqlText, _, err := dataset.ToSQL()
	require.NoError(t, err)
	require.Contains(t, sqlText, `"vt"."parent_id" IN (10, 20)`)
	require.Contains(t, sqlText, "EXISTS")
}
