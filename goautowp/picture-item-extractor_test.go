package goautowp

import (
	"context"
	"database/sql"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
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

	parentID := createItem(t, conn, cnt, &pb.Item{
		Name: "picture-item-parent-" + uuid.NewString(), ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE, IsGroup: true,
	})
	itemID := createItem(t, conn, cnt, &pb.Item{
		Name: "picture-item-child-" + uuid.NewString(), ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})
	apiCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken)
	_, err = pb.NewItemsClient(conn).CreateItemParent(apiCtx, &pb.ItemParent{ItemId: itemID, ParentId: parentID})
	require.NoError(t, err)

	pictureIDs := make([]int64, 0, 2)

	for range 2 {
		pictureID := addPicture(t, cnt, conn, "./test/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
			pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
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
	fields := &pb.PictureItemFields{
		Item: &pb.ItemsRequest{
			Options: &pb.ItemListOptions{TypeId: pb.ItemType_ITEM_TYPE_VEHICLE},
			Fields:  &pb.ItemFields{NameHtml: true, NameText: true, HasText: true},
		},
		ItemParentCacheAncestor: &pb.ItemParentCacheRequest{
			Options: &pb.ItemParentCacheListOptions{ParentId: parentID},
			Fields:  &pb.ItemParentCacheFields{ParentItem: &pb.ItemsRequest{Fields: &pb.ItemFields{NameHtml: true}}},
		},
		Picture: &pb.PicturesRequest{
			Options: &pb.PictureListOptions{Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED},
			Fields:  &pb.PictureFields{NameText: true, ThumbMedium: true},
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
	extractor := container.PictureItemExtractor()

	inlineCtx := extraction.WithInlineLookups(ctx)
	expected, err := extractor.ExtractRows(inlineCtx, rows, fields, schema.EnglishLanguageCode, authn.User{})
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
		items []*pb.PictureItem
		err   error
	}

	done := make(chan extractionResult, 1)

	go func() {
		actual, err := extractor.ExtractRows(lookupCtx, rows, fields, schema.EnglishLanguageCode, authn.User{})
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

	result, err := extractor.ExtractRows(canceledCtx, rows, fields, schema.EnglishLanguageCode, authn.User{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, result)
}
