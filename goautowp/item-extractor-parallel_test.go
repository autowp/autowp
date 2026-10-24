package goautowp

import (
	"context"
	"fmt"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/proto"
)

// Compare all heavy branches with inline extraction on the same data. The first,
// parallel extraction also generates missing thumbnails in multiple formats.
func TestItemExtractorParallelHeavyFields(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	suffix := time.Now().UnixNano()
	engineID := createItem(t, conn, cnt, &Item{
		Name: fmt.Sprintf("Parallel engine %d", suffix), ItemTypeId: ItemType_ITEM_TYPE_ENGINE,
	})
	vehicleID := createItem(t, conn, cnt, &Item{
		Name: fmt.Sprintf("Parallel vehicle %d", suffix), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		IsGroup: true, EngineItemId: engineID,
	})
	childID := createItem(t, conn, cnt, &Item{
		Name: fmt.Sprintf("Parallel child %d", suffix), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		EngineItemId: engineID,
	})
	twinsID := createItem(t, conn, cnt, &Item{
		Name: fmt.Sprintf("Parallel twins %d", suffix), ItemTypeId: ItemType_ITEM_TYPE_TWINS,
		IsGroup: true,
	})
	categoryID := createItem(t, conn, cnt, &Item{
		Name: fmt.Sprintf("Parallel category %d", suffix), ItemTypeId: ItemType_ITEM_TYPE_CATEGORY,
		Catname: fmt.Sprintf("parallel-%d", suffix),
	})

	client := NewItemsClient(conn)

	apiCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)
	for _, parent := range []*ItemParent{
		{ItemId: childID, ParentId: vehicleID},
		{ItemId: vehicleID, ParentId: twinsID},
		{ItemId: vehicleID, ParentId: categoryID},
	} {
		_, err := client.CreateItemParent(apiCtx, parent)
		require.NoError(t, err)
	}

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg",
		PicturePostForm{ItemID: childID, PerspectiveID: schema.PerspectiveFrontStrict},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	picturesRepository, err := cnt.PicturesRepository(ctx)
	require.NoError(t, err)
	picture, err := picturesRepository.Picture(ctx, &query.PictureListOptions{ID: pictureID}, nil, 0)
	require.NoError(t, err)
	require.True(t, picture.ImageID.Valid)

	nestedFields := &ItemFields{NameHtml: true, Route: true, HasText: true}
	fields := &ItemFields{
		NameText: true, NameHtml: true, Logo: true, Logo120: true, Brandicon: true,
		Categories:     &ItemsRequest{Fields: nestedFields},
		Twins:          &ItemsRequest{Fields: nestedFields},
		EngineVehicles: &ItemsRequest{Fields: nestedFields},
		PreviewPictures: &PreviewPicturesRequest{Pictures: &PicturesRequest{
			Options: &PictureListOptions{Status: PictureStatus_PICTURE_STATUS_ACCEPTED},
		}},
		RelatedGroupPictures: true, ItemOfDayPictures: true, SpecsContributors: true, CanEditSpecs: true,
	}
	requested := proto.Clone(fields)
	repository, err := cnt.ItemsRepository(ctx)
	require.NoError(t, err)

	extractor := cnt.ItemExtractor()

	for _, itemID := range []int64{vehicleID, engineID} {
		row, err := repository.Item(ctx, &query.ItemListOptions{
			ItemID: itemID, Language: schema.EnglishLanguageCode,
		}, convertItemFields(fields))
		require.NoError(t, err)

		// Use the same source image for logos and thumbnails without changing the fixture's item.
		row.LogoID = picture.ImageID
		actual, err := extractor.Extract(ctx, row, fields, schema.EnglishLanguageCode, UserContext{})
		require.NoError(t, err)

		inlineCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
		expected, err := extractor.Extract(inlineCtx, row, fields, schema.EnglishLanguageCode, UserContext{})
		require.NoError(t, err)
		require.True(t, proto.Equal(expected, actual),
			"parallel extraction differs for item %d\nexpected: %s\nactual: %s", itemID, expected, actual)
		require.NotEmpty(t, actual.GetLogo120().GetSrc())
		require.NotEmpty(t, actual.GetBrandicon().GetSrc())

		if itemID == vehicleID {
			require.NotEmpty(t, actual.GetCategories())
			require.NotEmpty(t, actual.GetTwins())
			require.NotEmpty(t, actual.GetRelatedGroupPictures())
			require.NotEmpty(t, actual.GetPreviewPictures().GetPictures())
			require.NotEmpty(t, actual.GetItemOfDayPictures())
		} else {
			require.NotEmpty(t, actual.GetEngineVehicles())
		}
	}

	require.True(t, proto.Equal(requested, fields), "extraction must not mutate requested fields")
}
