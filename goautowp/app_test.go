package goautowp

import (
	"errors"
	"fmt"
	"math/rand"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/golang-migrate/migrate/v4"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func TestPostgresMigrations(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	err := applyMigrations(cfg.PostgresMigrations)
	if !errors.Is(err, migrate.ErrNoChange) {
		require.NoError(t, err)
	}
}

func TestAutowpMigrations(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	err := applyMigrations(cfg.AutowpMigrations)
	if !errors.Is(err, migrate.ErrNoChange) {
		require.NoError(t, err)
	}
}

func TestServe(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	cfg.PublicRest.Listen = ":9090"
	app := NewApplication(cfg)

	done := make(chan bool)

	go func() {
		time.Sleep(5 * time.Second)
		close(done)
	}()

	err := app.Serve(t.Context(), ServeOptions{
		DuplicateFinderAMQP:   true,
		MonitoringAMQP:        true,
		GRPC:                  true,
		Public:                true,
		Autoban:               true,
		AttrsUpdateValuesAMQP: true,
	}, done)
	require.NoError(t, err)
}

func TestImageStorageListBrokenImages(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.ImageStorageListBrokenImages(t.Context(), "picture", "")
	require.NoError(t, err)
}

func TestImageStorageListUnlinkedObjects(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.ImageStorageListUnlinkedObjects(t.Context(), "format", true, "")
	require.NoError(t, err)
}

func TestGenerateIndexCache(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.GenerateIndexCache(t.Context())
	require.NoError(t, err)
}

func TestSpecsRefreshConflictFlags(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.SpecsRefreshConflictFlags(t.Context())
	require.NoError(t, err)
}

func TestSpecsRefreshItemConflictFlags(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.SpecsRefreshItemConflictFlags(t.Context(), 1)
	require.NoError(t, err)
}

func TestSpecsRefreshUserConflicts(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)
	ctx := t.Context()

	kc := cnt.Keycloak()
	usersClient := NewUsersClient(conn)

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	// tester (me)
	tester, err := usersClient.Me(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&APIMeRequest{},
	)
	require.NoError(t, err)

	err = app.SpecsRefreshUserConflicts(ctx, tester.GetId())
	require.NoError(t, err)
}

func TestSpecsRefreshUsersConflicts(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.SpecsRefreshUsersConflicts(t.Context())
	require.NoError(t, err)
}

func TestSpecsRefreshActualValues(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.SpecsRefreshActualValues(t.Context())
	require.NoError(t, err)
}

func TestRefreshItemParentLanguage(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.RefreshItemParentLanguage(t.Context(), schema.ItemTableItemTypeIDBrand, 10)
	require.NoError(t, err)
}

func TestRefreshItemParentAllAuto(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.RefreshItemParentAllAuto(t.Context())
	require.NoError(t, err)
}

func TestRebuildItemOrderCache(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.RebuildItemOrderCache(t.Context())
	require.NoError(t, err)
}

func TestPicturesDfIndex(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.PicturesDfIndex(t.Context())
	require.NoError(t, err)
}

func TestPicturesFixFilenames(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.PicturesFixFilenames(t.Context())
	require.NoError(t, err)
}

func TestBuildBrandsSprite(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)
	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	imageStorage, err := cnt.ImageStorage(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	for i := range 1 {
		itemID := createItem(t, conn, cnt, &APIItem{
			Name:       fmt.Sprintf("brand-%d-%d", random.Int(), i),
			ItemTypeId: ItemType_ITEM_TYPE_BRAND,
			Catname:    fmt.Sprintf("brand-%d-%d", random.Int(), i),
		})

		imageID, err := imageStorage.AddImageFromFilepath(
			t.Context(),
			"./test/png.png",
			"brand",
			storage.GenerateOptions{},
		)
		require.NoError(t, err)
		require.NotEmpty(t, imageID)

		_, err = goquDB.Update(schema.ItemTable).
			Set(goqu.Record{
				schema.ItemTableLogoIDColName: imageID,
			}).
			Where(schema.ItemTableIDCol.Eq(itemID)).
			Executor().ExecContext(t.Context())
		require.NoError(t, err)
	}

	err = app.BuildBrandsSprite(t.Context())
	require.NoError(t, err)
}

func TestDaily(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	app := NewApplication(cfg)

	err := app.SchedulerDaily(t.Context())
	require.NoError(t, err)
}
