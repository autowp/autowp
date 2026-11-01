package itemextractor

import (
	"context"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestPictureItemExtractorParallelPreloads(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	fixture, goquDB := newPreloadTestExtractor(t)
	parentID := createTestItem(t, fixture, schema.ItemTableItemTypeIDVehicle, true, "Picture-item parent")
	itemID := createTestItem(t, fixture, schema.ItemTableItemTypeIDVehicle, false, "Picture-item child")
	_, err := fixture.itemsRepository.CreateItemParent(ctx, itemID, parentID, schema.ItemParentTypeDefault, "child")
	require.NoError(t, err)

	pictureIDs := make([]int64, 0, 2)
	for range 2 {
		pictureIDs = append(pictureIDs, createTestPicture(t, fixture, itemID, schema.PerspectiveFrontStrict))
	}

	repository, err := fixture.dependencies.PicturesRepository(ctx)
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

	extractor := fixture.pictureItemExtractor

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

	logger := &testutil.QueryLogger{BeforeQuery: func() {
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
