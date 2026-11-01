package itemextractor

import (
	"database/sql"
	"fmt"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

// Compare all heavy branches with inline extraction on the same data. The first,
// parallel extraction also generates missing thumbnails in multiple formats.
func TestItemExtractorParallelHeavyFields(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	fixture, _ := newPreloadTestExtractor(t)
	suffix := time.Now().UnixNano()
	engineID := createTestItem(
		t,
		fixture,
		schema.ItemTableItemTypeIDEngine,
		false,
		fmt.Sprintf("Parallel engine %d", suffix),
	)
	vehicleID := createTestItemWithRow(t, fixture, schema.ItemRow{
		ItemTypeID: schema.ItemTableItemTypeIDVehicle, IsGroup: true,
		EngineItemID: sql.NullInt64{Int64: engineID, Valid: true},
	}, fmt.Sprintf("Parallel vehicle %d", suffix))
	childID := createTestItemWithRow(t, fixture, schema.ItemRow{
		ItemTypeID:   schema.ItemTableItemTypeIDVehicle,
		EngineItemID: sql.NullInt64{Int64: engineID, Valid: true},
	}, fmt.Sprintf("Parallel child %d", suffix))
	twinsID := createTestItem(
		t,
		fixture,
		schema.ItemTableItemTypeIDTwins,
		true,
		fmt.Sprintf("Parallel twins %d", suffix),
	)

	categoryID := createTestItem(
		t,
		fixture,
		schema.ItemTableItemTypeIDCategory,
		true,
		fmt.Sprintf("Parallel category %d", suffix),
	)

	for _, parent := range [][2]int64{{childID, vehicleID}, {vehicleID, twinsID}, {vehicleID, categoryID}} {
		_, err := fixture.itemsRepository.CreateItemParent(
			ctx,
			parent[0],
			parent[1],
			schema.ItemParentTypeDefault,
			"model",
		)
		require.NoError(t, err)
	}

	pictureID := createTestPicture(t, fixture, childID, schema.PerspectiveFrontStrict)
	picturesRepository, err := fixture.dependencies.PicturesRepository(ctx)
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
	repository, err := fixture.dependencies.ItemsRepository(ctx)
	require.NoError(t, err)

	extractor := fixture.Extractor

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
