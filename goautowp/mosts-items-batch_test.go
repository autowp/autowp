package goautowp

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/mosts"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestMostsItemsBatchSharesFieldReads(t *testing.T) {
	t.Parallel()
	extractor, logger := newItemBatchTestExtractor(t)
	server := &MostsGRPCServer{extractor: extractor}
	fields := &ItemFields{
		Meta: true, SpecificationsCount: true, Subscription: true, HasText: true,
		CommentsCount: true, ItemLanguageCount: true, LinksCount: true,
	}
	user := UserContext{UserID: 7, Roles: []string{users.RoleModer}}
	list := []mosts.ResultItem{
		{Item: &items.Item{ID: 2}, ValueHTML: "2 <span>kg</span>"},
		{Item: &items.Item{ID: 1}, ValueHTML: "1 <span>kg</span>"},
		{Item: &items.Item{ID: 2}, ValueHTML: "0 <span>kg</span>"},
	}

	expected := make([]*MostsItem, len(list))
	for index, car := range list {
		item, err := extractor.Extract(t.Context(), car.Item, fields, "ru", user)
		require.NoError(t, err)

		expected[index] = &MostsItem{Item: item, ValueHtml: car.ValueHTML, UnitId: 42}
	}

	require.EqualValues(t, 21, logger.queries.Load())
	logger.queries.Store(0)

	actual, err := server.extractItems(t.Context(), list, 42, fields, "ru", user)
	require.NoError(t, err)
	require.EqualValues(t, 7, logger.queries.Load())
	require.True(t, proto.Equal(&MostsItems{Items: expected}, &MostsItems{Items: actual}))
}

func TestMostsPreviewGroupsShareSizeBatches(t *testing.T) {
	t.Parallel()

	picture := func(id int64) *PerspectivePictureFetcherResultPicture {
		return &PerspectivePictureFetcherResultPicture{Row: &schema.PictureRow{ID: id}}
	}
	selections := []*PerspectivePictureFetcherResult{
		{
			LargeFormat:   true,
			TotalPictures: 5,
			Pictures:      []*PerspectivePictureFetcherResultPicture{picture(1), nil, picture(2)},
		},
		{
			LargeFormat:   true,
			TotalPictures: 8,
			Pictures:      []*PerspectivePictureFetcherResultPicture{picture(3), picture(2)},
		},
		{Pictures: []*PerspectivePictureFetcherResultPicture{nil, picture(1), {}}},
		{LargeFormat: true, Pictures: []*PerspectivePictureFetcherResultPicture{nil, picture(4)}},
		{Pictures: []*PerspectivePictureFetcherResultPicture{}},
	}
	fields := &PictureFields{NameText: true, Image: true}
	original := proto.Clone(fields)

	expected := make([]*PreviewPictures, len(selections))
	for index, selection := range selections {
		pictures, err := extractPreviewPictureBatches(t.Context(), selection, fields, fakePreviewPictureExtraction)
		require.NoError(t, err)

		expected[index] = &PreviewPictures{
			LargeFormat:   selection.LargeFormat,
			TotalPictures: selection.TotalPictures,
			Pictures:      pictures,
		}
	}

	calls := make(chan bool, 2)
	result, err := extractPreviewPictureGroups(t.Context(), selections, fields,
		func(ctx context.Context, rows []*schema.PictureRow, fields *PictureFields) ([]*Picture, error) {
			calls <- fields.GetThumbLarge()

			return fakePreviewPictureExtraction(ctx, rows, fields)
		})
	require.NoError(t, err)
	require.Len(t, calls, 2)
	first, second := <-calls, <-calls
	require.NotEqual(t, first, second)

	for index := range result {
		require.True(t, proto.Equal(expected[index], result[index]))
	}

	require.True(t, proto.Equal(original, fields))
	_, err = extractPreviewPictureGroups(t.Context(), selections, fields,
		func(context.Context, []*schema.PictureRow, *PictureFields) ([]*Picture, error) {
			return nil, errPreviewBatchTest
		})
	require.ErrorIs(t, err, errPreviewBatchTest)
}
