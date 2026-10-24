package goautowp

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
)

func newRelatedGroupTestDB(t *testing.T) (*goqu.Database, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	database, err := sql.Open("postgres", config.LoadConfig(".").PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	logger := &itemBatchQueryLogger{}
	db := goqu.New("postgres", database)
	db.Logger(logger)

	return db, database, logger
}

func TestRelatedGroupPreviewsBatchSelection(t *testing.T) {
	t.Parallel()

	db, database, logger := newRelatedGroupTestDB(t)
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE picture (id bigint PRIMARY KEY, image_id bigint, status text);
		CREATE TEMP TABLE picture_item (picture_id bigint, item_id bigint, perspective_id integer);
		CREATE TEMP TABLE item_parent_cache (item_id bigint, parent_id bigint, tuning boolean, sport boolean);
		CREATE TEMP TABLE item (id bigint PRIMARY KEY, is_concept boolean);
		INSERT INTO item VALUES (10, false), (20, false), (30, true), (40, false), (50, false);
		INSERT INTO item_parent_cache VALUES
			(11, 10, false, false), (12, 10, true, false), (13, 10, false, true),
			(21, 20, false, false), (31, 30, false, false), (41, 40, false, false),
			(51, 50, false, false);
		INSERT INTO picture VALUES
			(1, 101, 'accepted'), (2, 102, 'accepted'), (3, 103, 'accepted'),
			(4, 104, 'accepted'), (5, 105, 'accepted'), (6, 106, 'inbox'),
			(7, 107, 'accepted'), (8, NULL, 'accepted');
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO picture_item VALUES
		(1, 11, $1), (2, 11, $2), (3, 12, $1), (4, 13, $1),
		(5, 21, $2), (6, 21, $1), (7, 31, $2), (5, 31, $1),
		(8, 41, $1), (1, 51, $2), (2, 51, $1), (5, 51, NULL)`,
		schema.PerspectiveFrontStrict, schema.PerspectiveFront)
	require.NoError(t, err)

	repository := pictures.NewRepository(db, nil, nil, nil, nil, config.DuplicateFinderConfig{}, nil, nil, nil, nil)

	groups := map[int64][]int64{
		1: {10}, 2: {20}, 3: {10, 20}, 4: {30, 20}, 5: {40}, 6: {999}, 7: {}, 8: {50},
	}
	for id := int64(100); id < 150; id++ {
		groups[id] = []int64{10, 20}
	}

	result, err := repository.RelatedGroupPreviews(t.Context(), groups)
	require.NoError(t, err)
	require.EqualValues(t, 1, logger.queries.Load())
	require.EqualValues(t, 1, result[1].ID, "stock strict-front beats tuning, sport and other perspectives")
	require.EqualValues(t, 5, result[2].ID, "inbox pictures are excluded")
	require.False(t, result[5].ImageID.Valid)
	require.Nil(t, result[6])
	require.Nil(t, result[7])

	for groupID, ancestors := range groups {
		if len(ancestors) == 0 {
			continue
		}

		selection, err := repository.PictureSelect(&query.PictureListOptions{
			Status: schema.PictureStatusAccepted,
			PictureItem: &query.PictureItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
					ParentIDs: ancestors, ItemsByParentID: &query.ItemListOptions{},
				},
			},
		}, nil, pictures.OrderByAncestorStockFrontFirst)
		require.NoError(t, err)

		var expected struct {
			ID      int64         `db:"id"`
			ImageID sql.NullInt64 `db:"image_id"`
		}

		found, err := selection.Select(goqu.T(query.PictureAlias).Col("id"),
			goqu.T(query.PictureAlias).Col("image_id")).Limit(1).ScanStructContext(t.Context(), &expected)
		require.NoError(t, err)

		if !found {
			require.Nil(t, result[groupID])

			continue
		}

		require.NotNil(t, result[groupID])
		require.Equal(t, expected.ID, result[groupID].ID)
		require.Equal(t, expected.ImageID, result[groupID].ImageID)
	}

	logger.queries.Store(0)

	empty, err := repository.RelatedGroupPreviews(t.Context(), map[int64][]int64{1: {}})
	require.NoError(t, err)
	require.Empty(t, empty)
	require.Zero(t, logger.queries.Load())

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.RelatedGroupPreviews(ctx, groups)
	require.ErrorIs(t, err, context.Canceled)
}

func TestFirstCataloguePathsMatchesRecursiveSelection(t *testing.T) {
	t.Parallel()

	db, database, logger := newRelatedGroupTestDB(t)
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE item (id bigint PRIMARY KEY, catname text, item_type_id integer);
		CREATE TEMP TABLE item_parent (
			item_id bigint, parent_id bigint, catname text, type integer, manual_catname boolean DEFAULT false
		);
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO item VALUES
		(1, 'brand', $1), (2, 'category', $2), (3, 'person', $3),
		(10, 'root', $4), (11, 'child', $4), (12, 'tuning', $4), (13, 'missing', $4),
		(14, 'skip-category', $4), (15, 'other-root', $4)`,
		schema.ItemTableItemTypeIDBrand, schema.ItemTableItemTypeIDCategory,
		schema.ItemTableItemTypeIDPerson, schema.ItemTableItemTypeIDVehicle)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO item_parent (item_id, parent_id, catname, type) VALUES
		(10, 1, 'model', $1), (11, 10, 'child', $1),
		(12, 10, 'tuned', $2), (12, 15, 'stock', $1),
		(15, 1, 'other', $1), (14, 2, 'category-path', $1), (14, 1, 'brand-path', $2)`,
		schema.ItemParentTypeDefault, schema.ItemParentTypeTuning)
	require.NoError(t, err)

	repository := items.NewRepository(db, 0, nil, nil, nil)
	ids := []int64{11, 12, 13, 14, 10, 1, 2, 3, 999, 11}
	result, err := repository.FirstCataloguePaths(t.Context(), ids)
	require.NoError(t, err)

	batchQueries := logger.queries.Load()
	require.EqualValues(t, 4, batchQueries, "shared ancestors are loaded in two levels")

	for _, id := range ids {
		expected, err := repository.CataloguePaths(t.Context(), id, items.CataloguePathOptions{BreakOnFirst: true})
		require.NoError(t, err)

		if len(expected) == 0 {
			require.Nil(t, result[id])

			continue
		}

		require.Equal(t, &expected[0], result[id])
	}

	logger.queries.Store(0)

	_, err = database.ExecContext(t.Context(), `INSERT INTO item (id, item_type_id)
		SELECT n, $1 FROM generate_series(100, 149) n`, schema.ItemTableItemTypeIDVehicle)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO item_parent (item_id, parent_id, catname, type)
		SELECT n, 10, 'extra-' || n, $1 FROM generate_series(100, 149) n`, schema.ItemParentTypeDefault)
	require.NoError(t, err)

	for id := int64(100); id < 150; id++ {
		ids = append(ids, id)
	}

	_, err = repository.FirstCataloguePaths(t.Context(), ids)
	require.NoError(t, err)
	require.Equal(t, batchQueries, logger.queries.Load(), "adding groups at the same depth adds no SQL queries")

	logger.queries.Store(0)

	empty, err := repository.FirstCataloguePaths(t.Context(), nil)
	require.NoError(t, err)
	require.Empty(t, empty)
	require.Zero(t, logger.queries.Load())
	_, err = repository.FirstCataloguePaths(t.Context(), []int64{0})
	require.Error(t, err)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.FirstCataloguePaths(ctx, ids)
	require.ErrorIs(t, err, context.Canceled)
}

