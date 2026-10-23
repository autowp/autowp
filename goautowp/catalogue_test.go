package goautowp

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func createTemporaryCatalogue(t *testing.T) (*Catalogue, *sql.DB) {
	t.Helper()

	cfg := config.LoadConfig(".")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	// A tree must be readable with a single connection, without nested open cursors.
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, db.Close()) })
	_, err = db.ExecContext(t.Context(), `
		CREATE TEMP TABLE vehicle_type (
			id bigint PRIMARY KEY, name text NOT NULL, parent_id bigint, position integer NOT NULL
		);
		CREATE TEMP TABLE spec (
			id integer PRIMARY KEY, name text NOT NULL, short_name text NOT NULL, parent_id integer
		);
	`)
	require.NoError(t, err)

	database := goqu.New("postgres", db)
	catalogue, err := NewCatalogue(database, database)
	require.NoError(t, err)

	return catalogue, db
}

func TestCatalogueVehicleTypesTree(t *testing.T) {
	t.Parallel()

	catalogue, db := createTemporaryCatalogue(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	empty, err := catalogue.getVehicleTypesTree(ctx, 0)
	require.NoError(t, err)
	require.Empty(t, empty)
	require.NotNil(t, empty)

	_, err = db.ExecContext(ctx, `INSERT INTO vehicle_type VALUES
		(1, 'Root A', NULL, 20), (2, 'Root B', NULL, 10),
		(3, 'Child B', 1, 3), (4, 'Child A', 1, 2), (5, 'Grandchild', 4, 1),
		(98, 'Zero parent', 0, 0), (99, 'Missing parent', 999, 0)`)
	require.NoError(t, err)

	grandchild := &VehicleType{Id: 5, Name: "Grandchild", Childs: []*VehicleType{}}
	children := []*VehicleType{
		{Id: 4, Name: "Child A", Childs: []*VehicleType{grandchild}},
		{Id: 3, Name: "Child B", Childs: []*VehicleType{}},
	}
	items, err := catalogue.getVehicleTypesTree(ctx, 0)
	require.NoError(t, err)
	require.Equal(t, []*VehicleType{
		{Id: 2, Name: "Root B", Childs: []*VehicleType{}},
		{Id: 1, Name: "Root A", Childs: children},
	}, items)

	subtree, err := catalogue.getVehicleTypesTree(ctx, 1)
	require.NoError(t, err)
	require.Equal(t, children, subtree)

	missing, err := catalogue.getVehicleTypesTree(ctx, 12345)
	require.NoError(t, err)
	require.Empty(t, missing)
	require.Zero(t, db.Stats().WaitCount)

	cancel()

	_, err = catalogue.getVehicleTypesTree(ctx, 0)
	require.ErrorIs(t, err, context.Canceled)
}

func TestCatalogueSpecsTree(t *testing.T) {
	t.Parallel()

	catalogue, db := createTemporaryCatalogue(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	empty, err := catalogue.getSpecs(ctx, 0)
	require.NoError(t, err)
	require.Empty(t, empty)
	require.NotNil(t, empty)

	_, err = db.ExecContext(ctx, `INSERT INTO spec VALUES
		(1, 'B', 's1', NULL), (2, 'A', 's2', NULL),
		(3, 'Z', 's3', 1), (4, 'C', 's4', 1), (5, 'D', 's5', 4),
		(98, 'Zero parent', 's98', 0), (99, 'Missing parent', 's99', 999)`)
	require.NoError(t, err)

	grandchild := &Spec{Id: 5, Name: "D", ShortName: "s5", Childs: []*Spec{}}
	children := []*Spec{
		{Id: 4, Name: "C", ShortName: "s4", Childs: []*Spec{grandchild}},
		{Id: 3, Name: "Z", ShortName: "s3", Childs: []*Spec{}},
	}
	items, err := catalogue.getSpecs(ctx, 0)
	require.NoError(t, err)
	require.Equal(t, []*Spec{
		{Id: 2, Name: "A", ShortName: "s2", Childs: []*Spec{}},
		{Id: 1, Name: "B", ShortName: "s1", Childs: children},
	}, items)

	subtree, err := catalogue.getSpecs(ctx, 1)
	require.NoError(t, err)
	require.Equal(t, children, subtree)

	missing, err := catalogue.getSpecs(ctx, 12345)
	require.NoError(t, err)
	require.Empty(t, missing)
	require.Zero(t, db.Stats().WaitCount)

	cancel()

	_, err = catalogue.getSpecs(ctx, 0)
	require.ErrorIs(t, err, context.Canceled)
}
