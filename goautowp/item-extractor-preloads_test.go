package goautowp

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/proto"
)

func TestItemExtractorParallelRelationAndFieldPreloads(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	parentID := createItem(t, conn, cnt, &Item{
		Name: "item-preload-parent-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE, IsGroup: true,
	})
	childID := createItem(t, conn, cnt, &Item{
		Name: "item-preload-child-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})
	apiCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)
	_, err = NewItemsClient(conn).CreateItemParent(apiCtx, &ItemParent{ItemId: childID, ParentId: parentID})
	require.NoError(t, err)

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: parentID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	fields := &ItemFields{
		NameHtml: true, HasText: true, CommentsCount: true,
		PictureItems: &PictureItemsRequest{
			Options: &PictureItemListOptions{TypeId: PictureItemType_PICTURE_ITEM_CONTENT},
			Fields: &PictureItemFields{
				Item:    &ItemsRequest{Fields: &ItemFields{NameText: true}},
				Picture: &PicturesRequest{Fields: &PictureFields{ThumbMedium: true}},
			},
		},
		ItemParentChilds: &ItemParentsRequest{
			Fields: &ItemParentFields{Item: &ItemFields{NameText: true, HasText: true}},
		},
	}
	requested := proto.Clone(fields)

	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(2)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	goquDB := goqu.New("postgres", database)
	container := &Container{config: cfg, goquDB: goquDB}
	repository, err := container.ItemsRepository(ctx)
	require.NoError(t, err)
	rows, _, err := repository.List(ctx, &query.ItemListOptions{
		ItemIDs: []int64{parentID, childID}, Language: schema.EnglishLanguageCode,
	}, convertItemFields(fields), items.OrderByNone, false)
	require.NoError(t, err)
	require.Len(t, rows, 2)

	rows = append(rows, rows[0])
	extractor := NewItemExtractor(container)
	inlineCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
	expected, err := extractor.ExtractRows(inlineCtx, rows, fields, schema.EnglishLanguageCode, UserContext{})
	require.NoError(t, err)

	// Two relation queries and two field queries must all start before release.
	// Wrapping preloadFields as a single task would serialize its two field reads
	// and fail this barrier, even though the three outer stages ran concurrently.
	logger := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, logger, itemExtractorParallelism)
	goquDB.Logger(logger)

	lookupCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	type extractionResult struct {
		items []*Item
		err   error
	}

	done := make(chan extractionResult, 1)

	go func() {
		result, err := extractor.ExtractRows(lookupCtx, rows, fields, schema.EnglishLanguageCode, UserContext{})
		done <- extractionResult{items: result, err: err}
	}()

	unblock()

	actual := <-done
	require.NoError(t, actual.err)
	require.Len(t, actual.items, len(rows))

	for index, item := range actual.items {
		require.Equal(t, rows[index].ID, item.GetId())
		require.True(t, proto.Equal(expected[index], item), "parallel preloads differ at row %d", index)
		require.NotEmpty(t, item.GetNameHtml())

		if item.GetId() == parentID {
			require.Len(t, item.GetPictureItems().GetItems(), 1)
			require.Equal(t, pictureID, item.GetPictureItems().GetItems()[0].GetPictureId())
			require.NotEmpty(t, item.GetPictureItems().GetItems()[0].GetPicture().GetThumbMedium().GetSrc())
			require.Len(t, item.GetItemParentChilds().GetItems(), 1)
			require.Equal(t, childID, item.GetItemParentChilds().GetItems()[0].GetItem().GetId())
		} else {
			require.Empty(t, item.GetPictureItems().GetItems())
			require.Empty(t, item.GetItemParentChilds().GetItems())
		}
	}

	require.True(t, proto.Equal(requested, fields), "preloads must not change requested fields or filters")

	canceledCtx, cancelRequest := context.WithCancel(ctx)
	cancelRequest()

	result, err := extractor.ExtractRows(canceledCtx, rows, fields, schema.EnglishLanguageCode, UserContext{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, result)

	_, err = extractor.ExtractRows(ctx, rows, &ItemFields{
		ItemParentChilds: &ItemParentsRequest{Limit: 1}, CommentsCount: true,
	}, schema.EnglishLanguageCode, UserContext{})
	require.ErrorIs(t, err, errPreloadNotImplemented)
}

func TestItemExtractorEmptyPreloads(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	result, err := (&ItemExtractor{}).ExtractRows(ctx, nil, &ItemFields{
		PictureItems: &PictureItemsRequest{}, ItemParentChilds: &ItemParentsRequest{Limit: 1}, HasText: true,
	}, "en", UserContext{})
	require.NoError(t, err)
	require.NotNil(t, result)
	require.Empty(t, result)
}
