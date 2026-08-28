package goautowp

import (
	"fmt"
	"math/rand"
	"slices"
	"strconv"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
	"google.golang.org/protobuf/types/known/wrapperspb"
)

const tokenWithInvalidSignature = "eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9." +
	"eyJhdWQiOiJkZWZhdWx0Iiwic3ViIjoiMSJ9." +
	"yuzUurjlDfEKchYseIrHQ1D5_RWnSuMxM-iK9FDNlQBBw8kCz3H-94xHvyd9pAA6Ry2-YkGi1v6Y3AHIpkDpcQ"

func TestTopCategoriesList(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewItemsClient(conn)

	_, err := client.GetTopCategoriesList(ctx, &GetTopCategoriesListRequest{
		Language: schema.RussianLanguageCode,
	})
	require.NoError(t, err)
}

func TestGetTwinsBrandsList(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	ctx := t.Context()
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	brand1 := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand1-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand1-%d", random.Int()),
	})

	brand2 := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand2-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand2-%d", random.Int()),
	})

	vehicle1 := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle1-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	vehicle2 := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle2-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	twins := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("twins-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_TWINS,
	})

	client := NewItemsClient(conn)

	kc := cnt.Keycloak()

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: vehicle1, ParentId: brand1, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: vehicle2, ParentId: brand2, Catname: "vehicle2",
		},
	)
	require.NoError(t, err)

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: vehicle1, ParentId: twins, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: vehicle2, ParentId: twins, Catname: "vehicle2",
		},
	)
	require.NoError(t, err)

	res, err := client.GetTopTwinsBrandsList(ctx, &GetTopTwinsBrandsListRequest{
		Language: schema.RussianLanguageCode,
	})
	require.NoError(t, err)
	require.NotEmpty(t, res)

	r6, err := client.GetTwinsBrandsList(ctx, &GetTwinsBrandsListRequest{
		Language: schema.RussianLanguageCode,
	})
	require.NoError(t, err)
	require.NotEmpty(t, r6)
	require.NotEmpty(t, r6.GetItems())
}

func TestTopBrandsList(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewItemsClient(conn)

	res, err := client.GetTopBrandsList(ctx, &GetTopBrandsListRequest{
		Language: schema.RussianLanguageCode,
	})
	require.NoError(t, err)
	require.NotEmpty(t, res)
}

func TestTopPersonsAuthorList(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewItemsClient(conn)

	r, err := client.GetTopPersonsList(ctx, &GetTopPersonsListRequest{
		Language:        schema.RussianLanguageCode,
		PictureItemType: PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.NoError(t, err)
	require.NotEmpty(t, r)
}

func TestTopPersonsContentList(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewItemsClient(conn)

	r, err := client.GetTopPersonsList(ctx, &GetTopPersonsListRequest{
		Language:        schema.RussianLanguageCode,
		PictureItemType: PictureItemType_PICTURE_ITEM_CONTENT,
	})
	require.NoError(t, err)
	require.NotEmpty(t, r)
}

func TestTopFactoriesList(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewItemsClient(conn)

	r, err := client.GetTopFactoriesList(ctx, &GetTopFactoriesListRequest{
		Language: schema.RussianLanguageCode,
	})
	require.NoError(t, err)
	require.NotEmpty(t, r)
}

func TestContentLanguages(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewItemsClient(conn)

	r, err := client.GetContentLanguages(ctx, &emptypb.Empty{})
	require.NoError(t, err)
	require.NotEmpty(t, r)
	require.Greater(t, len(r.GetLanguages()), 1)
}

func TestItemLinks(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	client := NewItemsClient(conn)

	r1, err := client.CreateItemLink(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemLink{
			Name:   "Link 1 ",
			Url:    " https://example.org",
			Type:   " club",
			ItemId: 1,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, r1.GetId())

	r2, err := client.GetItemLink(ctx, &ItemLinksRequest{
		Options: &ItemLinkListOptions{
			Id: r1.GetId(),
		},
	})
	require.NoError(t, err)
	require.NotEmpty(t, r1.GetId())

	require.Equal(t, r1.GetId(), r2.GetId())
	require.Equal(t, "Link 1", r2.GetName())
	require.Equal(t, "https://example.org", r2.GetUrl())
	require.Equal(t, "club", r2.GetType())
	require.Equal(t, int64(1), r2.GetItemId())

	_, err = client.UpdateItemLink(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemLink{
			Id:     r1.GetId(),
			Name:   "Link 2",
			Url:    "https://example2.org",
			Type:   "default",
			ItemId: 2,
		},
	)
	require.NoError(t, err)

	r3, err := client.GetItemLink(ctx, &ItemLinksRequest{
		Options: &ItemLinkListOptions{
			Id: r1.GetId(),
		},
	})
	require.NoError(t, err)
	require.NotEmpty(t, r1.GetId())

	require.Equal(t, r1.GetId(), r3.GetId())
	require.Equal(t, "Link 2", r3.GetName())
	require.Equal(t, "https://example2.org", r3.GetUrl())
	require.Equal(t, "default", r3.GetType())
	require.Equal(t, int64(2), r3.GetItemId())

	r4, err := client.GetItemLinks(ctx, &ItemLinksRequest{
		Options: &ItemLinkListOptions{
			ItemId: r3.GetItemId(),
		},
	})
	require.NoError(t, err)
	require.NotEmpty(t, r4.GetItems())
}

func TestItemVehicleTypes(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	client := NewItemsClient(conn)

	_, err = client.CreateItemVehicleType(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemVehicleType{
			ItemId:        1,
			VehicleTypeId: 1,
		},
	)
	require.NoError(t, err)

	r2, err := client.GetItemVehicleType(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemVehicleTypeRequest{
			ItemId:        1,
			VehicleTypeId: 1,
		},
	)
	require.NoError(t, err)
	require.Equal(t, int64(1), r2.GetItemId())
	require.Equal(t, int64(1), r2.GetVehicleTypeId())

	_, err = client.CreateItemVehicleType(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemVehicleType{
			ItemId:        1,
			VehicleTypeId: 2,
		},
	)
	require.NoError(t, err)

	r4, err := client.GetItemVehicleTypes(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&GetItemVehicleTypesRequest{
			ItemId: 1,
		},
	)
	require.NoError(t, err)
	require.Len(t, r4.GetItems(), 2)

	_, err = client.DeleteItemVehicleType(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemVehicleTypeRequest{
			ItemId:        1,
			VehicleTypeId: 1,
		},
	)
	require.NoError(t, err)

	r6, err := client.GetItemVehicleType(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemVehicleTypeRequest{
			ItemId:        1,
			VehicleTypeId: 2,
		},
	)
	require.NoError(t, err)
	require.Equal(t, int64(1), r6.GetItemId())
	require.Equal(t, int64(2), r6.GetVehicleTypeId())

	_, err = client.GetItemVehicleType(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemVehicleTypeRequest{
			ItemId:        1,
			VehicleTypeId: 1,
		},
	)
	require.ErrorContains(t, err, "NotFound")

	r8, err := client.GetItemVehicleTypes(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&GetItemVehicleTypesRequest{
			ItemId: 1,
		},
	)
	require.NoError(t, err)
	require.Len(t, r8.GetItems(), 1)
}

func TestItemParentLanguages(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	client := NewItemsClient(conn)

	_, err = client.GetItemParentLanguages(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&GetItemParentLanguagesRequest{
			ItemId:   1,
			ParentId: 1,
		},
	)
	require.NoError(t, err)
}

func TestItemLanguages(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	client := NewItemsClient(conn)

	_, err = client.GetItemLanguages(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&GetItemLanguagesRequest{
			ItemId: 1,
		},
	)
	require.NoError(t, err)
}

func TestCatalogueMenuList(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	createItem(t, conn, cnt, &Item{
		Name:            fmt.Sprintf("category-%d", random.Int()),
		IsGroup:         false,
		ItemTypeId:      ItemType_ITEM_TYPE_CATEGORY,
		Catname:         fmt.Sprintf("category-%d", random.Int()),
		Body:            "",
		ProducedExactly: false,
	})

	client := NewItemsClient(conn)

	res, err := client.List(ctx, &ItemsRequest{
		Language: schema.RussianLanguageCode,
		Fields: &ItemFields{
			NameText:         true,
			DescendantsCount: true,
		},
		Limit: 20,
		Options: &ItemListOptions{
			NoParent: true,
			TypeId:   ItemType_ITEM_TYPE_CATEGORY,
		},
	})
	require.NoError(t, err)
	require.NotEmpty(t, res)
	require.NotEmpty(t, res.GetItems())
}

func TestStats(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewItemsClient(conn)

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	r, err := client.GetStats(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
	require.NotEmpty(t, r.GetValues())
}

func TestSetItemParentLanguage(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewItemsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	cases := []struct {
		ParentName           string
		ParentBeginYear      int32
		ParentEndYear        int32
		ParentBeginModelYear int32
		ParentEndModelYear   int32
		ParentSpecID         int32
		ChildName            string
		ChildBeginYear       int32
		ChildEndYear         int32
		ChildBeginModelYear  int32
		ChildEndModelYear    int32
		ChildSpecID          int32
		Result               string
	}{
		{
			"Peugeot %d",
			2000,
			2010,
			0,
			0,
			0,
			"Peugeot %d",
			2000,
			2005,
			0,
			0,
			0,
			"2000–05",
		},
		{
			"Peugeot %d",
			2000,
			2010,
			0,
			0,
			0,
			"Peugeot %d Coupe",
			2000,
			2010,
			0,
			0,
			0,
			"Coupe",
		},
		{
			"Peugeot %d",
			2000,
			2010,
			0,
			0,
			0,
			"Peugeot %d",
			2000,
			2010,
			0,
			0,
			schema.SpecIDWorldwide,
			"Worldwide",
		},
		{
			"Peugeot %d",
			2000,
			2010,
			2001,
			2010,
			0,
			"Peugeot %d",
			2000,
			2010,
			2001,
			2005,
			0,
			"2001–05",
		},
	}

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	for i, testCase := range cases {
		// random is a shared *rand.Rand (not safe for concurrent use); draw on the parent
		// goroutine before the parallel subtest starts.
		randomInt := random.Int()

		t.Run(strconv.Itoa(i), func(t *testing.T) {
			t.Parallel()

			childName := fmt.Sprintf(testCase.ChildName, randomInt)
			parentName := fmt.Sprintf(testCase.ParentName, randomInt)

			itemID := createItem(t, conn, cnt, &Item{ //nolint: contextcheck
				Name:           childName,
				ItemTypeId:     ItemType_ITEM_TYPE_VEHICLE,
				BeginYear:      testCase.ChildBeginYear,
				EndYear:        testCase.ChildEndYear,
				BeginModelYear: testCase.ChildBeginModelYear,
				EndModelYear:   testCase.ChildEndModelYear,
				SpecId:         testCase.ChildSpecID,
			})

			parentID := createItem(t, conn, cnt, &Item{ //nolint: contextcheck
				Name:           parentName,
				IsGroup:        true,
				ItemTypeId:     ItemType_ITEM_TYPE_VEHICLE,
				BeginYear:      testCase.ParentBeginYear,
				EndYear:        testCase.ParentEndYear,
				BeginModelYear: testCase.ParentBeginModelYear,
				EndModelYear:   testCase.ParentEndModelYear,
				SpecId:         testCase.ParentSpecID,
			})

			_, err := client.CreateItemParent(
				metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
				&ItemParent{
					ItemId: itemID, ParentId: parentID, Catname: "child-item", Type: ItemParentType_ITEM_TYPE_DEFAULT,
				},
			)
			require.NoError(t, err)

			_, err = client.SetItemParentLanguage(
				metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
				&ItemParentLanguage{
					ItemId:   itemID,
					ParentId: parentID,
					Language: schema.EnglishLanguageCode,
					Name:     "",
				},
			)
			require.NoError(t, err)

			r3, err := client.GetItemParentLanguages(
				metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
				&GetItemParentLanguagesRequest{
					ItemId:   itemID,
					ParentId: parentID,
				},
			)
			require.NoError(t, err)

			var itemParentLanguageRow *ItemParentLanguage

			for _, row := range r3.GetItems() {
				if row.GetLanguage() == schema.EnglishLanguageCode {
					itemParentLanguageRow = row

					break
				}
			}

			require.NotNil(t, itemParentLanguageRow)
			require.Equal(t, testCase.Result, itemParentLanguageRow.GetName())
		})
	}
}

func TestBrandNewItems(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", random.Int()),
	})

	client := NewItemsClient(conn)

	_, err := client.GetBrandNewItems(ctx, &NewItemsRequest{
		ItemId:   itemID,
		Language: schema.RussianLanguageCode,
	})
	require.NoError(t, err)
}

func TestNewItems(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("category-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_CATEGORY,
		Catname:    fmt.Sprintf("category-%d", random.Int()),
	})

	childID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: childID, ParentId: itemID, Catname: "child-item", Type: ItemParentType_ITEM_TYPE_DEFAULT,
		},
	)
	require.NoError(t, err)

	_, err = client.GetNewItems(ctx, &NewItemsRequest{
		ItemId:   itemID,
		Language: schema.EnglishLanguageCode,
	})
	require.NoError(t, err)
}

