package attrs

import (
	"context"
	"database/sql"
	"math/rand"
	"strconv"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	"github.com/google/uuid"
	_ "github.com/lib/pq" // enable postgres driver
	"github.com/stretchr/testify/require"
)

func createRandomUser(t *testing.T, db *goqu.Database) int64 {
	t.Helper()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	emailAddr := "test" + strconv.Itoa(random.Int()) + "@example.com"
	name := "ivan"

	var id int64

	success, err := db.Insert(schema.UserTable).
		Rows(goqu.Record{
			schema.UserTableLoginColName:          nil,
			schema.UserTableEmailColName:          emailAddr,
			schema.UserTablePasswordColName:       nil,
			schema.UserTableEmailToCheckColName:   nil,
			schema.UserTableHideEmailColName:      true,
			schema.UserTableEmailCheckCodeColName: nil,
			schema.UserTableNameColName:           name,
			schema.UserTableRegDateColName:        goqu.Func("NOW"),
			schema.UserTableLastOnlineColName:     goqu.Func("NOW"),
			schema.UserTableTimezoneColName:       "Europe/Moscow",
			schema.UserTableLastIPColName:         goqu.Func("INET", "127.0.0.1"),
			schema.UserTableLanguageColName:       schema.EnglishLanguageCode,
			schema.UserTableUUIDColName:           uuid.New().String(),
		}).
		Returning(schema.UserTableIDCol).
		Executor().ScanValContext(t.Context(), &id)
	require.NoError(t, err)
	require.True(t, success)

	return id
}

func createRepositoryWithCallback(
	t *testing.T,
	afterUserValueSet func(ctx context.Context, userID int64) error,
	afterUserValueChanged func(ctx context.Context, userID int64) error,
) (*Repository, *goqu.Database) {
	t.Helper()

	cfg := config.LoadConfig("..")

	postgresDB, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquDB := goqu.New("postgres", postgresDB)

	i18n, err := i18nbundle.New()
	require.NoError(t, err)

	textstorageRepository := textstorage.New(goquDB)
	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	itemParentLanguageRepository := items.NewItemParentLanguageRepository(goquDB, cfg.ContentLanguages)
	itemsRepository := items.NewRepository(
		goquDB,
		0,
		itemParentLanguageRepository,
		textstorageRepository,
		imageStorage,
	)

	picturesRepository := pictures.NewRepository(
		goquDB,
		imageStorage,
		textstorageRepository,
		itemsRepository,
		cfg.DuplicateFinder,
		func(int64) error { return nil },
		func(context.Context) error { return nil },
		func(context.Context, sql.NullInt64, int64) error { return nil },
		func(context.Context, int64) error { return nil },
	)

	repo := NewRepository(
		goquDB, i18n, itemsRepository, picturesRepository, imageStorage, afterUserValueSet, afterUserValueChanged,
	)

	return repo, goquDB
}

func createRepository(t *testing.T) *Repository {
	t.Helper()

	repo, _ := createRepositoryWithCallback(
		t,
		func(context.Context, int64) error { return nil },
		func(context.Context, int64) error { return nil },
	)

	return repo
}

func TestAttributes(t *testing.T) {
	t.Parallel()

	repo := createRepository(t)

	ctx := t.Context()

	_, err := repo.Attributes(ctx, nil)
	require.NoError(t, err)

	rows, err := repo.Attributes(ctx, &query.AttrsListOptions{ParentID: 95})
	require.NoError(t, err)
	require.NotEmpty(t, rows)
}

func TestSetUserValueCallsCallbackOnlyOnChange(t *testing.T) {
	t.Parallel()

	var calledWith []int64

	repo, db := createRepositoryWithCallback(
		t,
		func(_ context.Context, userID int64) error {
			calledWith = append(calledWith, userID)

			return nil
		},
		func(context.Context, int64) error { return nil },
	)

	ctx := t.Context()
	userID := createRandomUser(t, db)

	// attribute id 4 is a seeded integer-type attribute, item id 1 a seeded item
	// (see dump.sql fixture data loaded for the test DB).
	const attributeID, itemID int64 = 4, 1

	_, err := repo.SetUserValue(ctx, userID, attributeID, itemID, Value{Valid: true, IntValue: 42})
	require.NoError(t, err)
	require.Equal(t, []int64{userID}, calledWith)

	// Resubmitting the exact same value must not fire the callback again.
	_, err = repo.SetUserValue(ctx, userID, attributeID, itemID, Value{Valid: true, IntValue: 42})
	require.NoError(t, err)
	require.Equal(t, []int64{userID}, calledWith)

	// A genuinely different value fires it again.
	_, err = repo.SetUserValue(ctx, userID, attributeID, itemID, Value{Valid: true, IntValue: 43})
	require.NoError(t, err)
	require.Equal(t, []int64{userID, userID}, calledWith)
}

func TestUserValueChangeInvalidatesSpecsVolume(t *testing.T) {
	t.Parallel()

	var calledWith []int64

	repo, db := createRepositoryWithCallback(
		t,
		func(context.Context, int64) error { return nil },
		func(_ context.Context, userID int64) error {
			calledWith = append(calledWith, userID)

			return nil
		},
	)

	ctx := t.Context()
	userID := createRandomUser(t, db)

	// attribute id 4 is a seeded integer-type attribute, item id 1 a seeded item
	// (see dump.sql fixture data loaded for the test DB).
	const attributeID, itemID int64 = 4, 1

	_, err := repo.SetUserValue(ctx, userID, attributeID, itemID, Value{Valid: true, IntValue: 42})
	require.NoError(t, err)
	require.Equal(t, []int64{userID}, calledWith)

	// Resubmitting the exact same value must not fire the callback again.
	_, err = repo.SetUserValue(ctx, userID, attributeID, itemID, Value{Valid: true, IntValue: 42})
	require.NoError(t, err)
	require.Equal(t, []int64{userID}, calledWith)

	// A genuinely different value fires it again.
	_, err = repo.SetUserValue(ctx, userID, attributeID, itemID, Value{Valid: true, IntValue: 43})
	require.NoError(t, err)
	require.Equal(t, []int64{userID, userID}, calledWith)

	// Deleting the value fires it too.
	err = repo.DeleteUserValue(ctx, attributeID, itemID, userID)
	require.NoError(t, err)
	require.Equal(t, []int64{userID, userID, userID}, calledWith)
}

func TestAttributeTypes(t *testing.T) {
	t.Parallel()

	repo := createRepository(t)

	ctx := t.Context()

	_, err := repo.AttributeTypes(ctx)
	require.NoError(t, err)
}

func TestUnits(t *testing.T) {
	t.Parallel()

	repo := createRepository(t)

	ctx := t.Context()

	_, err := repo.Units(ctx)
	require.NoError(t, err)
}

func TestZones(t *testing.T) {
	t.Parallel()

	repo := createRepository(t)

	ctx := t.Context()

	_, err := repo.Zones(ctx)
	require.NoError(t, err)
}
