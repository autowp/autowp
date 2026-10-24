package goautowp

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/stretchr/testify/require"
)

func newCatalogueCountsTestExtractor(t *testing.T) (*ItemExtractor, *itemBatchQueryLogger) {
	t.Helper()

	db, database, logger := newRelatedGroupTestDB(t)
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE item (id bigint, engine_item_id bigint, engine_inherit boolean, is_group boolean);
		CREATE TEMP TABLE item_parent (item_id bigint, parent_id bigint, type integer);
		CREATE TEMP TABLE item_vehicle_type (item_id bigint, vehicle_type_id bigint, inherited boolean);
		INSERT INTO item_vehicle_type VALUES
			(1, 17, false), (1, 19, true), (2, 19, false), (2, 29, false), (3, 19, false),
			(5, 19, NULL), (999, 19, false);
		INSERT INTO item_parent VALUES
			(101, 1, 0), (102, 1, 0), (103, 1, 1), (104, 2, 2), (105, 2, 3),
			(106, 3, 0), (107, 999, 0), (108, 101, 0);
		INSERT INTO item VALUES
			(101, 3, false, false), (102, 3, true, false), (103, 3, false, true),
			(104, 4, false, false), (105, 999, false, false), (106, 1, false, false);
	`)
	require.NoError(t, err)

	container := &Container{
		itemsRepository: items.NewRepository(db, 0, nil, nil, nil),
		attrsRepository: attrs.NewRepository(db, nil, nil, nil, nil, nil, nil),
	}

	return NewItemExtractor(container), logger
}

func TestItemExtractorBatchesCatalogueCounts(t *testing.T) {
	t.Parallel()

	extractor, logger := newCatalogueCountsTestExtractor(t)

	rows := []*items.Item{
		{ID: 2, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 1, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 3, ItemTypeID: schema.ItemTableItemTypeIDEngine},
		{ID: 4, ItemTypeID: schema.ItemTableItemTypeIDEngine},
		{ID: 5, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 2, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 3, ItemTypeID: schema.ItemTableItemTypeIDEngine},
	}
	for id := int64(10); id < 60; id++ {
		rows = append(rows, &items.Item{ID: id, ItemTypeID: schema.ItemTableItemTypeIDEngine})
	}

	fields := &ItemFields{AttrZoneId: true, ChildsCounts: true, EngineVehiclesCount: true}
	result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", UserContext{Roles: []string{users.RoleModer}})
	require.NoError(t, err)
	require.Len(t, result, len(rows))
	require.EqualValues(t, 3, logger.queries.Load(), "one query per field, independent of the number of rows")
	require.EqualValues(t, 3, result[0].GetAttrZoneId(), "own bus types select the bus zone")
	require.EqualValues(t, 1, result[1].GetAttrZoneId(), "inherited bus types are excluded")
	require.EqualValues(t, 5, result[2].GetAttrZoneId(), "engines use the engine zone")
	require.EqualValues(
		t,
		3,
		result[2].GetEngineVehiclesCount(),
		"both inherited references and group items are counted",
	)
	require.Zero(t, result[1].GetEngineVehiclesCount(), "non-engine items do not expose this counter")
	require.NotNil(t, result[4].GetChildsCounts())
	require.Empty(t, result[4].GetChildsCounts())

	engineRows := 0

	for index, row := range rows {
		require.Equal(t, row.ID, result[index].GetId())

		types, err := extractor.container.itemsRepository.VehicleTypeIDs(t.Context(), row.ID, false)
		require.NoError(t, err)
		require.Equal(t, extractor.container.attrsRepository.ZoneIDByVehicleTypeIDs(row.ItemTypeID, types),
			result[index].GetAttrZoneId())

		children, err := extractor.container.itemsRepository.ChildsCounts(t.Context(), row.ID)
		require.NoError(t, err)
		require.ElementsMatch(t, convertChildsCounts(children), result[index].GetChildsCounts())

		if row.ItemTypeID == schema.ItemTableItemTypeIDEngine {
			engineRows++
			count, err := extractor.container.itemsRepository.Count(
				t.Context(),
				query.ItemListOptions{EngineItemID: row.ID},
			)
			require.NoError(t, err)
			require.EqualValues(t, count, result[index].GetEngineVehiclesCount())
		} else {
			require.Zero(t, result[index].GetEngineVehiclesCount())
		}
	}

	require.EqualValues(t, 3+2*len(rows)+engineRows, logger.queries.Load(), "old methods query each row separately")
}

func TestItemExtractorCatalogueCountsSelectionAndPermissions(t *testing.T) {
	t.Parallel()

	extractor, logger := newCatalogueCountsTestExtractor(t)

	rows := []*items.Item{{ID: 3, ItemTypeID: schema.ItemTableItemTypeIDEngine}}
	for _, userCtx := range []UserContext{{}, {UserID: 7}, {UserID: 7, Roles: []string{users.RoleModer}}} {
		result, err := extractor.ExtractRows(t.Context(), rows, nil, "en", userCtx)
		require.NoError(t, err)
		require.Zero(t, result[0].GetAttrZoneId())
		require.Nil(t, result[0].GetChildsCounts())
		require.Zero(t, result[0].GetEngineVehiclesCount())
	}

	require.Zero(t, logger.queries.Load())

	fields := &ItemFields{AttrZoneId: true, ChildsCounts: true, EngineVehiclesCount: true}
	result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", UserContext{})
	require.NoError(t, err)
	require.EqualValues(t, 2, logger.queries.Load(), "anonymous callers do not query engine counters")
	require.EqualValues(t, 5, result[0].GetAttrZoneId())
	require.NotEmpty(t, result[0].GetChildsCounts())
	require.Zero(t, result[0].GetEngineVehiclesCount())

	logger.queries.Store(0)

	result, err = extractor.ExtractRows(
		t.Context(),
		[]*items.Item{{ID: 1, ItemTypeID: schema.ItemTableItemTypeIDVehicle}},
		&ItemFields{EngineVehiclesCount: true},
		"en",
		UserContext{Roles: []string{users.RoleModer}},
	)
	require.NoError(t, err)
	require.Zero(t, result[0].GetEngineVehiclesCount())
	require.Zero(t, logger.queries.Load(), "a list without engines needs no engine query")

	for _, fields := range []*ItemFields{
		{AttrZoneId: true}, {ChildsCounts: true}, {EngineVehiclesCount: true},
	} {
		logger.queries.Store(0)

		_, err := extractor.ExtractRows(t.Context(), rows, fields, "en", UserContext{Roles: []string{users.RoleModer}})
		require.NoError(t, err)
		require.EqualValues(t, 1, logger.queries.Load(), "unrequested counters must not be queried")
	}
}

func TestItemExtractorCatalogueCountsEmptyAndCanceled(t *testing.T) {
	t.Parallel()

	extractor, logger := newCatalogueCountsTestExtractor(t)
	repository := extractor.container.itemsRepository
	vehicleTypes, err := repository.VehicleTypeIDsBatch(t.Context(), nil, false)
	require.NoError(t, err)
	require.Empty(t, vehicleTypes)
	children, err := repository.ChildsCountsBatch(t.Context(), nil)
	require.NoError(t, err)
	require.Empty(t, children)
	engines, err := repository.EngineVehiclesCounts(t.Context(), nil)
	require.NoError(t, err)
	require.Empty(t, engines)
	result, err := extractor.ExtractRows(t.Context(), nil,
		&ItemFields{AttrZoneId: true, ChildsCounts: true, EngineVehiclesCount: true}, "en",
		UserContext{Roles: []string{users.RoleModer}})
	require.NoError(t, err)
	require.Empty(t, result)
	require.Zero(t, logger.queries.Load())

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	for _, fields := range []*ItemFields{
		{AttrZoneId: true}, {ChildsCounts: true}, {EngineVehiclesCount: true},
	} {
		_, err := extractor.ExtractRows(ctx, []*items.Item{{ID: 3, ItemTypeID: schema.ItemTableItemTypeIDEngine}},
			fields, "en", UserContext{Roles: []string{users.RoleModer}})
		require.ErrorIs(t, err, context.Canceled)
	}
}
