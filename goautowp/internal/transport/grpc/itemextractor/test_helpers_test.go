package itemextractor

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/attrs"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/i18nbundle"
	"github.com/autowp/goautowp/internal/image/storage"
	"github.com/autowp/goautowp/internal/itemofday"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/textstorage"
	"github.com/autowp/goautowp/internal/transport/grpc/pictureextractor"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	_ "github.com/lib/pq"
	"github.com/stretchr/testify/require"
)

type testExtractor struct {
	*Extractor

	db                 *goqu.Database
	itemsRepository    *items.Repository
	attrsRepository    *attrs.Repository
	commentsRepository *comments.Repository
}

func newRelatedGroupTestDB(t *testing.T) (*goqu.Database, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	pool, err := sql.Open("postgres", config.LoadConfig("../../../..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })

	logger := &itemBatchQueryLogger{}
	db := goqu.New("postgres", pool)
	db.Logger(logger)

	return db, pool, logger
}

func newTestExtractor(t *testing.T, db *goqu.Database) *testExtractor {
	t.Helper()

	cfg := config.LoadConfig("../../../..")
	images, err := storage.NewStorage(db, cfg.ImageStorage)
	require.NoError(t, err)
	bundle, err := i18nbundle.New()
	require.NoError(t, err)

	texts := textstorage.New(db)
	itemRepository := items.NewRepository(db, cfg.MostsMinCarsCount,
		items.NewItemParentLanguageRepository(db, cfg.ContentLanguages), texts, images)
	pictureRepository := pictures.NewRepository(db, images, texts, itemRepository, nil,
		cfg.DuplicateFinder, nil, nil, nil, nil)
	attributes := attrs.NewRepository(db, bundle, itemRepository, pictureRepository, images, nil, nil)
	commentRepository := comments.NewRepository(db, nil, nil, nil, nil)
	dayRepository := itemofday.NewRepository(db)
	dependencies := Dependencies{
		ItemsRepository:     func(_ context.Context) (*items.Repository, error) { return itemRepository, nil },
		PicturesRepository:  func(_ context.Context) (*pictures.Repository, error) { return pictureRepository, nil },
		AttrsRepository:     func(_ context.Context) (*attrs.Repository, error) { return attributes, nil },
		CommentsRepository:  func(_ context.Context) (*comments.Repository, error) { return commentRepository, nil },
		ItemOfDayRepository: func(_ context.Context) (*itemofday.Repository, error) { return dayRepository, nil },
		ImageStorage:        func(_ context.Context) (*storage.Storage, error) { return images, nil },
		I18n:                func() (*i18nbundle.I18n, error) { return bundle, nil },
	}
	extractor := New(dependencies)
	pictureExtractor := pictureextractor.New(pictureextractor.Dependencies{
		PicturesRepository:    dependencies.PicturesRepository,
		ItemsRepository:       dependencies.ItemsRepository,
		CommentsRepository:    dependencies.CommentsRepository,
		ImageStorage:          dependencies.ImageStorage,
		I18n:                  dependencies.I18n,
		TextStorageRepository: func(_ context.Context) (*textstorage.Repository, error) { return texts, nil },
		ItemExtractor:         func() pictureextractor.ItemExtractor { return extractor },
	})
	extractor.dependencies.PictureExtractor = func() PictureExtractor { return pictureExtractor }

	return &testExtractor{
		Extractor: extractor, db: db, itemsRepository: itemRepository,
		attrsRepository: attributes, commentsRepository: commentRepository,
	}
}

func createTestItem(t *testing.T, extractor *testExtractor,
	itemType schema.ItemTableItemTypeID, group bool, name string,
) int64 {
	t.Helper()
	id, err := extractor.itemsRepository.CreateItem(t.Context(), schema.ItemRow{
		ItemTypeID: itemType,
		IsGroup:    group,
		Catname:    sql.NullString{String: "extractor-" + uuid.NewString(), Valid: true},
	}, name, 0)
	require.NoError(t, err)
	t.Cleanup(func() {
		extractor.db.Logger(nil)

		ctx, cancel := context.WithTimeout(context.WithoutCancel(t.Context()), 10*time.Second)
		defer cancel()

		_, err := extractor.db.Delete(schema.ItemParentLanguageTable).Where(goqu.Or(
			schema.ItemParentLanguageTableItemIDCol.Eq(id), schema.ItemParentLanguageTableParentIDCol.Eq(id),
		)).Executor().ExecContext(ctx)
		require.NoError(t, err)
		_, err = extractor.db.Delete(schema.ItemParentTable).Where(goqu.Or(
			schema.ItemParentTableItemIDCol.Eq(id), schema.ItemParentTableParentIDCol.Eq(id),
		)).Executor().ExecContext(ctx)
		require.NoError(t, err)
		_, err = extractor.db.Delete(schema.ItemTable).Where(schema.ItemTableIDCol.Eq(id)).Executor().ExecContext(ctx)
		require.NoError(t, err)
	})

	return id
}

func newItemOfDayTestExtractor(t *testing.T) (*testExtractor, *items.Item, *goqu.Database, *sql.DB) {
	t.Helper()
	db, pool, _ := newRelatedGroupTestDB(t)
	pool.SetMaxOpenConns(2)
	db.Logger(nil)
	extractor := newTestExtractor(t, db)
	brand := createTestItem(t, extractor, schema.ItemTableItemTypeIDBrand, true, "Day brand")
	vehicle := createTestItem(t, extractor, schema.ItemTableItemTypeIDVehicle, false, "Day vehicle")
	_, err := extractor.itemsRepository.CreateItemParent(
		t.Context(),
		vehicle,
		brand,
		schema.ItemParentTypeDefault,
		"model",
	)
	require.NoError(t, err)

	for _, perspective := range []int32{schema.PerspectiveFrontStrict, schema.PerspectiveBackStrict} {
		createTestPicture(t, extractor, vehicle, perspective)
	}

	row, err := extractor.itemsRepository.Item(
		t.Context(),
		&query.ItemListOptions{ItemID: vehicle, Language: "en"},
		nil,
	)
	require.NoError(t, err)

	return extractor, row, db, pool
}

func createTestPicture(t *testing.T, extractor *testExtractor, itemID int64, perspective int32) {
	t.Helper()
	images, err := extractor.dependencies.ImageStorage(t.Context())
	require.NoError(t, err)
	imageID, err := images.AddImageFromFilepath(
		t.Context(),
		"../../../../test/test.jpg",
		"picture",
		storage.GenerateOptions{},
	)
	require.NoError(t, err)

	var pictureID int64

	_, err = extractor.db.Insert(schema.PictureTable).Rows(goqu.Record{
		schema.PictureTableImageIDColName:   imageID,
		schema.PictureTableWidthColName:     1024,
		schema.PictureTableHeightColName:    768,
		schema.PictureTableFilesizeColName:  1000,
		schema.PictureTableIdentityColName:  uuid.NewString()[:10],
		schema.PictureTableStatusColName:    schema.PictureStatusAccepted,
		schema.PictureTableCreatedAtColName: goqu.Func("NOW"),
	}).Returning(schema.PictureTableIDCol).Executor().ScanValContext(t.Context(), &pictureID)
	require.NoError(t, err)
	t.Cleanup(func() {
		extractor.db.Logger(nil)

		ctx, cancel := context.WithTimeout(context.WithoutCancel(t.Context()), 10*time.Second)
		defer cancel()

		_, err := extractor.db.Delete(schema.PictureItemTable).
			Where(schema.PictureItemTablePictureIDCol.Eq(pictureID)).
			Executor().
			ExecContext(ctx)
		require.NoError(t, err)
		_, err = extractor.db.Delete(schema.PictureTable).
			Where(schema.PictureTableIDCol.Eq(pictureID)).
			Executor().
			ExecContext(ctx)
		require.NoError(t, err)
		require.NoError(t, images.RemoveImage(ctx, imageID))
	})
	repository, err := extractor.dependencies.PicturesRepository(t.Context())
	require.NoError(t, err)
	_, err = repository.CreatePictureItem(t.Context(), pictureID, itemID, schema.PictureItemTypeContent, perspective, 0)
	require.NoError(t, err)
}
