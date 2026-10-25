package items

import (
	"context"
	"database/sql"
	"errors"
	"net/url"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
)

var errVehicleTypeTestMutation = errors.New("mutation failed")

// Each fixture has its own schema and connection pool, including for lock-wait assertions.
func vehicleTypesFixture(t *testing.T) (*Repository, *sql.DB) {
	t.Helper()

	cfg := config.LoadConfig("../")
	dsn, err := url.Parse(cfg.PostgresDSN)
	require.NoError(t, err)

	fixtureSchema := "vehicle_types_" + uuid.New().String()
	params := dsn.Query()
	params.Set("search_path", fixtureSchema)
	params.Set("application_name", fixtureSchema)
	dsn.RawQuery = params.Encode()
	db, err := sql.Open("postgres", dsn.String())
	require.NoError(t, err)
	// The concurrent test holds a lock, starts two writers, and observes their locks.
	db.SetMaxOpenConns(4)
	t.Cleanup(func() {
		cleanupCtx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
		defer cancel()

		_, cleanupErr := db.ExecContext(cleanupCtx, `DROP SCHEMA "`+fixtureSchema+`" CASCADE`)
		require.NoError(t, cleanupErr)
		require.NoError(t, db.Close())
	})

	_, err = db.ExecContext(t.Context(), `CREATE SCHEMA "`+fixtureSchema+`";
		CREATE TABLE item (id bigint PRIMARY KEY);
		CREATE TABLE item_parent (item_id bigint, parent_id bigint);
		CREATE TABLE item_vehicle_type (
			item_id bigint, vehicle_type_id bigint, inherited boolean NOT NULL,
			PRIMARY KEY (item_id, vehicle_type_id)
		);
		INSERT INTO item VALUES (1), (2), (3);
		INSERT INTO item_parent VALUES (2, 1), (3, 2);
		INSERT INTO item_vehicle_type VALUES (1, 10, false);`)
	require.NoError(t, err)

	return &Repository{db: goqu.New("postgres", db)}, db
}

func requireVehicleTypes(t *testing.T, db *sql.DB, itemID int64, inherited bool, expected ...int64) {
	t.Helper()

	ids := []int64{}
	err := goqu.New("postgres", db).From(schema.ItemVehicleTypeTable).
		Select(schema.ItemVehicleTypeTableVehicleTypeIDCol).
		Where(
			schema.ItemVehicleTypeTableItemIDCol.Eq(itemID),
			schema.ItemVehicleTypeTableInheritedCol.Eq(inherited),
		).ScanValsContext(t.Context(), &ids)
	require.NoError(t, err)
	require.ElementsMatch(t, expected, ids)
}

func TestVehicleTypesInheritance(t *testing.T) {
	t.Parallel()
	repo, db := vehicleTypesFixture(t)
	ctx := t.Context()

	require.NoError(t, repo.RefreshItemVehicleTypeInheritanceFromParents(ctx, 2))
	requireVehicleTypes(t, db, 2, true, 10)
	requireVehicleTypes(t, db, 3, true, 10)

	// Promote the same inherited type to an own value, then add a second own type.
	require.NoError(t, repo.AddItemVehicleType(ctx, 2, 10))
	require.NoError(t, repo.AddItemVehicleType(ctx, 2, 20))
	require.NoError(t, repo.AddItemVehicleType(ctx, 1, 30))
	requireVehicleTypes(t, db, 2, false, 10, 20)
	requireVehicleTypes(t, db, 2, true)
	requireVehicleTypes(t, db, 3, true, 10, 20)

	require.NoError(t, repo.RemoveItemVehicleType(ctx, 2, 10))
	require.NoError(t, repo.RemoveItemVehicleType(ctx, 2, 20))
	requireVehicleTypes(t, db, 2, false)
	requireVehicleTypes(t, db, 2, true, 10, 30)
	requireVehicleTypes(t, db, 3, true, 10, 30)

	// Repeated removals and additions must be harmless.
	require.NoError(t, repo.RemoveItemVehicleType(ctx, 2, 20))
	require.NoError(t, repo.AddItemVehicleType(ctx, 2, 40))
	require.NoError(t, repo.AddItemVehicleType(ctx, 2, 40))
	requireVehicleTypes(t, db, 3, true, 40)
}

func TestVehicleTypesConcurrentRefreshAndAdd(t *testing.T) {
	t.Parallel()
	repo, db := vehicleTypesFixture(t)
	ctx := t.Context()

	// Hold the item lock until both operations are waiting. Without the common lock,
	// a refresh can observe no own value and later delete a concurrently added one.
	tx, err := db.BeginTx(ctx, nil)
	require.NoError(t, err)
	t.Cleanup(func() { _ = tx.Rollback() })

	_, err = tx.ExecContext(ctx, `SELECT id FROM item WHERE id=2 FOR UPDATE`)
	require.NoError(t, err)

	results := make(chan error, 2)
	go func() { results <- repo.RefreshItemVehicleTypeInheritanceFromParents(ctx, 2) }()
	go func() { results <- repo.AddItemVehicleType(ctx, 2, 20) }()

	require.Eventually(t, func() bool {
		var waiting int

		err := db.QueryRowContext(ctx, `SELECT count(*) FROM pg_stat_activity
			WHERE application_name=current_setting('application_name') AND wait_event_type='Lock'`).Scan(&waiting)

		return err == nil && waiting == 2
	}, 5*time.Second, 10*time.Millisecond)
	require.NoError(t, tx.Commit())

	for range 2 {
		select {
		case err := <-results:
			require.NoError(t, err)
		case <-time.After(5 * time.Second):
			t.Fatal("vehicle type update did not finish")
		}
	}

	requireVehicleTypes(t, db, 2, false, 20)
	requireVehicleTypes(t, db, 2, true)
	requireVehicleTypes(t, db, 3, true, 20)
}

func TestVehicleTypesRollback(t *testing.T) {
	t.Parallel()
	repo, db := vehicleTypesFixture(t)

	err := repo.updateItemVehicleTypes(t.Context(), 2, func(ctx context.Context, tx *goqu.TxDatabase) (bool, error) {
		_, err := tx.ExecContext(ctx, `INSERT INTO item_vehicle_type VALUES (2, 20, false)`)
		if err != nil {
			return false, err
		}

		return true, errVehicleTypeTestMutation
	})
	require.ErrorIs(t, err, errVehicleTypeTestMutation)
	requireVehicleTypes(t, db, 2, false)
	requireVehicleTypes(t, db, 3, true)
}

func TestVehicleTypesCancellation(t *testing.T) {
	t.Parallel()
	repo, db := vehicleTypesFixture(t)

	ctx, cancel := context.WithCancel(t.Context())
	defer cancel()

	err := repo.updateItemVehicleTypes(ctx, 2, func(updateCtx context.Context, tx *goqu.TxDatabase) (bool, error) {
		_, err := tx.ExecContext(updateCtx, `INSERT INTO item_vehicle_type VALUES (2, 20, false)`)

		cancel()

		return true, err
	})
	require.NoError(t, err)
	requireVehicleTypes(t, db, 2, false, 20)
	requireVehicleTypes(t, db, 3, true, 20)

	require.ErrorIs(t, repo.AddItemVehicleType(ctx, 2, 30), context.Canceled)
	require.ErrorIs(t, repo.RemoveItemVehicleType(ctx, 2, 20), context.Canceled)
	require.ErrorIs(t, repo.RefreshItemVehicleTypeInheritanceFromParents(ctx, 2), context.Canceled)
	requireVehicleTypes(t, db, 2, false, 20)
}