func TestInboxPicturesCount(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	ctx := t.Context()
	kc := cnt.Keycloak()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	// create brand
	brandID := createItem(t, conn, cnt, &Item{
		Name:            fmt.Sprintf("brand-%d", random.Int()),
		IsGroup:         true,
		ItemTypeId:      ItemType_ITEM_TYPE_BRAND,
		Catname:         fmt.Sprintf("brand-%d", random.Int()),
		Body:            "",
		ProducedExactly: false,
	})

	// create vehicle
	childID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	client := NewItemsClient(conn)
	picturesClient := NewPicturesClient(conn)

	// login with admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: childID, ParentId: brandID, Catname: "child-item", Type: ItemParentType_ITEM_TYPE_DEFAULT,
		},
	)
	require.NoError(t, err)

	// create inbox pictures
	for i := range 10 {
		pictureID := CreatePicture(t, cnt, "./test/test.jpg", PicturePostForm{
			ItemID: childID,
		}, adminToken.AccessToken)

		if i >= 5 {
			_, err = picturesClient.UpdatePicture(
				metadata.AppendToOutgoingContext(
					ctx,
					authorizationHeader,
					bearerPrefix+adminToken.AccessToken,
				),
				&UpdatePictureRequest{
					Picture: &Picture{
						Id:     pictureID,
						Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
					},
					UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
				},
			)
			require.NoError(t, err)
		}
	}

	_, err = client.List(ctx, &ItemsRequest{
		Fields: &ItemFields{
			InboxPicturesCount: true,
		},
		Options: &ItemListOptions{
			Id: brandID,
		},
		Language: schema.EnglishLanguageCode,
	})
	require.ErrorContains(t, err, "PermissionDenied")

	_, err = client.Item(ctx, &ItemRequest{
		Id: brandID,
		Fields: &ItemFields{
			InboxPicturesCount: true,
		},
		Language: schema.EnglishLanguageCode,
	})
	require.ErrorContains(t, err, "PermissionDenied")

	list, err := client.List(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemsRequest{
			Fields: &ItemFields{
				InboxPicturesCount: true,
			},
			Options: &ItemListOptions{
				Id: brandID,
			},
			Language: schema.EnglishLanguageCode,
		},
	)
	require.NoError(t, err)
	require.Len(t, list.GetItems(), 1)
	require.Equal(t, int32(5), list.GetItems()[0].GetInboxPicturesCount())

	item, err := client.Item(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemRequest{
			Id: brandID,
			Fields: &ItemFields{
				InboxPicturesCount: true,
			},
			Language: schema.EnglishLanguageCode,
		},
	)
	require.NoError(t, err)
	require.Equal(t, int32(5), item.GetInboxPicturesCount())
}

func TestCreateMoveDeleteItemParent(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	parentID1 := createItem(t, conn, cnt, &Item{
		Name:            fmt.Sprintf("category-%d", random.Int()),
		IsGroup:         true,
		ItemTypeId:      ItemType_ITEM_TYPE_CATEGORY,
		Catname:         fmt.Sprintf("category-%d", random.Int()),
		Body:            "",
		ProducedExactly: false,
	})

	parentID2 := createItem(t, conn, cnt, &Item{
		Name:            fmt.Sprintf("category-%d", random.Int()),
		IsGroup:         true,
		ItemTypeId:      ItemType_ITEM_TYPE_CATEGORY,
		Catname:         fmt.Sprintf("category-%d", random.Int()),
		Body:            "",
		ProducedExactly: false,
	})

	childID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	// attach to first parent
	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: childID, ParentId: parentID1, Catname: "child-item", Type: ItemParentType_ITEM_TYPE_DEFAULT,
		},
	)
	require.NoError(t, err)

	// check child in parent 1
	res, err := client.List(ctx, &ItemsRequest{
		Options: &ItemListOptions{
			Parent: &ItemParentListOptions{
				ParentId: parentID1,
			},
		},
		Language: schema.EnglishLanguageCode,
	})
	require.NoError(t, err)
	require.Len(t, res.GetItems(), 1)

	resItem := res.GetItems()[0]
	require.Equal(t, childID, resItem.GetId())

	// move to second parent
	_, err = client.MoveItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&MoveItemParentRequest{
			ItemId: childID, ParentId: parentID1, DestParentId: parentID2,
		},
	)
	require.NoError(t, err)

	// check no childs in parent 1
	res, err = client.List(ctx, &ItemsRequest{
		Options: &ItemListOptions{
			Parent: &ItemParentListOptions{
				ParentId: parentID1,
			},
		},
		Language: schema.EnglishLanguageCode,
	})
	require.NoError(t, err)
	require.Empty(t, res.GetItems())

	// check child in parent 2
	res, err = client.List(ctx, &ItemsRequest{
		Options: &ItemListOptions{
			Parent: &ItemParentListOptions{
				ParentId: parentID2,
			},
		},
		Language: schema.EnglishLanguageCode,
	})
	require.NoError(t, err)
	require.Len(t, res.GetItems(), 1)

	resItem = res.GetItems()[0]
	require.Equal(t, childID, resItem.GetId())

	// delete
	_, err = client.DeleteItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&DeleteItemParentRequest{
			ItemId:   childID,
			ParentId: parentID2,
		},
	)
	require.NoError(t, err)

	// check no childs in parent 2
	res, err = client.List(ctx, &ItemsRequest{
		Options: &ItemListOptions{
			Parent: &ItemParentListOptions{
				ParentId: parentID2,
			},
		},
		Language: schema.EnglishLanguageCode,
	})
	require.NoError(t, err)
	require.Empty(t, res.GetItems())
}

func TestDeleteItemParentNotDeletesSecondChild(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	parentID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("category-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_CATEGORY,
		Catname:    fmt.Sprintf("category-%d", random.Int()),
	})

	childID1 := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	childID2 := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	// attach first to parent
	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: childID1, ParentId: parentID, Catname: "child-item-1", Type: ItemParentType_ITEM_TYPE_DEFAULT,
		},
	)
	require.NoError(t, err)

	// attach second to parent
	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: childID2, ParentId: parentID, Catname: "child-item-2", Type: ItemParentType_ITEM_TYPE_DEFAULT,
		},
	)
	require.NoError(t, err)

	// check childs in parent
	res, err := client.List(ctx, &ItemsRequest{
		Options: &ItemListOptions{
			Parent: &ItemParentListOptions{
				ParentId: parentID,
			},
		},
		Language: schema.EnglishLanguageCode,
	})
	require.NoError(t, err)
	require.Len(t, res.GetItems(), 2)

	// delete
	_, err = client.DeleteItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&DeleteItemParentRequest{
			ItemId:   childID1,
			ParentId: parentID,
		},
	)
	require.NoError(t, err)

	// check child 2 in parent
	res, err = client.List(ctx, &ItemsRequest{
		Options: &ItemListOptions{
			Parent: &ItemParentListOptions{
				ParentId: parentID,
			},
		},
		Language: schema.EnglishLanguageCode,
	})
	require.NoError(t, err)
	require.Len(t, res.GetItems(), 1)

	resItem := res.GetItems()[0]
	require.Equal(t, childID2, resItem.GetId())
}

