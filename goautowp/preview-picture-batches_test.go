package goautowp

import (
	"context"
	"errors"
	"strconv"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

var errPreviewBatchTest = errors.New("preview batch test error")

func fakePreviewPictureExtraction(
	_ context.Context, rows []*schema.PictureRow, fields *PictureFields,
) ([]*Picture, error) {
	result := make([]*Picture, 0, len(rows))
	for _, row := range rows {
		picture := &Picture{Id: row.ID}
		if fields.GetImage() {
			picture.Image = &Image{Src: "original"}
		}

		if fields.GetThumbLarge() {
			picture.ThumbLarge = &Image{Src: "large"}
		}

		if fields.GetThumbMedium() {
			picture.ThumbMedium = &Image{Src: "medium"}
		}

		result = append(result, picture)
	}

	return result, nil
}

func TestPreviewPictureBatchesPreserveSlotsAndFields(t *testing.T) {
	t.Parallel()

	first := &PerspectivePictureFetcherResultPicture{Row: &schema.PictureRow{ID: 11}}
	second := &PerspectivePictureFetcherResultPicture{Row: &schema.PictureRow{ID: 22}}

	for _, testCase := range []struct {
		name     string
		large    bool
		pictures []*PerspectivePictureFetcherResultPicture
		batches  int
	}{
		{name: "medium only", pictures: []*PerspectivePictureFetcherResultPicture{first, nil, second}, batches: 1},
		{
			name: "both sizes", large: true,
			pictures: []*PerspectivePictureFetcherResultPicture{first, nil, second}, batches: 2,
		},
		{name: "large only", large: true, pictures: []*PerspectivePictureFetcherResultPicture{first}, batches: 1},
		{name: "empty first slot", large: true, pictures: []*PerspectivePictureFetcherResultPicture{nil, second}, batches: 1},
		{name: "no pictures", pictures: []*PerspectivePictureFetcherResultPicture{nil, {}}, batches: 0},
	} {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			requested := &PictureFields{Image: true, ThumbLarge: true, ThumbMedium: true}
			original := proto.Clone(requested)
			calls := make(chan *PictureFields, 2)
			result, err := extractPreviewPictureBatches(t.Context(), &PerspectivePictureFetcherResult{
				LargeFormat: testCase.large, Pictures: testCase.pictures,
			}, requested, func(ctx context.Context, rows []*schema.PictureRow, fields *PictureFields) ([]*Picture, error) {
				calls <- fields

				return fakePreviewPictureExtraction(ctx, rows, fields)
			})
			require.NoError(t, err)
			require.Len(t, result, len(testCase.pictures))
			require.Len(t, calls, testCase.batches)
			require.True(t, proto.Equal(original, requested), "requested fields must not change")

			for range testCase.batches {
				fields := <-calls
				require.True(t, fields.GetNameText())
				require.True(t, fields.GetImage())
				require.NotEqual(t, fields.GetThumbLarge(), fields.GetThumbMedium())
			}

			for index, picture := range testCase.pictures {
				if picture == nil || picture.Row == nil {
					require.IsType(t, &NullPicture_Null{}, result[index].GetKind())

					continue
				}

				actual := result[index].GetPicture()
				require.Equal(t, picture.Row.ID, actual.GetId())
				require.Equal(t, "original", actual.GetImage().GetSrc())

				if testCase.large && index == 0 {
					require.Equal(t, "large", actual.GetThumbLarge().GetSrc())
					require.Nil(t, actual.GetThumbMedium())
				} else {
					require.Equal(t, "medium", actual.GetThumbMedium().GetSrc())
					require.Nil(t, actual.GetThumbLarge())
				}
			}
		})
	}
}

func TestPreviewPictureBatchesShareBudgetInsideItemBranch(t *testing.T) {
	t.Parallel()

	for _, limit := range []int{pictureExtractorParallelism, 2} {
		t.Run(strconv.Itoa(limit), func(t *testing.T) {
			t.Parallel()

			ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
			defer cancel()

			ctx = context.WithValue(ctx, itemLookupContextKey{}, true)
			ctx = context.WithValue(ctx, pictureExtractionParallelismKey{}, limit)
			started := make(chan int, 2)
			release := make(chan struct{})
			done := make(chan error, 1)
			plan := &PerspectivePictureFetcherResult{
				LargeFormat: true,
				Pictures: []*PerspectivePictureFetcherResultPicture{
					{Row: &schema.PictureRow{ID: 1}}, {Row: &schema.PictureRow{ID: 2}},
				},
			}

			go func() {
				_, err := extractPreviewPictureBatches(ctx, plan, nil,
					func(ctx context.Context, rows []*schema.PictureRow, fields *PictureFields) ([]*Picture, error) {
						started <- pictureExtractionParallelism(ctx)

						select {
						case <-release:
							return fakePreviewPictureExtraction(ctx, rows, fields)
						case <-ctx.Done():
							return nil, ctx.Err()
						}
					})
				done <- err
			}()

			for range 2 {
				select {
				case budget := <-started:
					require.Equal(t, limit/2, budget, "sibling batches must share the existing budget")
				case <-ctx.Done():
					t.Fatal("preview batches did not start together inside an item branch")
				}
			}

			close(release)
			require.NoError(t, <-done)
		})
	}
}