func TestRelatedGroupFormattedImagesBatch(t *testing.T) {
	t.Parallel()

	db, database, logger := newRelatedGroupTestDB(t)
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE image (id integer, width integer, height integer, filesize integer, filepath text, dir text);
		CREATE TEMP TABLE image_formatted (image_id integer, format text, image_formatted_id integer);
		INSERT INTO image VALUES
			(101, 300, 200, 1000, 'one.jpg', 'format'), (102, 300, 200, 1000, 'two.jpg', 'format');
		INSERT INTO image_formatted VALUES (1, 'picture-thumb-large', 101), (2, 'picture-thumb-large', 102);
	`)
	require.NoError(t, err)
	imageStorage, err := storage.NewStorage(db, config.LoadConfig(".").ImageStorage)
	require.NoError(t, err)
	images, err := imageStorage.FormattedImages(t.Context(), []int{1, 2}, pictureThumbLargeFormat)
	require.NoError(t, err)
	require.Len(t, images, 2)
	require.EqualValues(t, 1, logger.queries.Load())

	for _, id := range []int{1, 2} {
		expected, err := imageStorage.FormattedImage(t.Context(), id, pictureThumbLargeFormat)
		require.NoError(t, err)
		require.Equal(t, expected.Src(), images[id].Src())
	}

	empty, err := (&ItemExtractor{}).extractRelatedGroupsPictures(t.Context(), nil, &items.Item{}, "en")
	require.NoError(t, err)
	require.Nil(t, empty)
}

func TestRelatedCarGroupsBatchesAncestors(t *testing.T) {
	t.Parallel()

	db, database, logger := newRelatedGroupTestDB(t)
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE item (id bigint, item_type_id integer);
		CREATE TEMP TABLE item_parent (item_id bigint, parent_id bigint);
		CREATE TEMP TABLE item_parent_cache (item_id bigint, parent_id bigint, diff integer);
		INSERT INTO item_parent VALUES (11, 1000), (12, 1000), (21, 1000);
		INSERT INTO item_parent_cache VALUES
			(11, 10, 1), (12, 10, 1), (21, 20, 1), (11, 11, 0), (11, 1000, 2);
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO item VALUES
		(10, $1), (11, $1), (12, $1), (20, $2), (21, $1), (1000, $3)`,
		schema.ItemTableItemTypeIDVehicle, schema.ItemTableItemTypeIDEngine, schema.ItemTableItemTypeIDBrand)
	require.NoError(t, err)

	repository := items.NewRepository(db, 0, nil, nil, nil)
	groups, err := repository.RelatedCarGroups(t.Context(), 1000)
	require.NoError(t, err)
	require.Len(t, groups, 2)
	require.ElementsMatch(t, []int64{11, 12}, groups[10])
	require.Equal(t, []int64{21}, groups[21])
	require.EqualValues(t, 2, logger.queries.Load())

	_, err = database.ExecContext(t.Context(), `INSERT INTO item_parent
		SELECT n, 1000 FROM generate_series(100, 149) n`)
	require.NoError(t, err)
	logger.queries.Store(0)

	groups, err = repository.RelatedCarGroups(t.Context(), 1000)
	require.NoError(t, err)
	require.Len(t, groups, 52)
	require.EqualValues(t, 2, logger.queries.Load(), "one child lookup and one shared ancestor lookup")

	logger.queries.Store(0)

	empty, err := repository.RelatedCarGroups(t.Context(), 999)
	require.NoError(t, err)
	require.Empty(t, empty)
	require.EqualValues(t, 1, logger.queries.Load())
}