func TestUpdateItemParent(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	parentName := fmt.Sprintf("Peugeot-%d", randomInt)
	parentID := createItem(t, conn, cnt, &Item{
		Name:            parentName,
		IsGroup:         true,
		ItemTypeId:      ItemType_ITEM_TYPE_CATEGORY,
		Catname:         fmt.Sprintf("peugeot-%d", randomInt),
		Body:            "",
		ProducedExactly: false,
	})

	childName := fmt.Sprintf("Peugeot-%d 407", randomInt)
	childID := createItem(t, conn, cnt, &Item{
		Name:       childName,
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	// attach first to parent
	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: childID, ParentId: parentID,
		},
	)
	require.NoError(t, err)

	rep, err := cnt.ItemsRepository(t.Context())
	require.NoError(t, err)

	row, err := rep.ItemParent(ctx, &query.ItemParentListOptions{
		ItemID:   childID,
		ParentID: parentID,
	}, items.ItemParentFields{})
	require.NoError(t, err)
	require.Equal(t, "407", row.Catname)
	require.Equal(t, schema.ItemParentTypeDefault, row.Type)
	require.False(t, row.ManualCatname)

	// update
	_, err = client.UpdateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: childID, ParentId: parentID, Type: ItemParentType_ITEM_TYPE_DESIGN, Catname: "custom",
		},
	)
	require.NoError(t, err)

	// check child in parent
	res, err := client.List(ctx, &ItemsRequest{
		Options: &ItemListOptions{
			Parent: &ItemParentListOptions{
				ParentId: parentID,
			},
		},
		Language: schema.EnglishLanguageCode,
	})
	require.NoError(t, err)
	require.Len(t, res.GetItems(), 1)

	resItem := res.GetItems()[0]
	require.Equal(t, childID, resItem.GetId())

	// check row
	row, err = rep.ItemParent(ctx, &query.ItemParentListOptions{
		ItemID:   childID,
		ParentID: parentID,
	}, items.ItemParentFields{})
	require.NoError(t, err)
	require.Equal(t, "custom", row.Catname)
	require.Equal(t, schema.ItemParentTypeDesign, row.Type)
	require.True(t, row.ManualCatname)

	// update
	_, err = client.UpdateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: childID, ParentId: parentID, Type: ItemParentType_ITEM_TYPE_TUNING, Catname: "",
		},
	)
	require.NoError(t, err)

	// check row
	row, err = rep.ItemParent(ctx, &query.ItemParentListOptions{
		ItemID:   childID,
		ParentID: parentID,
	}, items.ItemParentFields{})
	require.NoError(t, err)
	require.Equal(t, "407", row.Catname)
	require.Equal(t, schema.ItemParentTypeTuning, row.Type)
	require.False(t, row.ManualCatname)
}

func TestUpdateItemLanguage(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	itemName := fmt.Sprintf("Peugeot-%d", randomInt)
	itemID := createItem(t, conn, cnt, &Item{
		Name:            itemName,
		IsGroup:         true,
		ItemTypeId:      ItemType_ITEM_TYPE_CATEGORY,
		Catname:         fmt.Sprintf("peugeot-%d", randomInt),
		Body:            "",
		ProducedExactly: false,
	})

	_, err = client.UpdateItemLanguage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemLanguage{
			ItemId:   itemID,
			Language: schema.FrenchLanguageCode,
			Name:     itemName,
		},
	)
	require.NoError(t, err)

	res, err := client.GetItemLanguages(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&GetItemLanguagesRequest{
			ItemId: itemID,
		},
	)
	require.NoError(t, err)

	rows := res.GetItems()
	require.Len(t, rows, 1)

	row := rows[0]
	require.Equal(t, itemID, row.GetItemId())
	require.Equal(t, schema.FrenchLanguageCode, row.GetLanguage())
	require.Equal(t, itemName, row.GetName())
	require.Zero(t, row.GetTextId())
	require.Zero(t, row.GetFullTextId())

	// setup text
	_, err = client.UpdateItemLanguage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemLanguage{
			ItemId:   itemID,
			Language: schema.FrenchLanguageCode,
			Name:     itemName,
			Text:     "a text",
		},
	)
	require.NoError(t, err)

	res, err = client.GetItemLanguages(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&GetItemLanguagesRequest{
			ItemId: itemID,
		},
	)
	require.NoError(t, err)

	rows = res.GetItems()
	require.Len(t, rows, 1)

	row = rows[0]
	require.Equal(t, itemID, row.GetItemId())
	require.Equal(t, schema.FrenchLanguageCode, row.GetLanguage())
	require.Equal(t, itemName, row.GetName())
	require.NotZero(t, row.GetTextId())
	require.Equal(t, "a text", row.GetText())
	require.Zero(t, row.GetFullTextId())

	lastTextID := row.GetTextId()

	// update text
	_, err = client.UpdateItemLanguage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemLanguage{
			ItemId:   itemID,
			Language: schema.FrenchLanguageCode,
			Name:     itemName,
			Text:     "a second text",
		},
	)
	require.NoError(t, err)

	res, err = client.GetItemLanguages(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&GetItemLanguagesRequest{
			ItemId: itemID,
		},
	)
	require.NoError(t, err)

	rows = res.GetItems()
	require.Len(t, rows, 1)

	row = rows[0]
	require.Equal(t, itemID, row.GetItemId())
	require.Equal(t, schema.FrenchLanguageCode, row.GetLanguage())
	require.Equal(t, itemName, row.GetName())
	require.Equal(t, lastTextID, row.GetTextId())
	require.Equal(t, "a second text", row.GetText())
	require.Zero(t, row.GetFullTextId())

	// setup full text
	_, err = client.UpdateItemLanguage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemLanguage{
			ItemId:   itemID,
			Language: schema.FrenchLanguageCode,
			Name:     itemName,
			Text:     "a second text",
			FullText: "a full text",
		},
	)
	require.NoError(t, err)

	res, err = client.GetItemLanguages(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&GetItemLanguagesRequest{
			ItemId: itemID,
		},
	)
	require.NoError(t, err)

	rows = res.GetItems()
	require.Len(t, rows, 1)

	row = rows[0]
	require.Equal(t, itemID, row.GetItemId())
	require.Equal(t, schema.FrenchLanguageCode, row.GetLanguage())
	require.Equal(t, itemName, row.GetName())
	require.Equal(t, lastTextID, row.GetTextId())
	require.Equal(t, "a second text", row.GetText())
	require.Equal(t, "a full text", row.GetFullText())
	require.NotZero(t, row.GetFullTextId())

	lastFullTextID := row.GetFullTextId()

	// clear texts
	_, err = client.UpdateItemLanguage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemLanguage{
			ItemId:   itemID,
			Language: schema.FrenchLanguageCode,
			Name:     itemName,
		},
	)
	require.NoError(t, err)

	res, err = client.GetItemLanguages(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&GetItemLanguagesRequest{
			ItemId: itemID,
		},
	)
	require.NoError(t, err)

	rows = res.GetItems()
	require.Len(t, rows, 1)

	row = rows[0]
	require.Equal(t, itemID, row.GetItemId())
	require.Equal(t, schema.FrenchLanguageCode, row.GetLanguage())
	require.Equal(t, itemName, row.GetName())
	require.Equal(t, lastTextID, row.GetTextId())
	require.Empty(t, row.GetText())
	require.Empty(t, row.GetFullText())
	require.Equal(t, lastFullTextID, row.GetFullTextId())
}

func TestSetUserItemSubscription(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	itemName := fmt.Sprintf("Peugeot-%d", randomInt)
	itemID := createItem(t, conn, cnt, &Item{
		Name:            itemName,
		IsGroup:         true,
		ItemTypeId:      ItemType_ITEM_TYPE_CATEGORY,
		Catname:         fmt.Sprintf("peugeot-%d", randomInt),
		Body:            "",
		ProducedExactly: false,
	})

	_, err = client.SetUserItemSubscription(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&SetUserItemSubscriptionRequest{
			ItemId:     itemID,
			Subscribed: true,
		},
	)
	require.NoError(t, err)

	_, err = client.SetUserItemSubscription(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&SetUserItemSubscriptionRequest{
			ItemId:     itemID,
			Subscribed: true,
		},
	)
	require.NoError(t, err)

	_, err = client.SetUserItemSubscription(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&SetUserItemSubscriptionRequest{
			ItemId:     itemID,
			Subscribed: false,
		},
	)
	require.NoError(t, err)
}

