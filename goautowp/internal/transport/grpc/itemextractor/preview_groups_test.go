package itemextractor

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/picturefetcher"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestMostsPreviewGroupsShareSizeBatches(t *testing.T) {
	t.Parallel()

	picture := func(id int64) *picturefetcher.Picture {
		return &picturefetcher.Picture{Row: &schema.PictureRow{ID: id}}
	}
	selections := []*picturefetcher.Result{
		{
			LargeFormat:   true,
			TotalPictures: 5,
			Pictures:      []*picturefetcher.Picture{picture(1), nil, picture(2)},
		},
		{
			LargeFormat:   true,
			TotalPictures: 8,
			Pictures:      []*picturefetcher.Picture{picture(3), picture(2)},
		},
		{Pictures: []*picturefetcher.Picture{nil, picture(1), {}}},
		{LargeFormat: true, Pictures: []*picturefetcher.Picture{nil, picture(4)}},
		{Pictures: []*picturefetcher.Picture{}},
	}
	fields := &pb.PictureFields{NameText: true, Image: true}
	original := proto.Clone(fields)

	expected := make([]*pb.PreviewPictures, len(selections))
	for index, selection := range selections {
		pictures, err := extractPreviewPictureBatches(t.Context(), selection, fields, fakePreviewPictureExtraction)
		require.NoError(t, err)

		expected[index] = &pb.PreviewPictures{
			LargeFormat:   selection.LargeFormat,
			TotalPictures: selection.TotalPictures,
			Pictures:      pictures,
		}
	}

	calls := make(chan bool, 2)
	result, err := extractPreviewPictureGroups(t.Context(), selections, fields,
		func(ctx context.Context, rows []*schema.PictureRow, fields *pb.PictureFields) ([]*pb.Picture, error) {
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
		func(context.Context, []*schema.PictureRow, *pb.PictureFields) ([]*pb.Picture, error) {
			return nil, errPreviewBatchTest
		})
	require.ErrorIs(t, err, errPreviewBatchTest)
}
