package attrs

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

type zoneBatchLogger struct{ count atomic.Int64 }

func (s *zoneBatchLogger) Printf(_ string, _ ...interface{}) { s.count.Add(1) }

func TestZoneByItemsListBatch(t *testing.T) {
	t.Parallel()

	pool, err := sql.Open("postgres", config.LoadConfig("..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	_, err = pool.ExecContext(
		t.Context(),
		`CREATE TEMP TABLE item_vehicle_type(item_id bigint,
 vehicle_type_id bigint,inherited boolean);
 INSERT INTO item_vehicle_type VALUES (1,19,false),(2,39,true),(3,39,false),(0,19,false),(4,19,NULL);`,
	)
	require.NoError(t, err)

	db := goqu.New("postgres", pool)
	repository := &Repository{itemsRepository: items.NewRepository(db, 0, nil, nil, nil)}

	car := func(id int64, kind schema.ItemTableItemTypeID) *items.Item {
		row := &items.Item{ID: id}
		row.ItemTypeID = kind

		return row
	}
	for _, rows := range [][]*items.Item{
		nil,
		{car(1, schema.ItemTableItemTypeIDVehicle), car(3, schema.ItemTableItemTypeIDVehicle)},
		{car(2, schema.ItemTableItemTypeIDVehicle), car(4, schema.ItemTableItemTypeIDVehicle)},
		{car(1, schema.ItemTableItemTypeIDVehicle), car(2, schema.ItemTableItemTypeIDVehicle)},
		{car(1, schema.ItemTableItemTypeIDEngine), car(2, schema.ItemTableItemTypeIDEngine)},
		{car(0, schema.ItemTableItemTypeIDVehicle), car(1, schema.ItemTableItemTypeIDVehicle)},
		{car(999, schema.ItemTableItemTypeIDVehicle)},
	} {
		zones := make(map[int64]bool)

		for _, row := range rows {
			types, err := repository.itemsRepository.VehicleTypeIDs(t.Context(), row.ID, false)
			require.NoError(t, err)

			zones[repository.ZoneIDByVehicleTypeIDs(row.ItemTypeID, types)] = true
		}

		var expected int64

		if len(zones) == 1 {
			for zone := range zones {
				expected = zone
			}
		}

		logger := &zoneBatchLogger{}
		db.Logger(logger)

		actual, err := repository.zoneByItemsList(t.Context(), rows)
		require.NoError(t, err)
		require.Equal(t, expected, actual)

		expectedQueries := 0
		if len(rows) > 0 {
			expectedQueries = 1
		}

		require.EqualValues(t, expectedQueries, logger.count.Load())
		db.Logger(nil)
	}

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.item_vehicle_type RENAME COLUMN inherited TO broken")
	require.NoError(t, err)
	_, err = repository.zoneByItemsList(t.Context(), []*items.Item{car(1, schema.ItemTableItemTypeIDVehicle)})
	require.ErrorContains(t, err, "VehicleTypes():")
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.zoneByItemsList(ctx, []*items.Item{car(1, schema.ItemTableItemTypeIDVehicle)})
	require.ErrorIs(t, err, context.Canceled)
}