func TestSetItemEngine(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	itemName := fmt.Sprintf("Peugeot-%d", randomInt)
	itemID := createItem(t, conn, cnt, &Item{
		Name:       itemName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	engineName := fmt.Sprintf("Peugeot-%d-Engine", randomInt)
	engineID := createItem(t, conn, cnt, &Item{
		Name:       engineName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_ENGINE,
	})

	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            itemID,
				EngineItemId:  engineID,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err := client.Item(ctx, &ItemRequest{
		Id: itemID,
	})
	require.NoError(t, err)
	require.Equal(t, engineID, res.GetEngineItemId())

	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            itemID,
				EngineItemId:  engineID,
				EngineInherit: true,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err = client.Item(ctx, &ItemRequest{
		Id: itemID,
	})
	require.NoError(t, err)
	require.Zero(t, res.GetEngineItemId())

	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            itemID,
				EngineItemId:  engineID,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err = client.Item(ctx, &ItemRequest{
		Id: itemID,
	})
	require.NoError(t, err)
	require.Equal(t, engineID, res.GetEngineItemId())

	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            itemID,
				EngineItemId:  0,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err = client.Item(ctx, &ItemRequest{
		Id: itemID,
	})
	require.NoError(t, err)
	require.Zero(t, res.GetEngineItemId())
}

func TestSetItemEngineInheritance(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	itemName := fmt.Sprintf("Peugeot-%d", randomInt)
	itemID := createItem(t, conn, cnt, &Item{
		Name:       itemName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	engineName := fmt.Sprintf("Peugeot-%d-Engine", randomInt)
	engineID := createItem(t, conn, cnt, &Item{
		Name:       engineName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_ENGINE,
	})

	// test inheritance
	parentItemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("Peugeot-%d-Parent", randomInt),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: itemID, ParentId: parentItemID, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	// set parent engine
	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            parentItemID,
				EngineItemId:  engineID,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err := client.Item(ctx, &ItemRequest{
		Id: itemID,
	})
	require.NoError(t, err)
	require.Zero(t, res.GetEngineItemId())

	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            itemID,
				EngineInherit: true,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err = client.Item(ctx, &ItemRequest{
		Id: itemID,
	})
	require.NoError(t, err)
	require.Equal(t, engineID, res.GetEngineItemId())

	// reset parent engine
	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            parentItemID,
				EngineItemId:  0,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err = client.Item(ctx, &ItemRequest{
		Id: itemID,
	})
	require.NoError(t, err)
	require.Zero(t, res.GetEngineItemId())
}

func TestGetBrands(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	testCases := []struct {
		Name      string
		Catname   string
		Language  string
		Category  BrandsListLine_Category
		Character string
	}{
		{
			Name:      "123",
			Catname:   "numeric",
			Language:  schema.EnglishLanguageCode,
			Category:  BrandsListLine_NUMBER,
			Character: "1",
		},
		{
			Name:      "Бренд",
			Catname:   "cyrillic",
			Language:  schema.EnglishLanguageCode,
			Category:  BrandsListLine_CYRILLIC,
			Character: "Б",
		},
		{
			Name:      "Latin Brand",
			Catname:   "latin",
			Language:  schema.EnglishLanguageCode,
			Category:  BrandsListLine_LATIN,
			Character: "L",
		},
		{
			Name:      "所有",
			Catname:   "han",
			Language:  schema.EnglishLanguageCode,
			Category:  BrandsListLine_LATIN,
			Character: "S",
		},
	}

	for _, testCase := range testCases {
		t.Run(testCase.Character, func(t *testing.T) {
			t.Parallel()

			brandName := fmt.Sprintf("%s-%d", testCase.Name, randomInt)
			brandID := createItem(t, conn, cnt, &Item{ //nolint: contextcheck
				Name:       brandName,
				IsGroup:    true,
				ItemTypeId: ItemType_ITEM_TYPE_BRAND,
				Catname:    fmt.Sprintf("%s-%d", testCase.Catname, randomInt),
			})

			// setup text
			_, err := client.UpdateItemLanguage(
				metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
				&ItemLanguage{
					ItemId:   brandID,
					Language: testCase.Language,
					Name:     brandName,
				},
			)
			require.NoError(t, err)

			idx, err := cnt.Index(t.Context()) //nolint: contextcheck
			require.NoError(t, err)

			err = idx.GenerateBrandsCache(ctx, testCase.Language)
			require.NoError(t, err)

			res, err := client.GetBrands(ctx, &GetBrandsRequest{Language: testCase.Language})
			require.NoError(t, err)

			lineIndex := slices.IndexFunc(res.GetLines(), func(line *BrandsListLine) bool {
				return testCase.Category == line.GetCategory()
			})
			require.GreaterOrEqual(t, lineIndex, 0)

			line := res.GetLines()[lineIndex]

			characterIndex := slices.IndexFunc(
				line.GetCharacters(),
				func(character *BrandsListCharacter) bool {
					return testCase.Character == character.GetCharacter()
				},
			)
			require.GreaterOrEqual(t, characterIndex, 0)

			character := line.GetCharacters()[characterIndex]

			require.True(t,
				slices.ContainsFunc(character.GetItems(), func(item *BrandsListItem) bool {
					return item.GetId() == brandID
				}),
			)
		})
	}
}

func TestGetItemsFirstChars(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	personName := fmt.Sprintf("Zeta-%d", random.Int())

	personID := createItem(t, conn, cnt, &Item{
		Name:       personName,
		ItemTypeId: ItemType_ITEM_TYPE_PERSON,
	})

	// name is only set for English; requests below use Russian, which does not have a
	// direct item_language row and must fall back to English via NameOnly's priority chain.
	_, err = client.UpdateItemLanguage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemLanguage{
			ItemId:   personID,
			Language: schema.EnglishLanguageCode,
			Name:     personName,
		},
	)
	require.NoError(t, err)

	charsRes, err := client.GetItemsFirstChars(ctx, &ItemsFirstCharsRequest{
		Options:  &ItemListOptions{Id: personID},
		Language: schema.RussianLanguageCode,
	})
	require.NoError(t, err)
	require.Contains(t, charsRes.GetLatin(), "Z")

	listRes, err := client.List(ctx, &ItemsRequest{
		Language: schema.RussianLanguageCode,
		Options: &ItemListOptions{
			Id:            personID,
			NameFirstChar: "Z",
		},
	})
	require.NoError(t, err)
	require.Len(t, listRes.GetItems(), 1)
	require.Equal(t, personID, listRes.GetItems()[0].GetId())

	emptyRes, err := client.List(ctx, &ItemsRequest{
		Language: schema.RussianLanguageCode,
		Options: &ItemListOptions{
			Id:            personID,
			NameFirstChar: "A",
		},
	})
	require.NoError(t, err)
	require.Empty(t, emptyRes.GetItems())
}

func TestBrandSections(t *testing.T) {
	t.Parallel()

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	var ids []int64

	err = goquDB.Select(schema.ItemTableIDCol).
		From(schema.ItemTable).
		Where(schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDBrand)).
		ScanValsContext(ctx, &ids)
	require.NoError(t, err)

	for _, id := range ids {
		_, err = client.GetBrandSections(
			ctx,
			&GetBrandSectionsRequest{Language: schema.EnglishLanguageCode, ItemId: id},
		)
		require.NoError(t, err)
	}
}

func TestBrandSections2(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// create brand
	brandName := fmt.Sprintf("Opel-%d", randomInt)
	brandID := createItem(t, conn, cnt, &Item{
		Name:       brandName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("opel-%d", randomInt),
	})

	// setup text
	_, err = client.UpdateItemLanguage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemLanguage{
			ItemId:   brandID,
			Language: schema.EnglishLanguageCode,
			Name:     brandName,
		},
	)
	require.NoError(t, err)

	testCases := []struct {
		ItemTypeID    ItemType
		Name          string
		Catname       string
		SectionNames  []string
		VehicleTypeID []int64
	}{
		{
			ItemTypeID:   ItemType_ITEM_TYPE_VEHICLE,
			Name:         "Calibra",
			Catname:      "calibra",
			SectionNames: []string{""},
		},
		{
			ItemTypeID:    ItemType_ITEM_TYPE_VEHICLE,
			Name:          "Insignia",
			Catname:       "Insignia",
			SectionNames:  []string{""},
			VehicleTypeID: []int64{items.VehicleTypeIDCar},
		},
		{
			ItemTypeID:    ItemType_ITEM_TYPE_VEHICLE,
			Name:          "Motoclub",
			Catname:       "motoclub",
			SectionNames:  []string{"catalogue/section/moto"},
			VehicleTypeID: []int64{items.VehicleTypeIDMoto},
		},
		{
			ItemTypeID:    ItemType_ITEM_TYPE_VEHICLE,
			Name:          "Blitz",
			Catname:       "blitz",
			SectionNames:  []string{"catalogue/section/trucks", "catalogue/section/buses"},
			VehicleTypeID: []int64{items.VehicleTypeIDTruck, items.VehicleTypeIDBus},
		},
		{
			ItemTypeID:   ItemType_ITEM_TYPE_ENGINE,
			Name:         "Engine",
			Catname:      "engine",
			SectionNames: []string{"catalogue/section/engines"},
		},
	}

	for _, testCase := range testCases {
		t.Run(testCase.Name, func(t *testing.T) {
			t.Parallel()

			// create child vehicle
			childName := fmt.Sprintf("Opel-%d %s", randomInt, testCase.Name)
			childID := createItem(t, conn, cnt, &Item{ //nolint: contextcheck
				Name:       childName,
				IsGroup:    true,
				ItemTypeId: testCase.ItemTypeID,
			})

			// setup text
			_, err := client.UpdateItemLanguage(
				metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
				&ItemLanguage{
					ItemId:   childID,
					Language: schema.EnglishLanguageCode,
					Name:     childName,
				},
			)
			require.NoError(t, err)

			for _, vehicleTypeID := range testCase.VehicleTypeID {
				_, err = client.CreateItemVehicleType(
					metadata.AppendToOutgoingContext(
						ctx,
						authorizationHeader,
						bearerPrefix+adminToken,
					),
					&ItemVehicleType{
						ItemId:        childID,
						VehicleTypeId: vehicleTypeID,
					},
				)
				require.NoError(t, err)
			}

			_, err = client.CreateItemParent(
				metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
				&ItemParent{
					ItemId: childID, ParentId: brandID, Type: ItemParentType_ITEM_TYPE_DEFAULT,
				},
			)
			require.NoError(t, err)

			res, err := client.GetBrandSections(
				ctx,
				&GetBrandSectionsRequest{Language: schema.EnglishLanguageCode, ItemId: brandID},
			)
			require.NoError(t, err)

			for _, sectionName := range testCase.SectionNames {
				idx := slices.IndexFunc(res.GetSections(), func(section *BrandSection) bool {
					return section.GetName() == sectionName
				})
				require.GreaterOrEqual(
					t,
					idx,
					0,
					"section `%s` not present in results",
					sectionName,
				)

				section := res.GetSections()[idx]

				require.True(
					t,
					slices.ContainsFunc(section.GetGroups(), func(a *BrandSection) bool {
						return a.GetName() == testCase.Name
					}),
					"item `%s` not found in section `%s`",
					testCase.Name,
					sectionName,
				)
			}
		})
	}
}

func TestTwinsGroupBrands(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	groupName := fmt.Sprintf("Twins-Group-%d", randomInt)
	groupID := createItem(t, conn, cnt, &Item{
		Name:       groupName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_TWINS,
	})

	brandName := fmt.Sprintf("Brand-%d", randomInt)
	brandID := createItem(t, conn, cnt, &Item{
		Name:       brandName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", randomInt),
	})

	vehicleName := fmt.Sprintf("Vehicle-%d", randomInt)
	vehicleID := createItem(t, conn, cnt, &Item{
		Name:       vehicleName,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: vehicleID, ParentId: groupID,
		},
	)
	require.NoError(t, err)

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: vehicleID, ParentId: brandID,
		},
	)
	require.NoError(t, err)

	res, err := client.List(
		ctx,
		&ItemsRequest{
			Options: &ItemListOptions{
				TypeId: ItemType_ITEM_TYPE_BRAND,
				Child: &ItemParentListOptions{
					ItemParentParentByChild: &ItemParentListOptions{
						ParentId: groupID,
					},
				},
			},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, res)

	list := res.GetItems()

	require.Len(t, list, 1)

	var found bool

	for _, item := range list {
		if item.GetId() == brandID {
			found = true

			break
		}
	}

	require.True(t, found)
}

func TestAutocomplete(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	client := NewItemsClient(conn)

	_, err = client.List(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemsRequest{
			Options: &ItemListOptions{
				ParentTypesOf:        ItemType_ITEM_TYPE_BRAND,
				IsGroup:              true,
				ExcludeSelfAndChilds: 3,
				Autocomplete:         "Test",
			},
		},
	)
	require.NoError(t, err)
}

func TestTooBig(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	client := NewItemsClient(conn)

	_, err = client.List(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemsRequest{
			Order: ItemsRequest_CHILDS_COUNT,
			Fields: &ItemFields{
				ChildsCount: true,
			},
			Limit: 100,
		},
	)
	require.NoError(t, err)
}