func TestPreviewPictureBatchesUseSerialFallback(t *testing.T) {
	t.Parallel()

	ctx := context.WithValue(t.Context(), pictureExtractionParallelismKey{}, 1)
	plan := &PerspectivePictureFetcherResult{
		LargeFormat: true,
		Pictures: []*PerspectivePictureFetcherResultPicture{
			{Row: &schema.PictureRow{ID: 1}}, {Row: &schema.PictureRow{ID: 2}},
		},
	}

	var mediumFinished atomic.Bool

	result, err := extractPreviewPictureBatches(ctx, plan, nil,
		func(ctx context.Context, rows []*schema.PictureRow, fields *PictureFields) ([]*Picture, error) {
			if pictureExtractionParallelism(ctx) != 1 || (fields.GetThumbLarge() && !mediumFinished.Load()) {
				return nil, errPreviewBatchTest
			}

			pictures, err := fakePreviewPictureExtraction(ctx, rows, fields)

			mediumFinished.Store(true)

			return pictures, err
		})
	require.NoError(t, err)
	require.Len(t, result, 2)
}

func TestPreviewPictureBatchesCancelFailedSibling(t *testing.T) {
	t.Parallel()

	for _, failLarge := range []bool{false, true} {
		t.Run(map[bool]string{false: "medium fails", true: "large fails"}[failLarge], func(t *testing.T) {
			t.Parallel()

			ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
			defer cancel()

			started := make(chan struct{})

			var joined atomic.Bool

			result, err := extractPreviewPictureBatches(ctx, &PerspectivePictureFetcherResult{
				LargeFormat: true,
				Pictures: []*PerspectivePictureFetcherResultPicture{
					{Row: &schema.PictureRow{ID: 1}}, {Row: &schema.PictureRow{ID: 2}},
				},
			}, nil, func(ctx context.Context, _ []*schema.PictureRow, fields *PictureFields) ([]*Picture, error) {
				if fields.GetThumbLarge() == failLarge {
					select {
					case <-started:
						return nil, errPreviewBatchTest
					case <-ctx.Done():
						return nil, ctx.Err()
					}
				}

				close(started)
				<-ctx.Done()
				joined.Store(true)

				return nil, ctx.Err()
			})
			require.ErrorIs(t, err, errPreviewBatchTest)
			require.NoError(t, ctx.Err())
			require.Nil(t, result)
			require.True(t, joined.Load(), "canceled batch must finish before returning")
		})
	}
}

func TestPreviewPictureBatchesWithRealImages(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	itemID := createItem(t, conn, cnt, &Item{
		Name: "preview-batches-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})
	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	repository, err := cnt.PicturesRepository(ctx)
	require.NoError(t, err)
	row, err := repository.Picture(ctx, &query.PictureListOptions{ID: pictureID}, nil, 0)
	require.NoError(t, err)

	// Force both size buckets without uploading 31 images. Reusing the source also
	// exercises concurrent generation of different formats for the same image.
	plan := &PerspectivePictureFetcherResult{
		LargeFormat: true,
		Pictures:    []*PerspectivePictureFetcherResultPicture{{Row: row}, nil, {Row: row}},
	}
	extractor := cnt.PictureExtractor()
	extract := func(ctx context.Context, rows []*schema.PictureRow, fields *PictureFields) ([]*Picture, error) {
		return extractor.ExtractRows(ctx, rows, fields, schema.EnglishLanguageCode, UserContext{})
	}
	requested := &PictureFields{Image: true, ThumbLarge: true}
	original := proto.Clone(requested)
	actual, err := extractPreviewPictureBatches(ctx, plan, requested, extract)
	require.NoError(t, err)

	serialCtx := context.WithValue(ctx, pictureExtractionParallelismKey{}, 1)
	expected, err := extractPreviewPictureBatches(serialCtx, plan, requested, extract)
	require.NoError(t, err)
	require.Len(t, actual, len(expected))

	for index := range expected {
		require.True(t, proto.Equal(expected[index], actual[index]), "preview differs at slot %d", index)
	}

	require.NotEmpty(t, actual[0].GetPicture().GetThumbLarge().GetSrc())
	require.NotEmpty(t, actual[2].GetPicture().GetThumbMedium().GetSrc())
	require.True(t, proto.Equal(original, requested))
}
