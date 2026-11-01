package itemextractor

import (
	"context"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestItemExtractorParallelRelationAndFieldPreloads(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	fixture, goquDB := newPreloadTestExtractor(t)
	parentID := createTestItem(t, fixture, schema.ItemTableItemTypeIDVehicle, true, "Item preload parent")
	childID := createTestItem(t, fixture, schema.ItemTableItemTypeIDVehicle, false, "Item preload child")
	_, err := fixture.itemsRepository.CreateItemParent(ctx, childID, parentID, schema.ItemParentTypeDefault, "child")
	require.NoError(t, err)

	pictureID := createTestPicture(t, fixture, parentID, schema.PerspectiveFrontStrict)
	fields := &pb.ItemFields{
		NameHtml: true, HasText: true, CommentsCount: true,
		PictureItems: &pb.PictureItemsRequest{
			Options: &pb.PictureItemListOptions{TypeId: pb.PictureItemType_PICTURE_ITEM_CONTENT},
			Fields: &pb.PictureItemFields{
				Item:    &pb.ItemsRequest{Fields: &pb.ItemFields{NameText: true}},
				Picture: &pb.PicturesRequest{Fields: &pb.PictureFields{ThumbMedium: true}},
			},
		},
		ItemParentChilds: &pb.ItemParentsRequest{
			Fields: &pb.ItemParentFields{Item: &pb.ItemFields{NameText: true, HasText: true}},
		},
	}
	requested := proto.Clone(fields)

	repository := fixture.itemsRepository
	rows, _, err := repository.List(ctx, &query.ItemListOptions{
		ItemIDs: []int64{parentID, childID}, Language: schema.EnglishLanguageCode,
	}, itemmapper.FieldsFromProto(fields), items.OrderByNone, false)
	require.NoError(t, err)
	require.Len(t, rows, 2)

	rows = append(rows, rows[0])
	extractor := fixture.Extractor
	inlineCtx := extraction.WithInlineLookups(ctx)
	expected, err := extractor.ExtractRows(inlineCtx, rows, fields, schema.EnglishLanguageCode, authn.User{})
	require.NoError(t, err)

	// Two relation queries and two field queries must all start before release.
	// Wrapping preloadFields as a single task would serialize its two field reads
	// and fail this barrier, even though the three outer stages ran concurrently.
	logger := &testutil.QueryLogger{}
	unblock := testutil.BlockQueries(t, logger, extraction.DefaultParallelism)
	goquDB.Logger(logger)

	lookupCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	type extractionResult struct {
		items []*pb.Item
		err   error
	}

	done := make(chan extractionResult, 1)

	go func() {
		result, err := extractor.ExtractRows(lookupCtx, rows, fields, schema.EnglishLanguageCode, authn.User{})
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

	result, err := extractor.ExtractRows(canceledCtx, rows, fields, schema.EnglishLanguageCode, authn.User{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, result)

	_, err = extractor.ExtractRows(ctx, rows, &pb.ItemFields{
		ItemParentChilds: &pb.ItemParentsRequest{Limit: 1}, CommentsCount: true,
	}, schema.EnglishLanguageCode, authn.User{})
	require.ErrorIs(t, err, ErrPreloadNotImplemented)
}