func TestSuggestionsTo(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	client := NewItemsClient(conn)

	_, err = client.List(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemsRequest{
			Options: &ItemListOptions{
				SuggestionsTo: 1,
			},
			Limit: 1,
		},
	)
	require.NoError(t, err)
}

func TestGetItemParents(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	parentName := fmt.Sprintf("Parent-%d", randomInt)
	parentID := createItem(t, conn, cnt, &Item{
		Name:       parentName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_CATEGORY,
		Catname:    fmt.Sprintf("parent-%d", randomInt),
	})

	child1Name := fmt.Sprintf("Child1-%d", randomInt)
	child1ID := createItem(t, conn, cnt, &Item{
		Name:       child1Name,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	child2Name := fmt.Sprintf("Child2-%d", randomInt)
	child2ID := createItem(t, conn, cnt, &Item{
		Name:       child2Name,
		ItemTypeId: ItemType_ITEM_TYPE_CATEGORY,
		Catname:    fmt.Sprintf("child2-%d", randomInt),
	})

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: child1ID, ParentId: parentID,
		},
	)
	require.NoError(t, err)

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: child2ID, ParentId: parentID,
		},
	)
	require.NoError(t, err)

	res, err := client.GetItemParents(t.Context(), &ItemParentsRequest{
		Options: &ItemParentListOptions{
			ParentId: parentID,
		},
		Order: ItemParentsRequest_AUTO,
	})
	require.NoError(t, err)
	require.Len(t, res.GetItems(), 2)

	for _, row := range res.GetItems() {
		require.Equal(t, parentID, row.GetParentId())
		require.Contains(t, []int64{child1ID, child2ID}, row.GetItemId())
	}

	res, err = client.GetItemParents(t.Context(), &ItemParentsRequest{
		Options: &ItemParentListOptions{
			ParentId: parentID,
		},
		Order: ItemParentsRequest_CATEGORIES_FIRST,
		Limit: 1,
	})
	require.NoError(t, err)
	require.Len(t, res.GetItems(), 1)
	require.Equal(t, int32(2), res.GetPaginator().GetTotalItemCount())
	require.Equal(t, int32(2), res.GetPaginator().GetPageCount())
	require.Equal(t, int32(1), res.GetPaginator().GetCurrent())
	require.Equal(t, parentID, res.GetItems()[0].GetParentId())
	require.Equal(t, child2ID, res.GetItems()[0].GetItemId())
}

// Regression test for a moderator-picker UX bug: ModerPicturesItemMoveComponent's vehicle/engine
// pickers used Order_AUTO (see items/repository.go's ItemParentOrderByAuto), which sorts primarily
// by production year - fine for a public model list, but confusing for a picker where a moderator
// is searching for a specific model by name. Order_NAME wires up the same
// items.ItemParentOrderByName the public GetBrandSections path already uses (sorted by
// item_parent_language name with item.name fallback), which is a plain alphabetical sort.
//
// "Zebra" is given an earlier production year than "Alpha" specifically so the two orders are
// forced to disagree - Order_AUTO must put Zebra first (year ascending) while Order_NAME must put
// Alpha first (alphabetical) - rather than asserting against Order_NAME alone, which could pass
// coincidentally: item_parent rows have no explicit ORDER BY at all under Order_NONE, and a plain
// unordered scan for a given parent_id was observed (empirically, against this suite's Postgres)
// to consistently come back in descending item_id order - so "Alpha" is deliberately created
// *first* here (giving it the lower item_id) specifically so that coincidental descending-id
// fallback would put "Zebra" first instead, correctly failing the alphabetical assertion below if
// Order_NAME's mapping to items.ItemParentOrderByName were ever accidentally removed or broken.
func TestGetItemParentsOrderByName(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	parentID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("OrderByNameParent-%d", randomInt),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("order-by-name-parent-%d", randomInt),
	})

	alphaID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("Alpha-%d", randomInt),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		BeginYear:  2020,
		EndYear:    2020,
	})
	zebraID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("Zebra-%d", randomInt),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		BeginYear:  1990,
		EndYear:    1990,
	})

	kc := cnt.Keycloak()
	cfg := config.LoadConfig(".")
	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	client := NewItemsClient(conn)
	apiCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken.AccessToken)

	_, err = client.CreateItemParent(apiCtx, &ItemParent{ItemId: zebraID, ParentId: parentID})
	require.NoError(t, err)

	_, err = client.CreateItemParent(apiCtx, &ItemParent{ItemId: alphaID, ParentId: parentID})
	require.NoError(t, err)

	nameRes, err := client.GetItemParents(ctx, &ItemParentsRequest{
		Options: &ItemParentListOptions{ParentId: parentID},
		Order:   ItemParentsRequest_NAME,
	})
	require.NoError(t, err)
	require.Len(t, nameRes.GetItems(), 2)
	require.Equal(t, alphaID, nameRes.GetItems()[0].GetItemId(), "alphabetical: Alpha should sort before Zebra")
	require.Equal(t, zebraID, nameRes.GetItems()[1].GetItemId(), "alphabetical: Zebra should sort after Alpha")

	autoRes, err := client.GetItemParents(ctx, &ItemParentsRequest{
		Options: &ItemParentListOptions{ParentId: parentID},
		Order:   ItemParentsRequest_AUTO,
	})
	require.NoError(t, err)
	require.Len(t, autoRes.GetItems(), 2)
	require.Equal(t, zebraID, autoRes.GetItems()[0].GetItemId(), "by year: 1990 Zebra should sort before 2020 Alpha")
	require.Equal(t, alphaID, autoRes.GetItems()[1].GetItemId(), "by year: 2020 Alpha should sort after 1990 Zebra")
}

