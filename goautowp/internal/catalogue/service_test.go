package catalogue

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres"
	"github.com/stretchr/testify/require"
)

func createTemporaryCatalogue(t *testing.T) (*Service, *sql.DB) {
	t.Helper()

	cfg := config.LoadConfig("../..")
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
	catalogue, err := New(database, database)
	require.NoError(t, err)

	return catalogue, db
}

func TestCataloguePerspectiveErrors(t *testing.T) {
	t.Parallel()

	for _, column := range []string{"perspective_group", "perspective"} {
		t.Run(column, func(t *testing.T) {
			t.Parallel()

			catalogue, pool := createTemporaryCatalogue(t)
			_, err := pool.ExecContext(t.Context(), `
				CREATE TEMP TABLE perspective_page (id integer, name text);
				CREATE TEMP TABLE perspective_group (id integer, name text, page_id integer, position integer);
				CREATE TEMP TABLE perspective (id integer, name text, position integer);
				CREATE TEMP TABLE perspective_group_perspective
					(group_id integer, perspective_id integer, position integer);
				INSERT INTO perspective_page VALUES (1, 'Page');
				INSERT INTO perspective_group VALUES (1, 'Group', 1, 1);
				INSERT INTO perspective VALUES (1, 'Front', 1);
				INSERT INTO perspective_group_perspective VALUES (1, 1, 1);
			`)
			require.NoError(t, err)

			ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
			defer cancel()

			pages, err := catalogue.PerspectivePages(ctx)
			require.NoError(t, err)
			require.Len(t, pages, 1)
			require.Len(t, pages[0].GetGroups(), 1)
			require.Equal(t, "Front", pages[0].GetGroups()[0].GetPerspectives()[0].GetName())

			_, err = pool.ExecContext(ctx, "ALTER TABLE pg_temp."+column+" RENAME COLUMN name TO broken_name")
			require.NoError(t, err)
			pages, err = catalogue.PerspectivePages(ctx)
			require.Error(t, err, "nested query errors must not be swallowed")
			require.Nil(t, pages)
			require.NoError(t, ctx.Err())
			require.Zero(t, pool.Stats().InUse)
		})
	}
}

func TestCatalogueVehicleTypesTree(t *testing.T) {
	t.Parallel()

	catalogue, db := createTemporaryCatalogue(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	empty, err := catalogue.VehicleTypesTree(ctx, 0)
	require.NoError(t, err)
	require.Empty(t, empty)
	require.NotNil(t, empty)

	_, err = db.ExecContext(ctx, `INSERT INTO vehicle_type VALUES
		(1, 'Root A', NULL, 20), (2, 'Root B', NULL, 10),
		(3, 'Child B', 1, 3), (4, 'Child A', 1, 2), (5, 'Grandchild', 4, 1),
		(98, 'Zero parent', 0, 0), (99, 'Missing parent', 999, 0)`)
	require.NoError(t, err)

	grandchild := &pb.VehicleType{Id: 5, Name: "Grandchild", Childs: []*pb.VehicleType{}}
	children := []*pb.VehicleType{
		{Id: 4, Name: "Child A", Childs: []*pb.VehicleType{grandchild}},
		{Id: 3, Name: "Child B", Childs: []*pb.VehicleType{}},
	}
	items, err := catalogue.VehicleTypesTree(ctx, 0)
	require.NoError(t, err)
	require.Equal(t, []*pb.VehicleType{
		{Id: 2, Name: "Root B", Childs: []*pb.VehicleType{}},
		{Id: 1, Name: "Root A", Childs: children},
	}, items)

	subtree, err := catalogue.VehicleTypesTree(ctx, 1)
	require.NoError(t, err)
	require.Equal(t, children, subtree)

	missing, err := catalogue.VehicleTypesTree(ctx, 12345)
	require.NoError(t, err)
	require.Empty(t, missing)
	require.Zero(t, db.Stats().WaitCount)

	cancel()

	_, err = catalogue.VehicleTypesTree(ctx, 0)
	require.ErrorIs(t, err, context.Canceled)
}

func TestCatalogueSpecsTree(t *testing.T) {
	t.Parallel()

	catalogue, db := createTemporaryCatalogue(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	empty, err := catalogue.Specs(ctx, 0)
	require.NoError(t, err)
	require.Empty(t, empty)
	require.NotNil(t, empty)

	_, err = db.ExecContext(ctx, `INSERT INTO spec VALUES
		(1, 'B', 's1', NULL), (2, 'A', 's2', NULL),
		(3, 'Z', 's3', 1), (4, 'C', 's4', 1), (5, 'D', 's5', 4),
		(98, 'Zero parent', 's98', 0), (99, 'Missing parent', 's99', 999)`)
	require.NoError(t, err)

	grandchild := &pb.Spec{Id: 5, Name: "D", ShortName: "s5", Childs: []*pb.Spec{}}
	children := []*pb.Spec{
		{Id: 4, Name: "C", ShortName: "s4", Childs: []*pb.Spec{grandchild}},
		{Id: 3, Name: "Z", ShortName: "s3", Childs: []*pb.Spec{}},
	}
	items, err := catalogue.Specs(ctx, 0)
	require.NoError(t, err)
	require.Equal(t, []*pb.Spec{
		{Id: 2, Name: "A", ShortName: "s2", Childs: []*pb.Spec{}},
		{Id: 1, Name: "B", ShortName: "s1", Childs: children},
	}, items)

	subtree, err := catalogue.Specs(ctx, 1)
	require.NoError(t, err)
	require.Equal(t, children, subtree)

	missing, err := catalogue.Specs(ctx, 12345)
	require.NoError(t, err)
	require.Empty(t, missing)
	require.Zero(t, db.Stats().WaitCount)

	cancel()

	_, err = catalogue.Specs(ctx, 0)
	require.ErrorIs(t, err, context.Canceled)
}
