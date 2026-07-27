package attrs

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

// TestSpecsVolumeStaysFreshAfterUserValueChanges is a regression test for a bug where
// users.specs_volume froze after its first UpdateSpecsVolumes run: specs_volume_valid
// defaults to false and UpdateSpecsVolumes flips it to true once computed, but nothing
// ever flipped it back to false when the user's attrs_user_values rows changed, so
// later SetUserValue/DeleteUserValue calls were silently ignored by the next
// scheduler-daily run.
func TestSpecsVolumeStaysFreshAfterUserValueChanges(t *testing.T) {
	t.Parallel()

	var usersRepo *users.Repository

	// Wire the real invalidation callback, same shape container.go uses.
	repo, db := createRepositoryWithCallback(
		t,
		func(context.Context, int64) error { return nil },
		func(ctx context.Context, userID int64) error {
			return usersRepo.InvalidateSpecsVolume(ctx, userID)
		},
	)

	usersRepo = users.NewRepository(db, "", nil, nil, config.KeycloakConfig{}, 0, nil)

	ctx := t.Context()
	userID := createRandomUser(t, db)

	// attribute id 4 is a seeded integer-type attribute, item id 1 a seeded item
	// (see dump.sql fixture data loaded for the test DB).
	const attributeID, itemID int64 = 4, 1

	// Simulate the state after the bug: an earlier UpdateSpecsVolumes run already
	// computed and froze specs_volume for this (currently value-less) user.
	_, err := db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableSpecsVolumeColName:      0,
		schema.UserTableSpecsVolumeValidColName: true,
	}).Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)
	require.NoError(t, err)

	_, err = repo.SetUserValue(ctx, userID, attributeID, itemID, Value{Valid: true, IntValue: 42})
	require.NoError(t, err)

	var valid bool

	_, err = db.Select(schema.UserTableSpecsVolumeValidCol).
		From(schema.UserTable).
		Where(schema.UserTableIDCol.Eq(userID)).
		ScanValContext(ctx, &valid)
	require.NoError(t, err)
	require.False(t, valid, "specs_volume_valid must be invalidated after a spec value is set")

	require.NoError(t, usersRepo.UpdateSpecsVolumes(ctx))

	var volume int64

	_, err = db.Select(schema.UserTableSpecsVolumeCol).
		From(schema.UserTable).
		Where(schema.UserTableIDCol.Eq(userID)).
		ScanValContext(ctx, &volume)
	require.NoError(t, err)
	require.EqualValues(t, 1, volume)
}