func TestItemFields(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewItemsClient(conn)
	kc := cnt.Keycloak()
	cfg := config.LoadConfig(".")

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	res, err := client.List(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemsRequest{
			Language: schema.RussianLanguageCode,
			Fields: &ItemFields{
				// AltNames:                   true,
				// AcceptedPicturesCount:      true,
				// AttrZoneId:                 true,
				// Brandicon:                  true,
				// ChildsCounts:               true,
				// ChildsCount:                true,
				// CommentsAttentionsCount:    true,
				// HasChildSpecs:              true,
				// HasSpecs:                   true,
				// InboxPicturesCount:         true,
				// IsCompilesItemOfDay:        true,
				// Location:                   true,
				// Links:                      &ItemLinksRequest{},
				// Logo120:                    true,
				// MostsActive:                true,
				// OtherNames:                 true,
				// PictureItems:               &PictureItemsRequest{},
				// PreviewPictures:            &PreviewPicturesRequest{},
				// PublicRoutes:               true,
				// Route:                      true,
				// SpecsRoute:                 true,
				// NameHtml:                   true,
				// NameDefault:                true,
				// NameOnly:                   true,
				// NameText:                   true,
				// DescendantsCount:           true,
				// Description:                true,
				// HasText:                    true,
				// DescendantTwinsGroupsCount: true,
				// Design:                     true,
				// RelatedGroupPictures:       true,
				// ItemOfDayPictures:          true,
				// ParentsCount:               true,
				// ExactPicturesCount:         true,
				// Logo:                       true,
				// EngineVehiclesCount:        true,
				// ItemLanguageCount:          true,
				// LinksCount:                 true,
				SpecificationsCount: true,
				// Subscription:               true,
			},
			Limit: 200,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, res)
}

func TestItemParentFields(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewItemsClient(conn)
	kc := cnt.Keycloak()
	cfg := config.LoadConfig(".")

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	res, err := client.GetItemParents(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParentsRequest{
			Language: schema.RussianLanguageCode,
			Fields: &ItemParentFields{
				Item: &ItemFields{
					AcceptedPicturesCount:   true,
					ChildsCounts:            true,
					CommentsAttentionsCount: true,
					Description:             true,
					Design:                  true,
					FullText:                true,
					InboxPicturesCount:      true,
					NameDefault:             true,
					NameHtml:                true,
					NameText:                true,
					OtherNames:              true,
					SpecsRoute:              true,
					EngineVehicles:          &ItemsRequest{},
				},
				Parent:          &ItemFields{},
				DuplicateParent: &ItemFields{},
				DuplicateChild:  &ItemFields{},
			},
			Limit: 200,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, res)
}

func TestTwinsGroupPictures(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)
	picturesClient := NewPicturesClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	groupName := fmt.Sprintf("Twins-Group-%d", randomInt)
	groupID := createItem(t, conn, cnt, &Item{
		Name:       groupName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_TWINS,
	})

	vehicleName := fmt.Sprintf("Vehicle-%d", randomInt)
	vehicleID := createItem(t, conn, cnt, &Item{
		Name:       vehicleName,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&ItemParent{
			ItemId: vehicleID, ParentId: groupID,
		},
	)
	require.NoError(t, err)

	pictureID := CreatePicture(t, cnt, "./test/test.jpg", PicturePostForm{
		ItemID: vehicleID,
	}, adminToken)

	_, err = picturesClient.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	res2, err := client.GetItemParents(ctx, &ItemParentsRequest{
		Options: &ItemParentListOptions{
			ParentId: groupID,
			ItemId:   vehicleID,
		},
		Fields: &ItemParentFields{
			ChildDescendantPictures: &PicturesRequest{
				Limit: 1,
				Options: &PictureListOptions{
					Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
				},
				Order: PicturesRequest_ORDER_FRONT_PERSPECTIVES,
			},
		},
	})
	require.NoError(t, err)
	require.NotEmpty(t, res2.GetItems())
	require.NotEmpty(t, res2.GetItems()[0].GetChildDescendantPictures())
	require.NotEmpty(t, res2.GetItems()[0].GetChildDescendantPictures().GetItems()[0])
	require.Equal(
		t,
		pictureID,
		res2.GetItems()[0].GetChildDescendantPictures().GetItems()[0].GetId(),
	)
}

func TestPersonPreviewPictures(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)
	picturesClient := NewPicturesClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	personName := fmt.Sprintf("Person-%d", randomInt)
	personID := createItem(t, conn, cnt, &Item{
		Name:       personName,
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_PERSON,
	})

	pictureID := CreatePicture(t, cnt, "./test/test.jpg", PicturePostForm{
		ItemID: personID,
	}, adminToken)

	_, err = picturesClient.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	res2, err := client.List(ctx, &ItemsRequest{
		Fields: &ItemFields{
			Description: true,
			HasText:     true,
			NameDefault: true,
			NameHtml:    true,
			PreviewPictures: &PreviewPicturesRequest{
				Pictures: &PicturesRequest{
					Options: &PictureListOptions{
						PictureItem: &PictureItemListOptions{
							TypeId: PictureItemType_PICTURE_ITEM_CONTENT,
						},
					},
				},
			},
		},
		Language: schema.EnglishLanguageCode,
		Limit:    10,
		Options: &ItemListOptions{
			Id: personID,
			Descendant: &ItemParentCacheListOptions{
				PictureItemsByItemId: &PictureItemListOptions{
					Pictures: &PictureListOptions{Status: PictureStatus_PICTURE_STATUS_ACCEPTED},
					TypeId:   PictureItemType_PICTURE_ITEM_CONTENT,
				},
			},
			TypeId: ItemType_ITEM_TYPE_PERSON,
		},
		Order: ItemsRequest_NAME,
		Page:  1,
	})
	require.NoError(t, err)
	require.NotEmpty(t, res2.GetItems())
	require.NotEmpty(t, res2.GetItems()[0].GetPreviewPictures())
	require.EqualValues(t, 1, res2.GetItems()[0].GetPreviewPictures().GetTotalPictures())
	require.Equal(
		t,
		pictureID,
		res2.GetItems()[0].GetPreviewPictures().GetPictures()[0].GetPicture().GetId(),
	)
}

func TestCutawayAuthorsWithPreviewPictures(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()

	client := NewItemsClient(conn)
	picturesClient := NewPicturesClient(conn)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	personName := fmt.Sprintf("Person-%d", randomInt)
	personID := createItem(t, conn, cnt, &Item{
		Name:       personName,
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_PERSON,
	})

	vehicleName := fmt.Sprintf("Vehicle-%d", randomInt)
	vehicleID := createItem(t, conn, cnt, &Item{
		Name:       vehicleName,
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := CreatePicture(t, cnt, "./test/test.jpg", PicturePostForm{
		ItemID:        vehicleID,
		PerspectiveID: schema.PerspectiveCutaway,
	}, adminToken)

	_, err = picturesClient.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	_, err = picturesClient.CreatePictureItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CreatePictureItemRequest{
			PictureId: pictureID,
			ItemId:    personID,
			Type:      PictureItemType_PICTURE_ITEM_AUTHOR,
		},
	)
	require.NoError(t, err)

	res2, err := client.List(ctx, &ItemsRequest{
		Fields: &ItemFields{
			Description: true,
			HasText:     true,
			NameDefault: true,
			NameHtml:    true,
			PreviewPictures: &PreviewPicturesRequest{
				OnlyExactlyPictures: true,
				Pictures: &PicturesRequest{
					Options: &PictureListOptions{
						PictureItem: &PictureItemListOptions{
							PictureItemByPictureId: &PictureItemListOptions{
								PerspectiveId: schema.PerspectiveCutaway,
							},
							TypeId: PictureItemType_PICTURE_ITEM_AUTHOR,
						},
						Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
					},
				},
			},
		},
		Language: schema.EnglishLanguageCode,
		Limit:    12,
		Options: &ItemListOptions{
			Id: personID,
			PictureItems: &PictureItemListOptions{
				Pictures: &PictureListOptions{
					PictureItem: &PictureItemListOptions{PerspectiveId: schema.PerspectiveCutaway},
					Status:      PictureStatus_PICTURE_STATUS_ACCEPTED,
				},
				TypeId: PictureItemType_PICTURE_ITEM_AUTHOR,
			},
			TypeId: ItemType_ITEM_TYPE_PERSON,
		},
		Order: ItemsRequest_AGE,
	})
	require.NoError(t, err)
	require.NotEmpty(t, res2.GetItems())
	require.NotEmpty(t, res2.GetItems()[0].GetPreviewPictures())
	require.EqualValues(t, 1, res2.GetItems()[0].GetPreviewPictures().GetTotalPictures())
	require.Equal(
		t,
		pictureID,
		res2.GetItems()[0].GetPreviewPictures().GetPictures()[0].GetPicture().GetId(),
	)
}

func TestItemOfDayPicture(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewItemsClient(conn)
	kc := cnt.Keycloak()
	cfg := config.LoadConfig(".")

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	vehicleName := fmt.Sprintf("Vehicle-%d", randomInt)
	vehicleID := createItem(t, conn, cnt, &Item{
		Name:       vehicleName,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	addPicture(t, cnt, conn, "./test/test.jpg",
		PicturePostForm{ItemID: vehicleID, PerspectiveID: schema.PerspectiveFrontStrict},
		PictureStatus_PICTURE_STATUS_ACCEPTED, adminToken.AccessToken)

	res, err := client.Item(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemRequest{
			Language: schema.RussianLanguageCode,
			Fields:   &ItemFields{ItemOfDayPictures: true},
			Id:       vehicleID,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, res)
	require.NotEmpty(t, res.GetItemOfDayPictures())
	require.NotEmpty(t, res.GetItemOfDayPictures()[0].GetThumb().GetSrc())
}

func TestGetTopSpecsContributions(t *testing.T) {
	t.Parallel()

	client := NewItemsClient(conn)
	attsClient := NewAttrsClient(conn)
	ctx := t.Context()
	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	itemID := createItem(t, conn, cnt, &Item{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "Test",
		Body:       "E31",
		IsGroup:    true,
	})

	_, err = attsClient.SetUserValues(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&AttrSetUserValuesRequest{
			Items: []*AttrUserValue{
				{
					AttributeId: schema.FuelSupplySystemAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						ListValue: []int64{25},
					},
				},
				{
					AttributeId: schema.EngineCylinderDiameter,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_FLOAT,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.WidthAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_INTEGER,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.ABSAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:    AttrAttributeType_BOOLEAN,
						Valid:   true,
						IsEmpty: true,
					},
				},
				{
					AttributeId: schema.EngineTypeAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_TREE,
						Valid:     true,
						IsEmpty:   false,
						ListValue: []int64{105},
					},
				},
				{
					AttributeId: schema.EnginePlacementOrientationAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						IsEmpty:   true,
						ListValue: []int64{},
					},
				},
				{
					AttributeId: schema.DriveUnitAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:      AttrAttributeType_LIST,
						Valid:     true,
						IsEmpty:   true,
						ListValue: []int64{},
					},
				},
				{
					AttributeId: schema.TurningDiameterAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:       AttrAttributeType_FLOAT,
						Valid:      true,
						FloatValue: 7.091,
					},
				},
				{
					AttributeId: schema.LengthAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:     AttrAttributeType_INTEGER,
						Valid:    true,
						IntValue: 6,
					},
				},
				{
					AttributeId: schema.FrontSuspensionTypeAttr,
					ItemId:      itemID,
					Value: &AttrValueValue{
						Type:        AttrAttributeType_STRING,
						Valid:       true,
						StringValue: "suspension test",
					},
				},
			},
		},
	)
	require.NoError(t, err)

	res, err := client.GetTopSpecsContributions(
		ctx,
		&TopSpecsContributionsRequest{Language: schema.RussianLanguageCode},
	)
	require.NoError(t, err)
	require.NotEmpty(t, res.GetItems())
}

func TestVehiclesOnEnginesMerge(t *testing.T) {
	t.Parallel()

	client := NewItemsClient(conn)
	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	itemID := createItem(t, conn, cnt, &Item{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "5 Series",
		Body:       "",
		IsGroup:    true,
	})

	childID := createItem(t, conn, cnt, &Item{
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		Name:       "5 Series",
		Body:       "E31",
		IsGroup:    true,
	})

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemParent{
			ItemId: childID, ParentId: itemID, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	engineName := fmt.Sprintf("Peugeot-%d-Engine", randomInt)
	engineID := createItem(t, conn, cnt, &Item{
		Name:       engineName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_ENGINE,
	})

	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            itemID,
				EngineItemId:  engineID,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err := client.Item(ctx, &ItemRequest{
		Id: itemID,
	})
	require.NoError(t, err)
	require.Equal(t, engineID, res.GetEngineItemId())

	_, err = client.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateItemRequest{
			Item: &Item{
				Id:            childID,
				EngineItemId:  engineID,
				EngineInherit: false,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"engine_item_id", "engine_inherit"}},
		},
	)
	require.NoError(t, err)

	res, err = client.Item(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemRequest{
			Id: engineID,
			Fields: &ItemFields{
				EngineVehicles:      &ItemsRequest{},
				EngineVehiclesCount: true,
			},
			Language: schema.EnglishLanguageCode,
		},
	)
	require.NoError(t, err)
	require.Len(t, res.GetEngineVehicles(), 1)
	require.EqualValues(t, 2, res.GetEngineVehiclesCount())

	for i := range 10 {
		childChildID := createItem(t, conn, cnt, &Item{
			ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
			Name:       "5 Series",
			Body:       fmt.Sprintf("E31-%d", i),
			IsGroup:    false,
		})

		_, err = client.CreateItemParent(
			metadata.AppendToOutgoingContext(
				ctx,
				authorizationHeader,
				bearerPrefix+token.AccessToken,
			),
			&ItemParent{
				ItemId: childChildID, ParentId: childID, Catname: "vehicle1",
			},
		)
		require.NoError(t, err)

		_, err = client.UpdateItem(
			metadata.AppendToOutgoingContext(
				ctx,
				authorizationHeader,
				bearerPrefix+token.AccessToken,
			),
			&UpdateItemRequest{
				Item: &Item{
					Id:            childChildID,
					EngineItemId:  engineID,
					EngineInherit: false,
				},
				UpdateMask: &fieldmaskpb.FieldMask{
					Paths: []string{"engine_item_id", "engine_inherit"},
				},
			},
		)
		require.NoError(t, err)
	}

	res, err = client.Item(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemRequest{
			Id: engineID,
			Fields: &ItemFields{
				EngineVehicles:      &ItemsRequest{},
				EngineVehiclesCount: true,
			},
			Language: schema.EnglishLanguageCode,
		},
	)
	require.NoError(t, err)
	require.Len(t, res.GetEngineVehicles(), 1)
	require.EqualValues(t, 12, res.GetEngineVehiclesCount())
}

func TestBrandSectionLanguageName(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	brandID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", randomInt),
	})

	vehicleID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	_, err = client.CreateItemParent(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParent{
			ItemId: vehicleID, ParentId: brandID, Catname: "vehicle1",
		},
	)
	require.NoError(t, err)

	_, err = client.SetItemParentLanguage(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParentLanguage{
			ItemId:   vehicleID,
			ParentId: brandID,
			Language: schema.RussianLanguageCode,
			Name:     "Azazaza",
		},
	)
	require.NoError(t, err)

	_, err = client.SetItemParentLanguage(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemParentLanguage{
			ItemId:   vehicleID,
			ParentId: brandID,
			Language: schema.EnglishLanguageCode,
			Name:     "Custom name",
		},
	)
	require.NoError(t, err)

	_, err = client.CreateItemVehicleType(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&ItemVehicleType{
			ItemId:        vehicleID,
			VehicleTypeId: 19,
		},
	)
	require.NoError(t, err)

	res, err := client.GetBrandSections(
		ctx,
		&GetBrandSectionsRequest{Language: schema.EnglishLanguageCode, ItemId: brandID},
	)
	require.NoError(t, err)
	require.NotEmpty(t, res.GetSections()[2].GetGroups())
	require.Equal(t, "Custom name", res.GetSections()[2].GetGroups()[0].GetName())
}

