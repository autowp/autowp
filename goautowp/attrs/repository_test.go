package attrs

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/textstorage"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	_ "github.com/lib/pq"                               // enable postgres driver
	"github.com/stretchr/testify/require"
)

func createRepository(t *testing.T) *Repository {
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
	)

	repo := NewRepository(goquDB, i18n, itemsRepository, picturesRepository, imageStorage)

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
