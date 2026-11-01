package itemparentextractor

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/stretchr/testify/require"
)

func TestItemParentExtractorEmptyAndUnselectedFields(t *testing.T) {
	t.Parallel()

	extractor := &Extractor{}
	rows := []*items.ItemParent{
		{ItemParentRow: schema.ItemParentRow{ItemID: 7, ParentID: 9}},
		{ItemParentRow: schema.ItemParentRow{ItemID: 8, ParentID: 9}},
	}
	result, err := extractor.ExtractRows(t.Context(), rows, nil, "en", authn.User{})
	require.NoError(t, err)
	require.Len(t, result, len(rows))

	for index, relation := range result {
		require.Equal(t, rows[index].ItemID, relation.GetItemId())
		require.Equal(t, rows[index].ParentID, relation.GetParentId())
		require.Nil(t, relation.GetItem())
		require.Nil(t, relation.GetParent())
	}

	fields := &pb.ItemParentFields{
		Item: &pb.ItemFields{}, Parent: &pb.ItemFields{},
		DuplicateParent: &pb.ItemFields{}, DuplicateChild: &pb.ItemFields{},
		ChildDescendantPictures: &pb.PicturesRequest{},
	}
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	empty, err := extractor.ExtractRows(ctx, nil, fields, "en", authn.User{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
}