func TestAlpha(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	_, err = client.GetAlpha(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestUpdateItemName(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)
	newName := fmt.Sprintf("vehicle-%d-2", randomInt)

	itemID := createItem(t, conn, cnt, &Item{
		Name:       name,
		Body:       "Body",
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, "Body", item.GetBody())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:   itemID,
			Name: newName,
			Body: "IgnoreMe",
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"name"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newName, item.GetName())
	require.Equal(t, "Body", item.GetBody())
}

// Regression test for a latitude/longitude axis swap: goautowp.ru/wheelsage.org once stored
// item_point with X=latitude, Y=longitude (backwards from the PostGIS/WKT standard), while
// pictures.point used the correct X=longitude, Y=latitude convention throughout. Every pair below
// uses distinct, non-zero latitude and longitude values so a swap flips which assertion fails,
// instead of silently passing on symmetric or zero coordinates.
func TestUpdateItemLocationAxisOrder(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	kc := cnt.Keycloak()
	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken.AccessToken)

	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("factory-%d", randomInt),
		ItemTypeId: ItemType_ITEM_TYPE_FACTORY,
	})

	testCases := []struct {
		latitude  float64
		longitude float64
	}{
		{latitude: 10, longitude: 0},
		{latitude: 0, longitude: 10},
		{latitude: -10, longitude: 20},
		// Moscow: a swap would put the point in the ocean south of Ghana, in either direction.
		{latitude: 55.7520, longitude: 37.6175},
	}

	for _, testCase := range testCases {
		_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
			Item: &Item{
				Id: itemID,
				Location: &latlng.LatLng{
					Latitude:  testCase.latitude,
					Longitude: testCase.longitude,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"location"}},
		})
		require.NoError(t, err)

		item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Location: true}})
		require.NoError(t, err)
		require.NotNil(t, item.GetLocation())
		require.InDelta(t, testCase.latitude, item.GetLocation().GetLatitude(), 0.0001)
		require.InDelta(t, testCase.longitude, item.GetLocation().GetLongitude(), 0.0001)
	}

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item:       &Item{Id: itemID},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"location"}},
	})
	require.NoError(t, err)

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Location: true}})
	require.NoError(t, err)
	require.Nil(t, item.GetLocation())
}

func TestUpdateItemBody(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)
	body := "Body"
	newBody := "New Body"

	itemID := createItem(t, conn, cnt, &Item{
		Name:       name,
		Body:       body,
		BeginYear:  2000,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, body, item.GetBody())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:        itemID,
			Body:      newBody,
			BeginYear: 2001,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"body"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newBody, item.GetBody())
	require.EqualValues(t, 2000, item.GetBeginYear())
}

func TestUpdateItemBeginYear(t *testing.T) { //nolint: dupl
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		beginYear    = 2000
		newBeginYear = 2001
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:       name,
		BeginYear:  beginYear,
		EndYear:    2000,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.EqualValues(t, beginYear, item.GetBeginYear())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:        itemID,
			BeginYear: newBeginYear,
			EndYear:   2001,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"begin_year"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.EqualValues(t, newBeginYear, item.GetBeginYear())
	require.EqualValues(t, 2000, item.GetEndYear())
}

func TestUpdateItemEndYear(t *testing.T) { //nolint: dupl
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		endYear    = 2000
		newEndYear = 2001
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:           name,
		EndYear:        endYear,
		BeginModelYear: 2000,
		ItemTypeId:     ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.EqualValues(t, endYear, item.GetEndYear())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:             itemID,
			EndYear:        newEndYear,
			BeginModelYear: 2001,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"end_year"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.EqualValues(t, newEndYear, item.GetEndYear())
	require.EqualValues(t, 2000, item.GetBeginModelYear())
}

func TestUpdateItemBeginModelYear(t *testing.T) { //nolint: dupl
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		beginModelYear    = 2000
		newBeginModelYear = 2001
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:           name,
		BeginModelYear: beginModelYear,
		EndModelYear:   2000,
		ItemTypeId:     ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.EqualValues(t, beginModelYear, item.GetBeginModelYear())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:             itemID,
			BeginModelYear: newBeginModelYear,
			EndModelYear:   2001,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"begin_model_year"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.EqualValues(t, newBeginModelYear, item.GetBeginModelYear())
	require.EqualValues(t, 2000, item.GetEndModelYear())
}

func TestUpdateItemEndModelYear(t *testing.T) { //nolint: dupl
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		endModelYear    = 2000
		newEndModelYear = 2001
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:         name,
		EndModelYear: endModelYear,
		BeginMonth:   1,
		ItemTypeId:   ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.EqualValues(t, endModelYear, item.GetEndModelYear())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:           itemID,
			EndModelYear: newEndModelYear,
			BeginMonth:   3,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"end_model_year"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.EqualValues(t, newEndModelYear, item.GetEndModelYear())
	require.EqualValues(t, 1, item.GetBeginMonth())
}

func TestUpdateItemBeginMonth(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		beginMonth    = time.January
		newBeginMonth = time.May
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:       name,
		BeginMonth: int32(beginMonth),
		EndMonth:   int32(time.February),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.EqualValues(t, beginMonth, item.GetBeginMonth())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:         itemID,
			BeginMonth: int32(newBeginMonth),
			EndMonth:   int32(time.December),
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"begin_month"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.EqualValues(t, newBeginMonth, item.GetBeginMonth())
	require.EqualValues(t, time.February, item.GetEndMonth())
}

func TestUpdateItemEndMonth(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		endMonth    = time.January
		newEndMonth = time.May
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:                   name,
		EndMonth:               int32(endMonth),
		BeginModelYearFraction: "¼",
		ItemTypeId:             ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.EqualValues(t, endMonth, item.GetEndMonth())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:                     itemID,
			EndMonth:               int32(newEndMonth),
			BeginModelYearFraction: "½",
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"end_month"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.EqualValues(t, newEndMonth, item.GetEndMonth())
	require.Equal(t, "¼", item.GetBeginModelYearFraction())
}

func TestUpdateItemBeginModelYearFraction(t *testing.T) { //nolint: dupl
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		beginModelYearFraction    = "¼"
		newBeginModelYearFraction = "½"
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:                   name,
		BeginModelYearFraction: beginModelYearFraction,
		EndModelYearFraction:   "¼",
		ItemTypeId:             ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, beginModelYearFraction, item.GetBeginModelYearFraction())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:                     itemID,
			BeginModelYearFraction: newBeginModelYearFraction,
			EndModelYearFraction:   "½",
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"begin_model_year_fraction"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newBeginModelYearFraction, item.GetBeginModelYearFraction())
	require.Equal(t, "¼", item.GetEndModelYearFraction())
}

func TestUpdateItemEndModelYearFraction(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		endModelYearFraction    = "¼"
		newEndModelYearFraction = "½"
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:                 name,
		EndModelYearFraction: endModelYearFraction,
		SpecId:               schema.SpecIDWorldwide,
		ItemTypeId:           ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, endModelYearFraction, item.GetEndModelYearFraction())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:                   itemID,
			EndModelYearFraction: newEndModelYearFraction,
			SpecId:               schema.SpecIDNorthAmerica,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"end_model_year_fraction"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newEndModelYearFraction, item.GetEndModelYearFraction())
	require.EqualValues(t, schema.SpecIDWorldwide, item.GetSpecId())
}

func TestUpdateItemSpecID(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		specID    = schema.SpecIDWorldwide
		newSpecID = schema.SpecIDNorthAmerica
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:       name,
		SpecId:     specID,
		IsConcept:  true,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.EqualValues(t, specID, item.GetSpecId())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:        itemID,
			SpecId:    newSpecID,
			IsConcept: false,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"spec_id"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.EqualValues(t, newSpecID, item.GetSpecId())
	require.True(t, item.GetIsConcept())
}

func TestUpdateItemIsConcept(t *testing.T) { //nolint: dupl
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		isConcept    = true
		newIsConcept = false
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:        name,
		IsConcept:   isConcept,
		SpecInherit: true,
		ItemTypeId:  ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, isConcept, item.GetIsConcept())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:          itemID,
			IsConcept:   newIsConcept,
			SpecInherit: false,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"is_concept"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newIsConcept, item.GetIsConcept())
	require.True(t, item.GetSpecInherit())
}

func TestUpdateItemSpecInherit(t *testing.T) { //nolint: dupl
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		specInherit    = true
		newSpecInherit = false
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:             name,
		SpecInherit:      specInherit,
		IsConceptInherit: true,
		ItemTypeId:       ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, specInherit, item.GetSpecInherit())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:               itemID,
			SpecInherit:      newSpecInherit,
			IsConceptInherit: false,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"spec_inherit"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newSpecInherit, item.GetSpecInherit())
	require.True(t, item.GetIsConceptInherit())
}

func TestUpdateItemIsConceptInherit(t *testing.T) { //nolint: dupl
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		isConceptInherit    = true
		newIsConceptInherit = false
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:             name,
		IsConceptInherit: isConceptInherit,
		IsGroup:          true,
		ItemTypeId:       ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, isConceptInherit, item.GetIsConceptInherit())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:               itemID,
			IsConceptInherit: newIsConceptInherit,
			IsGroup:          false,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"is_concept_inherit"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newIsConceptInherit, item.GetIsConceptInherit())
	require.True(t, item.GetIsGroup())
}

func TestUpdateItemIsGroup(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		isGroup    = true
		newIsGroup = false
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:       name,
		IsGroup:    isGroup,
		Produced:   &wrapperspb.Int32Value{Value: 10},
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, isGroup, item.GetIsGroup())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:       itemID,
			IsGroup:  newIsGroup,
			Produced: &wrapperspb.Int32Value{Value: 30},
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"is_group"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newIsGroup, item.GetIsGroup())
	require.EqualValues(t, 10, item.GetProduced().GetValue())
}

