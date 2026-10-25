package goautowp

import (
	"context"
	"database/sql"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/proto"
)

func TestPictureItemExtractorParallelPreloads(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	parentID := createItem(t, conn, cnt, &Item{
		Name: "picture-item-parent-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE, IsGroup: true,
	})
	itemID := createItem(t, conn, cnt, &Item{
		Name: "picture-item-child-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})
	apiCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)
	_, err = NewItemsClient(conn).CreateItemParent(apiCtx, &ItemParent{ItemId: itemID, ParentId: parentID})
	require.NoError(t, err)

	pictureIDs := make([]int64, 0, 2)

	for range 2 {
		pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
			PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
		pictureIDs = append(pictureIDs, pictureID)
	}

	repository, err := cnt.PicturesRepository(ctx)
	require.NoError(t, err)
	rows, err := repository.PictureItems(ctx, &query.PictureItemListOptions{
		ItemID: itemID, PictureIDs: pictureIDs,
	}, pictures.PictureItemOrderByNone, 0)
	require.NoError(t, err)
	require.Len(t, rows, 2)

	// Preserve arbitrary input order and repeated relations when associating maps.
	rows[0], rows[1] = rows[1], rows[0]
	rows = append(rows, rows[0])
	fields := &PictureItemFields{
		Item: &ItemsRequest{
			Options: &ItemListOptions{TypeId: ItemType_ITEM_TYPE_VEHICLE},
			Fields:  &ItemFields{NameHtml: true, NameText: true, HasText: true},
		},
		ItemParentCacheAncestor: &ItemParentCacheRequest{
			Options: &ItemParentCacheListOptions{ParentId: parentID},
			Fields:  &ItemParentCacheFields{ParentItem: &ItemsRequest{Fields: &ItemFields{NameHtml: true}}},
		},
		Picture: &PicturesRequest{
			Options: &PictureListOptions{Status: PictureStatus_PICTURE_STATUS_ACCEPTED},
			Fields:  &PictureFields{NameText: true, ThumbMedium: true},
		},
	}
	requested := proto.Clone(fields)

	// A separate DB/logger avoids changing logging on the shared integration container.
	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(2)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	goquDB := goqu.New("postgres", database)
	container := &Container{config: cfg, goquDB: goquDB}
	extractor := NewPictureItemExtractor(container)
	inlineCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
	expected, err := extractor.ExtractRows(inlineCtx, rows, fields, schema.EnglishLanguageCode, UserContext{})
	require.NoError(t, err)

	// Block the first SQL call in each branch before connection acquisition. A
	// sequential implementation cannot reach all three calls before release.
	const branches = 3

	started := make(chan struct{}, branches)
	release := make(chan struct{})

	var once sync.Once

	unblock := func() { once.Do(func() { close(release) }) }
	t.Cleanup(unblock)

	var observed atomic.Int32

	logger := &itemBatchQueryLogger{beforeQuery: func() {
		if observed.Add(1) <= branches {
			started <- struct{}{}

			<-release
		}
	}}
	goquDB.Logger(logger)

	lookupCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	type extractionResult struct {
		items []*PictureItem
		err   error
	}

	done := make(chan extractionResult, 1)

	go func() {
		actual, err := extractor.ExtractRows(lookupCtx, rows, fields, schema.EnglishLanguageCode, UserContext{})
		done <- extractionResult{items: actual, err: err}
	}()

	func() {
		defer unblock()

		for range branches {
			select {
			case <-started:
			case <-lookupCtx.Done():
				t.Fatal("picture-item preloads did not start together")
			}
		}
	}()

	actual := <-done
	require.NoError(t, actual.err)
	require.Len(t, actual.items, len(rows))

	for index, result := range actual.items {
		require.True(t, proto.Equal(expected[index], result), "parallel extraction differs at row %d", index)
		require.Equal(t, rows[index].PictureID, result.GetPictureId())
		require.Equal(t, rows[index].ItemID, result.GetItem().GetId())
		require.Equal(t, rows[index].PictureID, result.GetPicture().GetId())
		require.NotEmpty(t, result.GetItem().GetNameHtml())
		require.NotEmpty(t, result.GetPicture().GetThumbMedium().GetSrc())
		require.Len(t, result.GetItemParentCacheAncestors().GetItems(), 1)
		require.Equal(t, parentID, result.GetItemParentCacheAncestors().GetItems()[0].GetParentItem().GetId())
	}

	require.True(t, proto.Equal(requested, fields), "preloads must not change request fields or filters")

	canceledCtx, cancelRequest := context.WithCancel(ctx)
	cancelRequest()

	result, err := extractor.ExtractRows(canceledCtx, rows, fields, schema.EnglishLanguageCode, UserContext{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, result)
}

func TestPictureItemExtractorEmptyAndUnselectedFields(t *testing.T) {
	t.Parallel()

	extractor := &PictureItemExtractor{}
	fields := &PictureItemFields{
		Item: &ItemsRequest{}, Picture: &PicturesRequest{}, ItemParentCacheAncestor: &ItemParentCacheRequest{},
	}
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	empty, err := extractor.ExtractRows(ctx, nil, fields, "en", UserContext{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)

	result, err := extractor.ExtractRows(t.Context(), []*schema.PictureItemRow{{ItemID: 7, PictureID: 9}},
		nil, "en", UserContext{})
	require.NoError(t, err)
	require.Len(t, result, 1)
	require.EqualValues(t, 7, result[0].GetItemId())
	require.EqualValues(t, 9, result[0].GetPictureId())
	require.Nil(t, result[0].GetItem())
	require.Nil(t, result[0].GetPicture())
	require.Nil(t, result[0].GetItemParentCacheAncestors())
}
