package goautowp

import (
	"fmt"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
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
	engineID := createItem(t, conn, cnt, &pb.Item{
		Name: fmt.Sprintf("Parallel engine %d", suffix), ItemTypeId: pb.ItemType_ITEM_TYPE_ENGINE,
	})
	vehicleID := createItem(t, conn, cnt, &pb.Item{
		Name: fmt.Sprintf("Parallel vehicle %d", suffix), ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
		IsGroup: true, EngineItemId: engineID,
	})
	childID := createItem(t, conn, cnt, &pb.Item{
		Name: fmt.Sprintf("Parallel child %d", suffix), ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
		EngineItemId: engineID,
	})
	twinsID := createItem(t, conn, cnt, &pb.Item{
		Name: fmt.Sprintf("Parallel twins %d", suffix), ItemTypeId: pb.ItemType_ITEM_TYPE_TWINS,
		IsGroup: true,
	})
	categoryID := createItem(t, conn, cnt, &pb.Item{
		Name: fmt.Sprintf("Parallel category %d", suffix), ItemTypeId: pb.ItemType_ITEM_TYPE_CATEGORY,
		Catname: fmt.Sprintf("parallel-%d", suffix),
	})

	client := pb.NewItemsClient(conn)

	apiCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken)
	for _, parent := range []*pb.ItemParent{
		{ItemId: childID, ParentId: vehicleID},
		{ItemId: vehicleID, ParentId: twinsID},
		{ItemId: vehicleID, ParentId: categoryID},
	} {
		_, err := client.CreateItemParent(apiCtx, parent)
		require.NoError(t, err)
	}

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg",
		pictureshttp.PicturePostForm{ItemID: childID, PerspectiveID: schema.PerspectiveFrontStrict},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	picturesRepository, err := cnt.PicturesRepository(ctx)
	require.NoError(t, err)
	picture, err := picturesRepository.Picture(ctx, &query.PictureListOptions{ID: pictureID}, nil, 0)
	require.NoError(t, err)
	require.True(t, picture.ImageID.Valid)

	nestedFields := &pb.ItemFields{NameHtml: true, Route: true, HasText: true}
	fields := &pb.ItemFields{
		NameText: true, NameHtml: true, Logo: true, Logo120: true, Brandicon: true,
		Categories:     &pb.ItemsRequest{Fields: nestedFields},
		Twins:          &pb.ItemsRequest{Fields: nestedFields},
		EngineVehicles: &pb.ItemsRequest{Fields: nestedFields},
		PreviewPictures: &pb.PreviewPicturesRequest{Pictures: &pb.PicturesRequest{
			Options: &pb.PictureListOptions{Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED},
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
		}, itemmapper.FieldsFromProto(fields))
		require.NoError(t, err)

		// Use the same source image for logos and thumbnails without changing the fixture's item.
		row.LogoID = picture.ImageID
		actual, err := extractor.Extract(ctx, row, fields, schema.EnglishLanguageCode, authn.User{})
		require.NoError(t, err)

		inlineCtx := extraction.WithInlineLookups(ctx)
		expected, err := extractor.Extract(inlineCtx, row, fields, schema.EnglishLanguageCode, authn.User{})
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
