package integration

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/schema"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
)

func TestRelatedGroupPreviewsThroughAPI(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	brandCatname := "related-" + uuid.NewString()
	brandID := createItem(t, &pb.Item{
		Name: "Related brand", Catname: brandCatname, IsGroup: true, ItemTypeId: pb.ItemType_ITEM_TYPE_BRAND,
	})
	categoryID := createItem(t, &pb.Item{
		Name: "Related category", Catname: "related-" + uuid.NewString(),
		IsGroup: true, ItemTypeId: pb.ItemType_ITEM_TYPE_CATEGORY,
	})
	firstID := createItem(t, &pb.Item{
		Name: "First related car", BeginYear: 1990, ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})
	secondID := createItem(t, &pb.Item{
		Name: "Second related car", BeginYear: 2000, ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})
	repository, err := cnt.ItemsRepository(ctx)
	require.NoError(t, err)

	for _, edge := range []struct {
		itemID  int64
		catname string
	}{{firstID, "first"}, {secondID, "second"}} {
		_, err := repository.CreateItemParent(ctx, edge.itemID, brandID, schema.ItemParentTypeDefault, edge.catname)
		require.NoError(t, err)
		_, err = repository.CreateItemParent(ctx, edge.itemID, categoryID, schema.ItemParentTypeDefault, edge.catname)
		require.NoError(t, err)
	}

	cfg := cnt.Config()
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: firstID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	response, err := pb.NewItemsClient(conn).List(ctx, &pb.ItemsRequest{
		Language: "en",
		Options:  &pb.ItemListOptions{Id: categoryID},
		Fields:   &pb.ItemFields{RelatedGroupPictures: true},
	})
	require.NoError(t, err)
	require.Len(t, response.GetItems(), 1)
	previews := response.GetItems()[0].GetRelatedGroupPictures()
	require.Len(t, previews, 2)
	require.Contains(t, previews[0].GetNameHtml(), "First related car")
	require.Contains(t, previews[1].GetNameHtml(), "Second related car")
	require.NotEmpty(t, previews[0].GetSrc())
	require.Empty(t, previews[1].GetSrc())
	require.Equal(t, []string{"/", brandCatname, "first"}, previews[0].GetRoute())
	require.Equal(t, []string{"/", brandCatname, "second"}, previews[1].GetRoute())
}