func TestUpdateItemProduced(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		produced    = 10
		newProduced = 30
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:            name,
		Produced:        &wrapperspb.Int32Value{Value: produced},
		ProducedExactly: true,
		ItemTypeId:      ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.EqualValues(t, produced, item.GetProduced().GetValue())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:              itemID,
			Produced:        &wrapperspb.Int32Value{Value: newProduced},
			ProducedExactly: false,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"produced"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.EqualValues(t, newProduced, item.GetProduced().GetValue())
	require.True(t, item.GetProducedExactly())
}

func TestUpdateItemProducedExactly(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		producedExactly    = true
		newProducedExactly = false
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:            name,
		ProducedExactly: producedExactly,
		Today:           &wrapperspb.BoolValue{Value: true},
		ItemTypeId:      ItemType_ITEM_TYPE_VEHICLE,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, producedExactly, item.GetProducedExactly())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:              itemID,
			ProducedExactly: newProducedExactly,
			Today:           &wrapperspb.BoolValue{Value: false},
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"produced_exactly"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newProducedExactly, item.GetProducedExactly())
	require.True(t, item.GetToday().GetValue())
}

func TestUpdateItemToday(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)
	catname := fmt.Sprintf("vehicle-%d", randomInt)

	const (
		today    = true
		newToday = false
	)

	itemID := createItem(t, conn, cnt, &Item{
		Name:       name,
		Today:      &wrapperspb.BoolValue{Value: today},
		Catname:    catname,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, today, item.GetToday().GetValue())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:      itemID,
			Today:   &wrapperspb.BoolValue{Value: newToday},
			Catname: fmt.Sprintf("vehicle-%d-new", randomInt),
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"today"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newToday, item.GetToday().GetValue())
	require.Equal(t, catname, item.GetCatname())
}

func TestUpdateItemCatname(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)
	catname := fmt.Sprintf("vehicle-%d", randomInt)
	newCatname := fmt.Sprintf("vehicle-%d-new", randomInt)

	itemID := createItem(t, conn, cnt, &Item{
		Name:       name,
		Catname:    catname,
		FullName:   "FullName",
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, catname, item.GetCatname())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:       itemID,
			Catname:  newCatname,
			FullName: "FullName New",
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"catname"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, newCatname, item.GetCatname())
	require.Equal(t, "FullName", item.GetFullName())
}

func TestUpdateItemFullname(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	name := fmt.Sprintf("vehicle-%d", randomInt)

	itemID := createItem(t, conn, cnt, &Item{
		FullName:   "FullName",
		Catname:    fmt.Sprintf("vehicle-%d", randomInt),
		Name:       name,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
	})

	item, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, name, item.GetName())
	require.Equal(t, "FullName", item.GetFullName())

	_, err = client.UpdateItem(apiCtx, &UpdateItemRequest{
		Item: &Item{
			Id:       itemID,
			FullName: "FullName New",
			Name:     "New name",
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"full_name"}},
	})
	require.NoError(t, err)

	item, err = client.Item(apiCtx, &ItemRequest{Id: itemID, Fields: &ItemFields{Meta: true}})
	require.NoError(t, err)
	require.Equal(t, "FullName New", item.GetFullName())
	require.Equal(t, name, item.GetName())
}

func TestUpdateBeginOrderCache(t *testing.T) {
	t.Parallel()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	createItem(t, conn, cnt, &Item{
		Name:                   fmt.Sprintf("engine-%d", randomInt),
		ItemTypeId:             ItemType_ITEM_TYPE_ENGINE,
		BeginModelYear:         1999,
		BeginModelYearFraction: "¼",
		EndModelYear:           2000,
		EndModelYearFraction:   "¼",
	})
}

func TestGetTree(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("person-%d", randomInt),
		ItemTypeId: ItemType_ITEM_TYPE_PERSON,
		BeginYear:  1999,
		EndYear:    2000,
	})

	res, err := client.GetTree(apiCtx, &GetTreeRequest{Id: itemID, Language: schema.EnglishLanguageCode})
	require.NoError(t, err)
	require.NotEmpty(t, res)
}

func TestCreatedBrandIsGroup(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand-%d", randomInt),
		Catname:    fmt.Sprintf("brand-%d", randomInt),
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
	})

	res, err := client.Item(apiCtx, &ItemRequest{Id: itemID, Language: schema.EnglishLanguageCode})
	require.NoError(t, err)
	require.NotEmpty(t, res)
	require.True(t, res.GetIsGroup())
}

func TestGetBrandVehicleTypes(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	grpcClient := NewItemsClient(conn)

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	_, err = grpcClient.GetBrandVehicleTypes(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&GetBrandVehicleTypesRequest{
			BrandId: 1,
		},
	)
	require.NoError(t, err)
}

func TestGetSpecs(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	grpcClient := NewItemsClient(conn)

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	_, err = grpcClient.GetSpecs(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestGetVehicleTypes(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	ctx := t.Context()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	srv, err := cnt.ItemsGRPCServer(t.Context())
	require.NoError(t, err)

	result, err := srv.GetVehicleTypes(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
	require.NotEmpty(t, result.GetItems())
}

func TestGetVehicleTypesInaccessibleAnonymously(t *testing.T) {
	t.Parallel()

	srv, err := cnt.ItemsGRPCServer(t.Context())
	require.NoError(t, err)

	_, err = srv.GetVehicleTypes(t.Context(), &emptypb.Empty{})
	require.Error(t, err)
}

func TestGetVehicleTypesInaccessibleWithEmptyToken(t *testing.T) {
	t.Parallel()

	srv, err := cnt.ItemsGRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{authorizationHeader: bearerPrefix}),
	)

	_, err = srv.GetVehicleTypes(ctx, &emptypb.Empty{})
	require.Error(t, err)
}

func TestGetVehicleTypesInaccessibleWithInvalidToken(t *testing.T) {
	t.Parallel()

	srv, err := cnt.ItemsGRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{authorizationHeader: bearerPrefix + "abc"}),
	)

	_, err = srv.GetVehicleTypes(ctx, &emptypb.Empty{})
	require.Error(t, err)
}

func TestGetVehicleTypesInaccessibleWithWronglySignedToken(t *testing.T) {
	t.Parallel()

	srv, err := cnt.ItemsGRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{
			authorizationHeader: bearerPrefix + tokenWithInvalidSignature,
		}),
	)

	_, err = srv.GetVehicleTypes(ctx, &emptypb.Empty{})
	require.Error(t, err)
}

func TestGetVehicleTypesInaccessibleWithoutModeratePrivilege(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	ctx := t.Context()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, testUsername, testPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	srv, err := cnt.ItemsGRPCServer(t.Context())
	require.NoError(t, err)

	_, err = srv.GetVehicleTypes(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&emptypb.Empty{},
	)
	require.Error(t, err)
}

func TestItemParentLanguageAutoUpdates(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	client := NewItemsClient(conn)
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	// admin
	kc := cnt.Keycloak()
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	apiCtx := metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+adminToken.AccessToken,
	)

	ruName := fmt.Sprintf("Лада %d Калина", randomInt)
	ruBrandName := fmt.Sprintf("Лада %d", randomInt)

	testCases := []struct {
		Name               string
		Language           string
		NewName            string
		FullName           string
		NewFullName        string
		BrandName          string
		CheckNewRouterLink bool
	}{
		{
			Language:           schema.EnglishLanguageCode,
			BrandName:          fmt.Sprintf("Lada %d", randomInt),
			FullName:           fmt.Sprintf("Lada %d Kalina", randomInt),
			Name:               "Kalina",
			NewFullName:        fmt.Sprintf("Lada %d Granta", randomInt),
			NewName:            "Granta",
			CheckNewRouterLink: true,
		},
		{
			Language:           schema.RussianLanguageCode,
			BrandName:          ruBrandName,
			FullName:           ruName,
			Name:               "Калина",
			NewFullName:        fmt.Sprintf("Лада %d Гранта", randomInt),
			NewName:            "Гранта",
			CheckNewRouterLink: false,
		},
	}

	for _, testCase := range testCases {
		t.Run(testCase.Name, func(t *testing.T) {
			t.Parallel()

			// Each language case gets its own brand/vehicle pair: the auto-derived
			// item_parent catname is language-neutral (taken from the English name),
			// so sharing one vehicle across parallel cases lets the "Granta" rename in
			// one case race the "Kalina" assertions in the other.
			brandCatname := fmt.Sprintf("lada-%d-%s", randomInt, testCase.Language)

			itemID := createItem(t, conn, cnt, &Item{ //nolint: contextcheck
				Name:       ruName,
				ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
			})

			brandID := createItem(t, conn, cnt, &Item{ //nolint: contextcheck
				Name:       ruBrandName,
				Catname:    brandCatname,
				ItemTypeId: ItemType_ITEM_TYPE_BRAND,
				IsGroup:    true,
			})

			_, err := client.CreateItemParent(
				apiCtx,
				&ItemParent{ItemId: itemID, ParentId: brandID},
			)
			require.NoError(t, err)

			_, err = client.UpdateItemLanguage(
				apiCtx,
				&ItemLanguage{ItemId: brandID, Language: testCase.Language, Name: testCase.BrandName},
			)
			require.NoError(t, err)

			_, err = client.UpdateItemLanguage(
				apiCtx,
				&ItemLanguage{ItemId: itemID, Language: testCase.Language, Name: testCase.FullName},
			)
			require.NoError(t, err)

			sections, err := client.GetBrandSections(ctx, &GetBrandSectionsRequest{
				Language: testCase.Language, ItemId: brandID,
			})
			require.NoError(t, err)
			require.NotEmpty(t, sections.GetSections())

			for _, section := range sections.GetSections() {
				for _, group := range section.GetGroups() {
					require.Equal(t, testCase.Name, group.GetName())
					require.Equal(t, []string{"/", brandCatname, "kalina"}, group.GetRouterLink())
				}
			}

			_, err = client.UpdateItemLanguage(
				apiCtx,
				&ItemLanguage{
					ItemId:   itemID,
					Language: testCase.Language,
					Name:     testCase.NewFullName,
				},
			)
			require.NoError(t, err)

			sections, err = client.GetBrandSections(ctx, &GetBrandSectionsRequest{
				Language: testCase.Language, ItemId: brandID,
			})
			require.NoError(t, err)
			require.NotEmpty(t, sections.GetSections())

			for _, section := range sections.GetSections() {
				for _, group := range section.GetGroups() {
					require.Equal(t, testCase.NewName, group.GetName())

					if testCase.CheckNewRouterLink {
						require.Equal(t, []string{"/", brandCatname, "granta"}, group.GetRouterLink())
					}
				}
			}
		})
	}
}
