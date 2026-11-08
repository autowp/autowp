package pictureextractor_test

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/i18nbundle"
	"github.com/autowp/goautowp/internal/image/storage"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/textstorage"
	"github.com/autowp/goautowp/internal/transport/grpc/dfdistanceextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/itemextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/itemparentextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/pictureextractor"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

type pictureFieldFixture struct {
	*pictureextractor.Extractor

	dependencies pictureextractor.Dependencies
	itemParents  *itemparentextractor.Extractor
}

func newPictureFieldBatchExtractor(
	t *testing.T,
) (*pictureFieldFixture, *goqu.Database, *sql.DB, []*schema.PictureRow) {
	t.Helper()

	db, pool, rows := newPictureFieldRows(t)
	cfg := config.LoadConfig("../../../..")
	images, err := storage.NewStorage(db, cfg.ImageStorage)
	require.NoError(t, err)

	texts := textstorage.New(db)
	itemRepository := items.NewRepository(db, cfg.MostsMinCarsCount, nil, texts, images)
	pictureRepository := pictures.NewRepository(db, images, texts, itemRepository, nil,
		cfg.RabbitMQ, cfg.DuplicateFinder, nil, nil, nil, nil)
	commentRepository := comments.NewRepository(db, nil, nil, nil, nil)
	bundle, err := i18nbundle.New()
	require.NoError(t, err)

	itemsDependency := func(_ context.Context) (*items.Repository, error) { return itemRepository, nil }
	picturesDependency := func(_ context.Context) (*pictures.Repository, error) { return pictureRepository, nil }
	bundleDependency := func() (*i18nbundle.I18n, error) { return bundle, nil }
	itemExtractor := itemextractor.New(itemextractor.Dependencies{
		ItemsRepository: itemsDependency,
		I18n:            bundleDependency,
	})

	var distances *dfdistanceextractor.Extractor

	dependencies := pictureextractor.Dependencies{
		PicturesRepository:    picturesDependency,
		ItemsRepository:       itemsDependency,
		I18n:                  bundleDependency,
		ImageStorage:          func(_ context.Context) (*storage.Storage, error) { return images, nil },
		ItemExtractor:         func() pictureextractor.ItemExtractor { return itemExtractor },
		CommentsRepository:    func(_ context.Context) (*comments.Repository, error) { return commentRepository, nil },
		TextStorageRepository: func(_ context.Context) (*textstorage.Repository, error) { return texts, nil },
		DfDistanceExtractor:   func(_ context.Context) (*dfdistanceextractor.Extractor, error) { return distances, nil },
	}
	extractor := pictureextractor.New(dependencies)
	distances = dfdistanceextractor.New(pictureRepository, extractor)
	parents := itemparentextractor.New(itemsDependency, picturesDependency, itemExtractor, extractor)

	return &pictureFieldFixture{
		Extractor:    extractor,
		dependencies: dependencies,
		itemParents:  parents,
	}, db, pool, rows
}
