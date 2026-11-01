package pictureitemextractor

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/stretchr/testify/require"
)

func TestPictureItemExtractorEmptyAndUnselectedFields(t *testing.T) {
	t.Parallel()

	extractor := &Extractor{}
	fields := &pb.PictureItemFields{
		Item: &pb.ItemsRequest{}, Picture: &pb.PicturesRequest{}, ItemParentCacheAncestor: &pb.ItemParentCacheRequest{},
	}
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	empty, err := extractor.ExtractRows(ctx, nil, fields, "en", authn.User{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)

	result, err := extractor.ExtractRows(t.Context(), []*schema.PictureItemRow{{ItemID: 7, PictureID: 9}},
		nil, "en", authn.User{})
	require.NoError(t, err)
	require.Len(t, result, 1)
	require.EqualValues(t, 7, result[0].GetItemId())
	require.EqualValues(t, 9, result[0].GetPictureId())
	require.Nil(t, result[0].GetItem())
	require.Nil(t, result[0].GetPicture())
	require.Nil(t, result[0].GetItemParentCacheAncestors())
}