func TestRelatedGroupPreviewsThroughAPI(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	brandCatname := "related-" + uuid.NewString()
	brandID := createItem(t, conn, cnt, &Item{
		Name: "Related brand", Catname: brandCatname, IsGroup: true, ItemTypeId: ItemType_ITEM_TYPE_BRAND,
	})
	categoryID := createItem(t, conn, cnt, &Item{
		Name: "Related category", Catname: "related-" + uuid.NewString(),
		IsGroup: true, ItemTypeId: ItemType_ITEM_TYPE_CATEGORY,
	})
	firstID := createItem(t, conn, cnt, &Item{
		Name: "First related car", BeginYear: 1990, ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})
	secondID := createItem(t, conn, cnt, &Item{
		Name: "Second related car", BeginYear: 2000, ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})
	repository, err := cnt.ItemsRepository(ctx)
	require.NoError(t, err)

	for _, edge := range []struct {
		itemID  int64
		catname string
	}{{firstID, "first"}, {secondID, "second"}} {
		_, err := repository.CreateItemParent(ctx, edge.itemID, brandID, schema.ItemParentTypeDefault, edge.catname)
		require.NoError(t, err)
		_, err = repository.CreateItemParent(ctx, edge.itemID, categoryID, schema.ItemParentTypeDefault, edge.catname)
		require.NoError(t, err)
	}

	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: firstID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	response, err := NewItemsClient(conn).List(ctx, &ItemsRequest{
		Language: "en", Options: &ItemListOptions{Id: categoryID}, Fields: &ItemFields{RelatedGroupPictures: true},
	})
	require.NoError(t, err)
	require.Len(t, response.GetItems(), 1)
	previews := response.GetItems()[0].GetRelatedGroupPictures()
	require.Len(t, previews, 2)
	require.Contains(t, previews[0].GetNameHtml(), "First related car")
	require.Contains(t, previews[1].GetNameHtml(), "Second related car")
	require.NotEmpty(t, previews[0].GetSrc())
	require.Empty(t, previews[1].GetSrc())
	require.Equal(t, []string{"/", brandCatname, "first"}, previews[0].GetRoute())
	require.Equal(t, []string{"/", brandCatname, "second"}, previews[1].GetRoute())
}
