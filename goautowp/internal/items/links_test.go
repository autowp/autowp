package items

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func itemRelationsFixture(t *testing.T) *Repository {
	t.Helper()

	db, err := sql.Open("postgres", config.LoadConfig("../..").PostgresDSN)
	require.NoError(t, err)
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, db.Close()) })

	_, err = db.ExecContext(t.Context(), `
		CREATE TEMP TABLE item_link (
			id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
			name text, url text, type text, item_id bigint
		);
		CREATE TEMP TABLE item_vehicle_type (item_id bigint, vehicle_type_id bigint, inherited boolean);
		INSERT INTO item_vehicle_type VALUES
			(1, 10, false), (1, 20, false), (2, 10, false),
			(1, 30, true), (1, 40, NULL);`)
	require.NoError(t, err)

	return &Repository{db: goqu.New("postgres", db)}
}

func TestLinkMutations(t *testing.T) {
	t.Parallel()

	repo := itemRelationsFixture(t)
	ctx := t.Context()
	row := schema.LinkRow{ID: 999, Name: "Original", URL: "https://example.org", Type: "club", ItemID: 1}
	id, err := repo.CreateLink(ctx, row)
	require.NoError(t, err)
	require.NotZero(t, id)
	require.NotEqual(t, row.ID, id, "creation ignores the supplied primary key")
	row.ID = id
	stored, err := repo.Link(ctx, &query.LinkListOptions{ID: id})
	require.NoError(t, err)
	require.Equal(t, row, *stored)

	row.Name, row.URL, row.Type, row.ItemID = "Updated", "https://updated.example.org", "default", 2
	require.NoError(t, repo.UpdateLink(ctx, row))
	stored, err = repo.Link(ctx, &query.LinkListOptions{ID: id})
	require.NoError(t, err)
	require.Equal(t, row, *stored)

	canceled, cancel := context.WithCancel(ctx)
	cancel()

	_, err = repo.CreateLink(canceled, row)
	require.ErrorIs(t, err, context.Canceled)

	changed := row
	changed.Name = "Canceled"
	require.ErrorIs(t, repo.UpdateLink(canceled, changed), context.Canceled)
	require.ErrorIs(t, repo.DeleteLink(canceled, id), context.Canceled)
	stored, err = repo.Link(ctx, &query.LinkListOptions{ID: id})
	require.NoError(t, err)
	require.Equal(t, row, *stored)

	count, err := repo.LinksCount(ctx, &query.LinkListOptions{})
	require.NoError(t, err)
	require.EqualValues(t, 1, count)

	require.NoError(t, repo.DeleteLink(ctx, id))
	_, err = repo.Link(ctx, &query.LinkListOptions{ID: id})
	require.ErrorIs(t, err, sql.ErrNoRows)
	require.NoError(t, repo.UpdateLink(ctx, row), "updating a missing link remains a no-op")
	require.NoError(t, repo.DeleteLink(ctx, id), "deleting a missing link remains a no-op")
}

func TestItemVehicleTypeReads(t *testing.T) {
	t.Parallel()

	repo := itemRelationsFixture(t)
	for _, test := range []struct {
		name          string
		itemID        int64
		vehicleTypeID int64
		expected      []schema.ItemVehicleTypeRow
	}{
		{name: "item", itemID: 1, expected: []schema.ItemVehicleTypeRow{
			{ItemID: 1, VehicleTypeID: 10}, {ItemID: 1, VehicleTypeID: 20},
		}},
		{name: "type", vehicleTypeID: 10, expected: []schema.ItemVehicleTypeRow{
			{ItemID: 1, VehicleTypeID: 10}, {ItemID: 2, VehicleTypeID: 10},
		}},
		{name: "pair", itemID: 1, vehicleTypeID: 20, expected: []schema.ItemVehicleTypeRow{
			{ItemID: 1, VehicleTypeID: 20},
		}},
		{name: "inherited", itemID: 1, vehicleTypeID: 30},
		{name: "null inheritance", itemID: 1, vehicleTypeID: 40},
		{name: "negative filter", itemID: -1},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			rows, err := repo.ItemVehicleTypes(t.Context(), test.itemID, test.vehicleTypeID)
			require.NoError(t, err)
			require.NotNil(t, rows)
			require.ElementsMatch(t, test.expected, rows)
		})
	}

	row, err := repo.ItemVehicleType(t.Context(), 1, 10)
	require.NoError(t, err)
	require.Equal(t, &schema.ItemVehicleTypeRow{ItemID: 1, VehicleTypeID: 10}, row)

	for _, pair := range [][2]int64{{1, 0}, {0, 10}, {1, 30}, {1, 40}, {9, 10}} {
		_, err := repo.ItemVehicleType(t.Context(), pair[0], pair[1])
		require.ErrorIs(t, err, sql.ErrNoRows)
	}

	canceled, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repo.ItemVehicleTypes(canceled, 1, 0)
	require.ErrorIs(t, err, context.Canceled)
	_, err = repo.ItemVehicleType(canceled, 1, 10)
	require.ErrorIs(t, err, context.Canceled)
}
