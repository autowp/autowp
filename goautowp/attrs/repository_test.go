package attrs

import (
	"context"
	"database/sql"
	"testing"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/mysql"    // enable mysql dialect
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	_ "github.com/go-sql-driver/mysql"                  // enable mysql driver
	_ "github.com/lib/pq"                               // enable postgres driver
	"github.com/stretchr/testify/require"
)

func createRepository(t *testing.T) *Repository {
	t.Helper()

	cfg := config.LoadConfig("..")

	autowpDB, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", autowpDB)

	i18n, err := i18nbundle.New()
	require.NoError(t, err)

	textstorageRepository := textstorage.New(goquDB)
	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	client := gocloak.NewClient(cfg.Keycloak.URL)

	postgresDB, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquPostgresDB := goqu.New("postgres", postgresDB)

	itemsRepository := items.NewRepository(
		goquDB,
		0,
		cfg.ContentLanguages,
		textstorageRepository,
		imageStorage,
	)
	usersRepository := users.NewRepository(
		goquDB,
		goquPostgresDB,
		cfg.UsersSalt,
		cfg.Languages,
		client,
		cfg.Keycloak,
		cfg.MessageInterval,
		imageStorage,
	)
	i, err := i18nbundle.New()
	require.NoError(t, err)

	messagingRepo := messaging.NewRepository(
		goquDB,
		func(_ context.Context, _ int64, _ int64, _ string) error {
			return nil
		},
		i,
	)
	hostsManager := hosts.NewManager(cfg.Languages)
	commentsRepository := comments.NewRepository(
		goquDB,
		usersRepository,
		messagingRepo,
		hostsManager,
	)
	picturesRepository := pictures.NewRepository(
		goquDB,
		imageStorage,
		textstorageRepository,
		itemsRepository,
		cfg.DuplicateFinder,
		commentsRepository,
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
