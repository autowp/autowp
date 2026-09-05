package goautowp

import (
	"database/sql"
	"fmt"
	"math/rand"
	"net"
	"net/http"
	"strconv"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/jackc/pgtype"
	"github.com/stretchr/testify/require"
	"google.golang.org/genproto/googleapis/type/date"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
	"google.golang.org/protobuf/types/known/wrapperspb"
)

func TestView(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewPicturesClient(conn)
	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := CreatePicture(
		t,
		cnt,
		"./test/test.jpg",
		PicturePostForm{ItemID: itemID},
		token.AccessToken,
	)

	_, err = client.View(ctx, &PicturesViewRequest{PictureId: pictureID})
	require.NoError(t, err)
}

func TestVote(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewPicturesClient(conn)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := CreatePicture(
		t,
		cnt,
		"./test/test.jpg",
		PicturePostForm{ItemID: itemID},
		token.AccessToken,
	)

	_, err = client.Vote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PicturesVoteRequest{PictureId: pictureID, Value: 1},
	)
	require.NoError(t, err)
}

func TestModerVoteTemplate(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewPicturesClient(conn)

	template, err := client.CreateModerVoteTemplate(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ModerVoteTemplate{Message: "test", Vote: 1},
	)
	require.NoError(t, err)

	_, err = client.GetModerVoteTemplates(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = client.DeleteModerVoteTemplate(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&DeleteModerVoteTemplateRequest{Id: template.GetId()},
	)
	require.NoError(t, err)
}

func TestModerVote(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: 1, Save: true},
	)
	require.NoError(t, err)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: 1, Save: true},
	)
	require.NoError(t, err)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: -1, Save: true},
	)
	require.NoError(t, err)

	_, err = client.DeleteModerVote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&DeleteModerVoteRequest{PictureId: pictureID},
	)
	require.NoError(t, err)

	secondUserID, _ := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	var picStatus schema.PictureStatus

	// test unaccepting
	_, err = goquDB.Update(schema.PictureTable).Set(goqu.Record{
		schema.PictureTableStatusColName:             schema.PictureStatusAccepted,
		schema.PictureTableChangeStatusUserIDColName: secondUserID,
	}).Where(schema.PictureTableIDCol.Eq(pictureID)).Executor().ExecContext(ctx)
	require.NoError(t, err)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: -1, Save: false},
	)
	require.NoError(t, err)

	success, err := goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusInbox, picStatus)

	// test restore from removing
	_, err = goquDB.Update(schema.PictureTable).Set(goqu.Record{
		schema.PictureTableStatusColName:             schema.PictureStatusRemoving,
		schema.PictureTableChangeStatusUserIDColName: secondUserID,
	}).Where(schema.PictureTableIDCol.Eq(pictureID)).Executor().ExecContext(ctx)
	require.NoError(t, err)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: 1, Save: false},
	)
	require.NoError(t, err)

	success, err = goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusInbox, picStatus)

	_, err = client.DeleteModerVote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&DeleteModerVoteRequest{PictureId: pictureID},
	)
	require.NoError(t, err)
}

func TestUserSummary(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewPicturesClient(conn)

	_, err = client.GetUserSummary(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestFlopNormalizeAndRepair(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	_, err = goquDB.Update(schema.PictureTable).Set(goqu.Record{
		schema.PictureTableStatusColName: schema.PictureStatusInbox,
	}).Where(schema.PictureTableIDCol.Eq(pictureID)).Executor().ExecContext(ctx)
	require.NoError(t, err)

	client := NewPicturesClient(conn)

	_, err = client.Flop(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	_, err = client.Normalize(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	_, err = client.Repair(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)
}

func TestDeleteSimilar(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewPicturesClient(conn)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	picture1ID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)
	picture2ID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	_, err = client.DeleteSimilar(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&DeleteSimilarRequest{Id: picture1ID, SimilarPictureId: picture2ID},
	)
	require.NoError(t, err)
}

func TestPictureItemAreaAndPerspective(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.UpdatePictureItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureItemRequest{
			PictureItem: &PictureItem{
				PictureId:  pictureID,
				ItemId:     itemID,
				Type:       PictureItemType_PICTURE_ITEM_CONTENT,
				CropLeft:   0,
				CropTop:    0,
				CropWidth:  10,
				CropHeight: 10,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdatePictureItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureItemRequest{
			PictureItem: &PictureItem{
				PictureId:  pictureID,
				ItemId:     itemID,
				Type:       PictureItemType_PICTURE_ITEM_CONTENT,
				CropLeft:   0,
				CropTop:    0,
				CropWidth:  0,
				CropHeight: 10,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdatePictureItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureItemRequest{
			PictureItem: &PictureItem{
				PictureId:     pictureID,
				ItemId:        itemID,
				Type:          PictureItemType_PICTURE_ITEM_CONTENT,
				PerspectiveId: 1,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"perspective_id"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdatePictureItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureItemRequest{
			PictureItem: &PictureItem{
				PictureId:     pictureID,
				ItemId:        itemID,
				Type:          PictureItemType_PICTURE_ITEM_CONTENT,
				PerspectiveId: 0,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"perspective_id"}},
		},
	)
	require.NoError(t, err)
}

func TestPictureItemSetPictureItemItemID(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	itemID1 := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-1-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	itemID2 := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-2-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID1},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.SetPictureItemItemID(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&SetPictureItemItemIDRequest{
			PictureId: pictureID,
			ItemId:    itemID1,
			Type:      PictureItemType_PICTURE_ITEM_CONTENT,
			NewItemId: itemID2,
		},
	)
	require.NoError(t, err)

	_, err = client.DeletePictureItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&DeletePictureItemRequest{
			PictureId: pictureID,
			ItemId:    itemID1,
			Type:      PictureItemType_PICTURE_ITEM_CONTENT,
		},
	)
	require.Error(t, err)

	_, err = client.DeletePictureItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&DeletePictureItemRequest{
			PictureId: pictureID,
			ItemId:    itemID2,
			Type:      PictureItemType_PICTURE_ITEM_CONTENT,
		},
	)
	require.NoError(t, err)
}

func TestCreatePictureItemAuthorByOwner(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()

	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	testerToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, testUsername, testPassword)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	personID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("person-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_PERSON,
	})
	vehicleID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	// Picture owned by the tester, still in the inbox.
	ownPictureID := CreatePicture(
		t, cnt, "./test/test.jpg", PicturePostForm{ItemID: vehicleID}, testerToken.AccessToken,
	)
	// Picture owned by the admin.
	otherPictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: vehicleID},
		PictureStatus_PICTURE_STATUS_INBOX, adminToken.AccessToken)

	client := NewPicturesClient(conn)
	testerCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+testerToken.AccessToken)

	// The owner may credit an author on their own inbox picture.
	_, err = client.CreatePictureItem(testerCtx, &CreatePictureItemRequest{
		PictureId: ownPictureID,
		ItemId:    personID,
		Type:      PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.NoError(t, err)

	// ... and remove it again.
	_, err = client.DeletePictureItem(testerCtx, &DeletePictureItemRequest{
		PictureId: ownPictureID,
		ItemId:    personID,
		Type:      PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.NoError(t, err)

	// Not a CONTENT link, though - that stays moderator-only.
	_, err = client.CreatePictureItem(testerCtx, &CreatePictureItemRequest{
		PictureId: ownPictureID,
		ItemId:    vehicleID,
		Type:      PictureItemType_PICTURE_ITEM_CONTENT,
	})
	require.ErrorContains(t, err, "PermissionDenied")

	// ... and not an author link on someone else's picture.
	_, err = client.CreatePictureItem(testerCtx, &CreatePictureItemRequest{
		PictureId: otherPictureID,
		ItemId:    personID,
		Type:      PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.ErrorContains(t, err, "PermissionDenied")
}

func TestPictureCrop(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Crop: &PictureCrop{
					Left:   0,
					Top:    0,
					Width:  0,
					Height: 10,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Crop: &PictureCrop{
					Left:   0,
					Top:    0,
					Width:  10,
					Height: 10,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	res, err := client.GetPicture(ctx, &PicturesRequest{
		Options: &PictureListOptions{Id: pictureID},
		Fields:  &PictureFields{ImageGallery: true},
	})
	require.NoError(t, err)
	require.NotEmpty(t, res.GetImageGallery())
	require.EqualValues(t, 10, res.GetImageGallery().GetWidth())
	require.EqualValues(t, 10, res.GetImageGallery().GetHeight())
}

func TestPictureCropByOneAxis(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	imageStorage, err := cnt.ImageStorage(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Crop: &PictureCrop{
					Left:   0,
					Top:    0,
					Width:  200,
					Height: 130,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	res, err := client.GetPicture(ctx, &PicturesRequest{
		Options: &PictureListOptions{Id: pictureID},
		Fields:  &PictureFields{Image: true},
	})
	require.NoError(t, err)

	crop, err := imageStorage.ImageCrop(ctx, int(res.GetImage().GetId()))
	require.NoError(t, err)
	require.Equal(t, 0, crop.Left)
	require.Equal(t, 0, crop.Top)
	require.Equal(t, 200, crop.Width)
	require.Equal(t, 130, crop.Height)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Crop: &PictureCrop{
					Left:   0,
					Top:    0,
					Width:  180,
					Height: 143,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	crop, err = imageStorage.ImageCrop(ctx, int(res.GetImage().GetId()))
	require.NoError(t, err)
	require.Equal(t, 0, crop.Left)
	require.Equal(t, 0, crop.Top)
	require.Equal(t, 180, crop.Width)
	require.Equal(t, 143, crop.Height)
}

func TestInvalidPictureCrop(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	imageStorage, err := cnt.ImageStorage(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Crop: &PictureCrop{
					Left:   0,
					Top:    0,
					Width:  1002,
					Height: 140,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	res, err := client.GetPicture(ctx, &PicturesRequest{
		Options: &PictureListOptions{Id: pictureID},
		Fields:  &PictureFields{Image: true},
	})
	require.NoError(t, err)

	crop, err := imageStorage.ImageCrop(ctx, int(res.GetImage().GetId()))
	require.NoError(t, err)
	require.Equal(t, 0, crop.Left)
	require.Equal(t, 0, crop.Top)
	require.Equal(t, 1000, crop.Width)
	require.Equal(t, 140, crop.Height)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Crop: &PictureCrop{
					Left:   0,
					Top:    0,
					Width:  190,
					Height: 565,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	crop, err = imageStorage.ImageCrop(ctx, int(res.GetImage().GetId()))
	require.NoError(t, err)
	require.Equal(t, 0, crop.Left)
	require.Equal(t, 0, crop.Top)
	require.Equal(t, 190, crop.Width)
	require.Equal(t, 563, crop.Height)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Crop: &PictureCrop{
					Left:   30,
					Top:    0,
					Width:  990,
					Height: 143,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"crop"}},
		},
	)
	require.NoError(t, err)

	crop, err = imageStorage.ImageCrop(ctx, int(res.GetImage().GetId()))
	require.NoError(t, err)
	require.Equal(t, 30, crop.Left)
	require.Equal(t, 0, crop.Top)
	require.Equal(t, 970, crop.Width)
	require.Equal(t, 143, crop.Height)
}

func TestClearReplacePicture(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	replacePictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)
	pictureID := addPicture(
		t,
		cnt,
		conn,
		"./test/test.jpg",
		PicturePostForm{ReplacePictureID: replacePictureID},
		PictureStatus_PICTURE_STATUS_INBOX,
		token.AccessToken,
	)

	client := NewPicturesClient(conn)

	_, err = client.ClearReplacePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	var value sql.NullInt64

	success, err := goquDB.Select(schema.PictureTableReplacePictureIDCol).
		From(schema.PictureTable).
		Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &value)
	require.NoError(t, err)
	require.True(t, success)
	require.False(t, value.Valid)
}

func TestUpdatePicturePoint(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Point: &latlng.LatLng{
					Latitude:  0,
					Longitude: 0,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"point"}},
		},
	)
	require.NoError(t, err)

	pic, err := client.GetPicture(
		ctx,
		&PicturesRequest{Options: &PictureListOptions{Id: pictureID}},
	)
	require.NoError(t, err)
	require.Nil(t, pic.GetPoint())

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Point: &latlng.LatLng{
					Latitude:  10,
					Longitude: 0,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"point"}},
		},
	)
	require.NoError(t, err)

	pic, err = client.GetPicture(ctx, &PicturesRequest{Options: &PictureListOptions{Id: pictureID}})
	require.NoError(t, err)
	require.NotNil(t, pic.GetPoint())
	require.InDelta(t, float64(10), pic.GetPoint().GetLatitude(), 0.001)
	require.InDelta(t, float64(0), pic.GetPoint().GetLongitude(), 0.001)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Point: &latlng.LatLng{
					Latitude:  0,
					Longitude: 10,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"point"}},
		},
	)
	require.NoError(t, err)

	pic, err = client.GetPicture(ctx, &PicturesRequest{Options: &PictureListOptions{Id: pictureID}})
	require.NoError(t, err)
	require.NotNil(t, pic.GetPoint())
	require.InDelta(t, float64(0), pic.GetPoint().GetLatitude(), 0.001)
	require.InDelta(t, float64(10), pic.GetPoint().GetLongitude(), 0.001)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
				Point: &latlng.LatLng{
					Latitude:  -10,
					Longitude: 10,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"point"}},
		},
	)
	require.NoError(t, err)

	pic, err = client.GetPicture(ctx, &PicturesRequest{Options: &PictureListOptions{Id: pictureID}})
	require.NoError(t, err)
	require.NotNil(t, pic.GetPoint())
	require.InDelta(t, float64(-10), pic.GetPoint().GetLatitude(), 0.001)
	require.InDelta(t, float64(10), pic.GetPoint().GetLongitude(), 0.001)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id: pictureID,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"point"}},
		},
	)
	require.NoError(t, err)

	pic, err = client.GetPicture(ctx, &PicturesRequest{Options: &PictureListOptions{Id: pictureID}})
	require.NoError(t, err)
	require.Nil(t, pic.GetPoint())
}

func TestUpdatePicture(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:          pictureID,
				SpecialName: "Foo",
				TakenDate: &date.Date{
					Year:  2020,
					Month: 2,
					Day:   1,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{
				Paths: []string{"special_name", "taken_date"},
			},
		},
	)
	require.NoError(t, err)

	var pic schema.PictureRow

	success, err := goquDB.Select(
		schema.PictureTableTakenYearColName,
		schema.PictureTableTakenMonthColName,
		schema.PictureTableTakenDayColName,
	).
		From(schema.PictureTable).
		Where(schema.PictureTableIDCol.Eq(pictureID)).ScanStructContext(ctx, &pic)
	require.NoError(t, err)
	require.True(t, success)

	require.Equal(t, int16(2020), pic.TakenYear.Int16)
	require.True(t, pic.TakenYear.Valid)
	require.Equal(t, byte(2), pic.TakenMonth.Byte)
	require.True(t, pic.TakenMonth.Valid)
	require.Equal(t, byte(1), pic.TakenDay.Byte)
	require.True(t, pic.TakenDay.Valid)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:          pictureID,
				SpecialName: "Foo",
				TakenDate: &date.Date{
					Year:  2020,
					Month: 2,
				},
			},
			UpdateMask: &fieldmaskpb.FieldMask{
				Paths: []string{"special_name", "taken_date"},
			},
		},
	)
	require.NoError(t, err)

	success, err = goquDB.Select(
		schema.PictureTableTakenYearColName,
		schema.PictureTableTakenMonthColName,
		schema.PictureTableTakenDayColName,
	).
		From(schema.PictureTable).
		Where(schema.PictureTableIDCol.Eq(pictureID)).ScanStructContext(ctx, &pic)
	require.NoError(t, err)
	require.True(t, success)

	require.Equal(t, int16(2020), pic.TakenYear.Int16)
	require.True(t, pic.TakenYear.Valid)
	require.Equal(t, byte(2), pic.TakenMonth.Byte)
	require.True(t, pic.TakenMonth.Valid)
	require.False(t, pic.TakenDay.Valid)

	// Updating only the name must not touch taken_date when it is absent from the mask.
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:          pictureID,
				SpecialName: "Bar",
			},
			UpdateMask: &fieldmaskpb.FieldMask{
				Paths: []string{"special_name"},
			},
		},
	)
	require.NoError(t, err)

	success, err = goquDB.Select(
		schema.PictureTableNameColName,
		schema.PictureTableTakenYearColName,
		schema.PictureTableTakenMonthColName,
	).
		From(schema.PictureTable).
		Where(schema.PictureTableIDCol.Eq(pictureID)).ScanStructContext(ctx, &pic)
	require.NoError(t, err)
	require.True(t, success)

	require.Equal(t, "Bar", pic.Name.String)
	require.Equal(t, int16(2020), pic.TakenYear.Int16)
	require.True(t, pic.TakenYear.Valid)
	require.Equal(t, byte(2), pic.TakenMonth.Byte)
	require.True(t, pic.TakenMonth.Valid)
}

func TestUpdatePictureCopyrights(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	textStorageRepository := textstorage.New(goquDB)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	pictureID2 := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:         pictureID,
				Copyrights: "First",
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"copyrights"}},
		},
	)
	require.NoError(t, err)

	pic, err := client.GetPicture(
		ctx,
		&PicturesRequest{Options: &PictureListOptions{Id: pictureID}},
	)
	require.NoError(t, err)
	require.NotZero(t, pic.GetCopyrightsTextId())
	require.NotEmpty(t, pic.GetCopyrightsTextId())

	text, err := textStorageRepository.Text(ctx, pic.GetCopyrightsTextId())
	require.NoError(t, err)
	require.Equal(t, "First", text)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:         pictureID,
				Copyrights: "Second",
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"copyrights"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:         pictureID2,
				Copyrights: "Third",
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"copyrights"}},
		},
	)
	require.NoError(t, err)

	text, err = textStorageRepository.Text(ctx, pic.GetCopyrightsTextId())
	require.NoError(t, err)
	require.Equal(t, "Second", text)

	pic2, err := client.GetPicture(
		ctx,
		&PicturesRequest{Options: &PictureListOptions{Id: pictureID2}},
	)
	require.NoError(t, err)
	require.NotZero(t, pic2.GetCopyrightsTextId())
	require.NotEmpty(t, pic2.GetCopyrightsTextId())
	require.NotEqual(t, pic.GetCopyrightsTextId(), pic2.GetCopyrightsTextId())

	text, err = textStorageRepository.Text(ctx, pic2.GetCopyrightsTextId())
	require.NoError(t, err)
	require.Equal(t, "Third", text)
}

func TestGetPicturesHasCopyrightsFilter(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	testerToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, testUsername, testPassword)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	withCopyrightsID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	withoutCopyrightsID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := NewPicturesClient(conn)

	moderCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)

	_, err = client.UpdatePicture(
		moderCtx,
		&UpdatePictureRequest{
			Picture:    &Picture{Id: withCopyrightsID, Copyrights: "Some copyrights"},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"copyrights"}},
		},
	)
	require.NoError(t, err)

	testCases := []struct {
		name            string
		pictureID       int64
		hasCopyrights   bool
		hasNoCopyrights bool
		expectFound     bool
	}{
		{"has-copyrights matches picture with copyrights", withCopyrightsID, true, false, true},
		{"has-copyrights excludes picture without copyrights", withoutCopyrightsID, true, false, false},
		{"no-copyrights excludes picture with copyrights", withCopyrightsID, false, true, false},
		{"no-copyrights matches picture without copyrights", withoutCopyrightsID, false, true, true},
	}

	for _, tc := range testCases {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			res, err := client.GetPictures(moderCtx, &PicturesRequest{
				Options: &PictureListOptions{
					Id:              tc.pictureID,
					HasCopyrights:   tc.hasCopyrights,
					HasNoCopyrights: tc.hasNoCopyrights,
				},
			})
			require.NoError(t, err)

			if tc.expectFound {
				require.Len(t, res.GetItems(), 1)
			} else {
				require.Empty(t, res.GetItems())
			}
		})
	}

	t.Run("ignored for non-moder users", func(t *testing.T) {
		t.Parallel()

		testerCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+testerToken.AccessToken)

		res, err := client.GetPictures(testerCtx, &PicturesRequest{
			Options: &PictureListOptions{Id: withoutCopyrightsID, HasCopyrights: true},
		})
		require.NoError(t, err)
		require.Len(t, res.GetItems(), 1)
	})
}

func TestGetPicturesLicensesFilter(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	testerToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, testUsername, testPassword)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pdID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	unlicensedID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := NewPicturesClient(conn)

	moderCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)

	_, err = client.UpdatePicture(
		moderCtx,
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:        pdID,
				SourceUrl: "https://example.com/source",
				License:   PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"source_url", "license"}},
		},
	)
	require.NoError(t, err)

	t.Run("moder: matches the picture with the filtered licence", func(t *testing.T) {
		t.Parallel()

		res, err := client.GetPictures(moderCtx, &PicturesRequest{
			Options: &PictureListOptions{
				Id:       pdID,
				Licenses: []PictureLicense{PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN},
			},
		})
		require.NoError(t, err)
		require.Len(t, res.GetItems(), 1)
	})

	t.Run("moder: excludes a picture with a different licence", func(t *testing.T) {
		t.Parallel()

		res, err := client.GetPictures(moderCtx, &PicturesRequest{
			Options: &PictureListOptions{
				Id:       unlicensedID,
				Licenses: []PictureLicense{PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN},
			},
		})
		require.NoError(t, err)
		require.Empty(t, res.GetItems())
	})

	t.Run("non-moder: forbidden", func(t *testing.T) {
		t.Parallel()

		testerCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+testerToken.AccessToken)

		_, err := client.GetPictures(testerCtx, &PicturesRequest{
			Options: &PictureListOptions{
				Id:       pdID,
				Licenses: []PictureLicense{PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN},
			},
		})
		require.Error(t, err)
		require.Equal(t, codes.PermissionDenied, status.Code(err))
	})
}

func TestUpdatePictureStatus(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := NewPicturesClient(conn)

	var picStatus schema.PictureStatus

	// accept
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	success, err := goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusAccepted, picStatus)

	// unaccept
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_INBOX,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	success, err = goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusInbox, picStatus)

	// remove without vote
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_REMOVING,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.ErrorContains(t, err, "PermissionDenied")

	// vote for remove
	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: -1},
	)
	require.NoError(t, err)

	// remove with vote
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_REMOVING,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	success, err = goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusRemoving, picStatus)

	// restore
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_INBOX,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	success, err = goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusInbox, picStatus)

	// accept
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.Error(t, err)
}

func TestReplacePicture(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)
	pictureID2 := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

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

	// tester (me)
	usersClient := NewUsersClient(conn)
	tester, err := usersClient.Me(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&MeRequest{},
	)
	require.NoError(t, err)

	client := NewPicturesClient(conn)

	var picStatus schema.PictureStatus

	// accept
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	success, err := goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusAccepted, picStatus)

	// set replace
	_, err = goquDB.Update(schema.PictureTable).Set(goqu.Record{
		schema.PictureTableReplacePictureIDColName: pictureID,
		schema.PictureTableOwnerIDColName:          tester.GetId(),
	}).Where(schema.PictureTableIDCol.Eq(pictureID2)).Executor().ExecContext(ctx)
	require.NoError(t, err)

	// accept replace
	_, err = client.AcceptReplacePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{
			Id: pictureID,
		},
	)
	require.Error(t, err)

	_, err = client.AcceptReplacePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{
			Id: pictureID2,
		},
	)
	require.NoError(t, err)

	success, err = goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusRemoving, picStatus)

	success, err = goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID2)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusAccepted, picStatus)
}

func TestGetPictures(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewPicturesClient(conn)

	_, err := client.GetPictures(ctx, &PicturesRequest{Fields: &PictureFields{
		NameText:    true,
		Image:       true,
		ThumbMedium: true,
	}, Limit: 100})
	require.ErrorContains(
		t,
		err,
		"PictureItem.ItemParentCacheAncestor.ItemID or OwnerID is required",
	)

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	_, err = client.GetPictures(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PicturesRequest{Fields: &PictureFields{
			NameText:         true,
			NameHtml:         true,
			Image:            true,
			ThumbMedium:      true,
			Views:            true,
			Votes:            true,
			CommentsCount:    true,
			ModerVote:        true,
			PictureItem:      &PictureItemsRequest{},
			DfDistance:       &DfDistanceRequest{},
			ImageGalleryFull: true,
			Path:             &PicturePathRequest{},
		}, Limit: 100},
	)
	require.NoError(t, err)
}

func TestGetPictureWithPerspectivePrefix(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewPicturesClient(conn)
	itemsClient := NewItemsClient(conn)
	cfg := config.LoadConfig(".")

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	itemName := fmt.Sprintf("vehicle-%d", random.Int())

	itemID := createItem(t, conn, cnt, &Item{
		Name:            itemName,
		IsGroup:         false,
		ItemTypeId:      ItemType_ITEM_TYPE_VEHICLE,
		Produced:        &wrapperspb.Int32Value{Value: 777},
		ProducedExactly: true,
		BeginYear:       1999,
		EndYear:         2001,
		BeginModelYear:  2000,
		EndModelYear:    2001,
		SpecId:          schema.SpecIDWorldwide,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{
		ItemID:        itemID,
		PerspectiveID: schema.PerspectiveIDUnderTheHood,
	}, PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	picture, err := client.GetPicture(
		ctx,
		&PicturesRequest{
			Language: "en",
			Options:  &PictureListOptions{Id: pictureID},
			Fields:   &PictureFields{NameText: true, NameHtml: true},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, picture.GetNameText())
	require.NotEmpty(t, picture.GetNameHtml())

	item, err := itemsClient.Item(ctx, &ItemRequest{
		Id:       itemID,
		Fields:   &ItemFields{NameText: true, NameHtml: true},
		Language: "en",
	})
	require.NoError(t, err)
	require.NotEmpty(t, item.GetNameText())
	require.NotEmpty(t, item.GetNameHtml())

	require.Equal(t, picture.GetNameText(), "Under The Hood "+item.GetNameText())
	require.Equal(t, picture.GetNameHtml(), "Under The Hood "+item.GetNameHtml())

	picture, err = client.GetPicture(
		ctx,
		&PicturesRequest{
			Language: schema.RussianLanguageCode,
			Options:  &PictureListOptions{Id: pictureID},
			Fields:   &PictureFields{NameText: true, NameHtml: true},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, picture.GetNameText())
	require.NotEmpty(t, picture.GetNameHtml())

	item, err = itemsClient.Item(ctx, &ItemRequest{
		Id:       itemID,
		Fields:   &ItemFields{NameText: true, NameHtml: true},
		Language: schema.RussianLanguageCode,
	})
	require.NoError(t, err)
	require.NotEmpty(t, item.GetNameText())
	require.NotEmpty(t, item.GetNameHtml())

	require.Equal(t, picture.GetNameText(), "Под капотом "+item.GetNameText())
	require.Equal(t, picture.GetNameHtml(), "Под капотом "+item.GetNameHtml())
}

func TestGetPicturePath(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewPicturesClient(conn)
	itemsClient := NewItemsClient(conn)
	cfg := config.LoadConfig(".")
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	randomInt := random.Int()

	// create brand
	brandName := fmt.Sprintf("Opel-%d", randomInt)
	brandID := createItem(t, conn, cnt, &Item{
		Name:       brandName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("opel-%d", randomInt),
		Body:       "",
	})

	itemName := fmt.Sprintf("vehicle-%d", randomInt)
	itemID := createItem(t, conn, cnt, &Item{
		Name:       itemName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	childName := fmt.Sprintf("child-%d", randomInt)
	childID := createItem(t, conn, cnt, &Item{
		Name:       childName,
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemParent{
			ItemId: itemID, ParentId: brandID, Type: ItemParentType_ITEM_TYPE_DEFAULT, Catname: "item",
		},
	)
	require.NoError(t, err)

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemParent{
			ItemId: childID, ParentId: itemID, Type: ItemParentType_ITEM_TYPE_DEFAULT, Catname: "child",
		},
	)
	require.NoError(t, err)

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: childID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	picture, err := client.GetPicture(
		ctx,
		&PicturesRequest{
			Options: &PictureListOptions{Id: pictureID},
			Fields: &PictureFields{Path: &PicturePathRequest{
				ParentId: brandID,
			}},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, picture.GetPath())
	require.Equal(t, "child", picture.GetPath()[0].GetItem().GetParents()[0].GetCatname())
	require.Equal(
		t,
		"item",
		picture.GetPath()[0].GetItem().GetParents()[0].GetItem().GetParents()[0].GetCatname(),
	)
}

func TestGetPicturesOrders(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewPicturesClient(conn)

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	testCases := []PicturesRequest_Order{
		PicturesRequest_ORDER_NONE,
		PicturesRequest_ORDER_CREATED_AT_DESC,
		PicturesRequest_ORDER_CREATED_AT_ASC,
		PicturesRequest_ORDER_RESOLUTION_DESC,
		PicturesRequest_ORDER_RESOLUTION_ASC,
		PicturesRequest_ORDER_FILESIZE_DESC,
		PicturesRequest_ORDER_FILESIZE_ASC,
		PicturesRequest_ORDER_COMMENTS,
		PicturesRequest_ORDER_VIEWS,
		PicturesRequest_ORDER_MODER_VOTES,
		PicturesRequest_ORDER_DF_DISTANCE_SIMILARITY,
		PicturesRequest_ORDER_REMOVING_DATE,
		PicturesRequest_ORDER_LIKES,
		PicturesRequest_ORDER_DISLIKES,
		PicturesRequest_ORDER_ACCEPT_DATETIME_DESC,
		PicturesRequest_ORDER_PERSPECTIVES,
	}

	for _, testCase := range testCases {
		t.Run(fmt.Sprintf("%v", testCase), func(t *testing.T) {
			t.Parallel()

			request := PicturesRequest{
				Fields: &PictureFields{
					NameText:      true,
					NameHtml:      true,
					Image:         true,
					ThumbMedium:   true,
					Views:         true,
					Votes:         true,
					CommentsCount: true,
					ModerVote:     true,
					PictureItem:   &PictureItemsRequest{},
				},
				Limit: 100,
				Order: testCase,
				Options: &PictureListOptions{
					PictureModerVote: &PictureModerVoteListOptions{},
					PictureItem:      &PictureItemListOptions{},
					DfDistance:       &DfDistanceListOptions{},
				},
			}

			_, err := client.GetPictures(
				metadata.AppendToOutgoingContext(
					ctx,
					authorizationHeader,
					bearerPrefix+token.AccessToken,
				),
				&request,
			)
			require.NoError(t, err)
		})
	}
}

func TestGetPicturesFilters(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewPicturesClient(conn)

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	request := PicturesRequest{
		Fields: &PictureFields{
			NameText:      true,
			NameHtml:      true,
			Image:         true,
			ThumbMedium:   true,
			Views:         true,
			Votes:         true,
			CommentsCount: true,
			ModerVote:     true,
			PictureItem:   &PictureItemsRequest{},
		},
		Limit: 100,
		Options: &PictureListOptions{
			PictureModerVote: &PictureModerVoteListOptions{},
			PictureItem:      &PictureItemListOptions{},
			DfDistance:       &DfDistanceListOptions{},
			Statuses: []PictureStatus{
				PictureStatus_PICTURE_STATUS_ACCEPTED,
				PictureStatus_PICTURE_STATUS_INBOX,
				PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			OwnerId:               123,
			AcceptedInDays:        3,
			CreateDate:            &date.Date{Year: 2025, Month: 1, Day: 1},
			AcceptDate:            &date.Date{Year: 2025, Month: 1, Day: 1},
			AddedFrom:             &date.Date{Year: 2025, Month: 1, Day: 1},
			CommentTopic:          &CommentTopicListOptions{MessagesGtZero: true},
			HasNoComments:         true,
			HasPoint:              true,
			HasNoPoint:            true,
			HasNoPictureItem:      true,
			ReplacePicture:        &PictureListOptions{},
			HasNoReplacePicture:   true,
			HasNoPictureModerVote: true,
			HasSpecialName:        true,
			HasCopyrights:         true,
			HasNoCopyrights:       true,
		},
	}

	_, err = client.GetPictures(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&request,
	)
	require.NoError(t, err)
}

func TestGetPictureIP(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()
	kc := cnt.Keycloak()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	ips := []net.IP{net.IPv4(172, 18, 0, 1), net.IPv4allrouter, net.IPv4allsys, net.IPv4bcast}

	for _, ip := range ips {
		// random is a shared *rand.Rand (not safe for concurrent use); draw on the parent
		// goroutine before the parallel subtest starts.
		identity := "t" + strconv.Itoa(int(random.Uint32()%100000))

		t.Run(ip.String(), func(t *testing.T) {
			t.Parallel()

			var pictureID int64

			var pgIP pgtype.Inet

			err := pgIP.Set(ip)
			require.NoError(t, err)

			success, err := goquDB.Insert(schema.PictureTable).Rows(schema.PictureRow{
				Identity:  identity,
				Status:    schema.PictureStatusAccepted,
				IP:        pgIP,
				CreatedAt: time.Now(),
				Point:     schema.NullPoint{Valid: false},
			}).Returning(schema.PictureTableIDCol).Executor().ScanValContext(ctx, &pictureID)
			require.NoError(t, err)
			require.True(t, success)

			token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
			require.NoError(t, err)
			require.NotNil(t, token)

			client := NewPicturesClient(conn)

			picture, err := client.GetPicture(
				metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
				&PicturesRequest{
					Options: &PictureListOptions{Id: pictureID},
				},
			)
			require.NoError(t, err)
			require.Equal(t, ip.String(), picture.GetIpAddress())
		})
	}
}

func TestInbox(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()
	kc := cnt.Keycloak()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	identity := "t" + strconv.Itoa(int(random.Uint32()%100000))

	var pgIP pgtype.Inet

	err = pgIP.Set(net.IPv4allrouter)
	require.NoError(t, err)

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	authCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)

	// GetInbox computes "current date" in the requesting user's profile timezone
	// (PicturesGRPCServer.resolveTimezone), not the test process's local timezone —
	// those can differ (the seeded admin user is Europe/London, the test runner may be
	// anything), which made this test flaky around timezone/day boundaries. Resolve
	// `now`/`yesterday` in that same timezone so the comparisons below are apples-to-apples.
	usersClient := NewUsersClient(conn)
	me, err := usersClient.Me(authCtx, &MeRequest{Fields: &UserFields{Timezone: true}})
	require.NoError(t, err)

	loc, err := time.LoadLocation(me.GetTimezone())
	require.NoError(t, err)

	now := time.Now().In(loc)
	yesterday := now.AddDate(0, 0, -1)

	_, err = goquDB.Insert(schema.PictureTable).Rows(schema.PictureRow{
		Identity:  identity,
		Status:    schema.PictureStatusInbox,
		IP:        pgIP,
		CreatedAt: now,
		Point:     schema.NullPoint{Valid: false},
	}).Executor().ExecContext(ctx)
	require.NoError(t, err)

	identity = "t" + strconv.Itoa(int(random.Uint32()%100000))

	_, err = goquDB.Insert(schema.PictureTable).Rows(schema.PictureRow{
		Identity:  identity,
		Status:    schema.PictureStatusInbox,
		IP:        pgIP,
		CreatedAt: yesterday,
		Point:     schema.NullPoint{Valid: false},
	}).Executor().ExecContext(ctx)
	require.NoError(t, err)

	client := NewPicturesClient(conn)

	resp, err := client.GetInbox(
		authCtx,
		&InboxRequest{
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.EqualValues(t, now.Day(), resp.GetCurrentDate().GetDay())
	require.EqualValues(t, now.Month(), resp.GetCurrentDate().GetMonth())
	require.EqualValues(t, now.Year(), resp.GetCurrentDate().GetYear())

	resp, err = client.GetInbox(
		authCtx,
		&InboxRequest{
			Language: "en",
			Date: &date.Date{
				Year:  int32(yesterday.Year()),  //nolint:gosec
				Month: int32(yesterday.Month()), //nolint:gosec
				Day:   int32(yesterday.Day()),   //nolint:gosec
			},
		},
	)
	require.NoError(t, err)
	require.EqualValues(t, yesterday.Day(), resp.GetCurrentDate().GetDay())
	require.EqualValues(t, yesterday.Month(), resp.GetCurrentDate().GetMonth())
	require.EqualValues(t, yesterday.Year(), resp.GetCurrentDate().GetYear())
	require.EqualValues(t, now.Day(), resp.GetNextDate().GetDay())
	require.EqualValues(t, now.Month(), resp.GetNextDate().GetMonth())
	require.EqualValues(t, now.Year(), resp.GetNextDate().GetYear())

	_, err = client.GetInbox(
		authCtx,
		&InboxRequest{
			BrandId:  1,
			Language: "en",
			Date: &date.Date{
				Year:  2005,
				Month: 1,
				Day:   1,
			},
		},
	)
	require.NoError(t, err)

	_, err = client.GetInbox(
		ctx,
		&InboxRequest{
			BrandId:  1,
			Language: "en",
			Date: &date.Date{
				Year:  2005,
				Month: 1,
				Day:   1,
			},
		},
	)
	require.ErrorContains(t, err, "Unauthenticated")
}

func TestNewbox(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	ctx := t.Context()
	kc := cnt.Keycloak()
	client := NewPicturesClient(conn)

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := CreatePicture(
		t,
		cnt,
		"./test/test.jpg",
		PicturePostForm{ItemID: itemID},
		token.AccessToken,
	)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdatePictureRequest{
			Picture: &Picture{
				Id:     pictureID,
				Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	_, err = client.GetNewbox(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&NewboxRequest{
			Language: "en",
		},
	)
	require.NoError(t, err)
}

func TestInboxCount(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	ctx := t.Context()
	kc := cnt.Keycloak()

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewPicturesClient(conn)

	res, err := client.GetPicturesPaginator(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PicturesRequest{
			Options: &PictureListOptions{
				Status: PictureStatus_PICTURE_STATUS_INBOX,
			},
			Paginator: true,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, res)
}

func TestCorrectFileNamesVote(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewPicturesClient(conn)
	itemsClient := NewItemsClient(conn)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	vehicleName := fmt.Sprintf("Toyota %d Corolla", randomInt)
	vehicleID := createItem(t, conn, cnt, &Item{
		Name:       vehicleName,
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: vehicleID},
		PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	newName := fmt.Sprintf("Toyota %d Corolla New", randomInt)
	_, err = itemsClient.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&UpdateItemRequest{
			Item:       &Item{Id: vehicleID, Name: newName},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"name"}},
		},
	)
	require.NoError(t, err)

	_, err = client.CorrectFileNames(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	picture, err := client.GetPicture(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PicturesRequest{
			Options: &PictureListOptions{Id: pictureID},
			Fields:  &PictureFields{Image: true},
		},
	)
	require.NoError(t, err)
	require.Contains(t,
		picture.GetImage().GetSrc(),
		fmt.Sprintf("t/toyota_%d_corolla_new/toyota_%d_corolla_new", randomInt, randomInt),
	)

	request, err := http.NewRequestWithContext(
		ctx,
		http.MethodHead,
		picture.GetImage().GetSrc(),
		nil,
	)
	require.NoError(t, err)

	httpResponse, err := http.DefaultClient.Do(request) //nolint: bodyclose
	require.NoError(t, err)

	defer util.Close(httpResponse.Body)

	require.EqualValues(t, 203718, httpResponse.ContentLength)

	// add brand
	brandName := fmt.Sprintf("Toyota %d", randomInt)
	brandID := createItem(t, conn, cnt, &Item{
		Name:       brandName,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("toyota-%d", randomInt),
	})

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemParent{
			ItemId: vehicleID, ParentId: brandID, Type: ItemParentType_ITEM_TYPE_DEFAULT, Catname: "corolla",
		},
	)
	require.NoError(t, err)

	_, err = client.CorrectFileNames(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	picture, err = client.GetPicture(ctx, &PicturesRequest{
		Options: &PictureListOptions{Id: pictureID},
		Fields:  &PictureFields{Image: true},
	})
	require.NoError(t, err)
	require.Contains(t,
		picture.GetImage().GetSrc(),
		fmt.Sprintf("t/toyota-%d/corolla_new/toyota_%d_corolla_new", randomInt, randomInt),
	)

	request, err = http.NewRequestWithContext(
		ctx,
		http.MethodHead,
		picture.GetImage().GetSrc(),
		nil,
	)
	require.NoError(t, err)

	httpResponse, err = http.DefaultClient.Do(request) //nolint: bodyclose
	require.NoError(t, err)

	defer util.Close(httpResponse.Body)

	require.EqualValues(t, 203718, httpResponse.ContentLength)

	// add second brand
	brand2Name := fmt.Sprintf("Peugeot %d", randomInt)
	brand2ID := createItem(t, conn, cnt, &Item{
		Name:       brand2Name,
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("peugeot-%d", randomInt),
	})

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&ItemParent{
			ItemId: vehicleID, ParentId: brand2ID, Type: ItemParentType_ITEM_TYPE_DEFAULT, Catname: "corolla",
		},
	)
	require.NoError(t, err)

	_, err = client.CorrectFileNames(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	picture, err = client.GetPicture(ctx, &PicturesRequest{
		Options: &PictureListOptions{Id: pictureID},
		Fields:  &PictureFields{Image: true},
	})
	require.NoError(t, err)
	require.Contains(
		t,
		picture.GetImage().GetSrc(),
		fmt.Sprintf(
			"p/peugeot-%d/toyota-%d/corolla_new/toyota_%d_corolla_new",
			randomInt,
			randomInt,
			randomInt,
		),
	)

	request, err = http.NewRequestWithContext(
		ctx,
		http.MethodHead,
		picture.GetImage().GetSrc(),
		nil,
	)
	require.NoError(t, err)

	httpResponse, err = http.DefaultClient.Do(request) //nolint: bodyclose
	require.NoError(t, err)

	defer util.Close(httpResponse.Body)

	require.EqualValues(t, 203718, httpResponse.ContentLength)
}

func TestGetGallery(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewPicturesClient(conn)
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	addPicture(
		t,
		cnt,
		conn,
		"./test/test.jpg",
		PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX,
		token.AccessToken,
	)

	_, err = client.GetGallery(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&GalleryRequest{
			Request: &PicturesRequest{
				Options: &PictureListOptions{
					PictureItem: &PictureItemListOptions{
						ItemId: itemID,
					},
				},
				Fields: &PictureFields{
					NameText:         true,
					NameHtml:         true,
					Image:            true,
					ThumbMedium:      true,
					Views:            true,
					Votes:            true,
					CommentsCount:    true,
					ModerVote:        true,
					PictureItem:      &PictureItemsRequest{},
					DfDistance:       &DfDistanceRequest{},
					ImageGalleryFull: true,
					Path:             &PicturePathRequest{},
				},
				Limit: 100,
			},
		},
	)
	require.NoError(t, err)
}

func TestGetPerspectives(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	grpcClient := NewPicturesClient(conn)

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

	_, err = grpcClient.GetPerspectives(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestGetPerspectivePages(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	grpcClient := NewPicturesClient(conn)

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

	_, err = grpcClient.GetPerspectivePages(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestGetPersonPictures(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewPicturesClient(conn)
	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("person-%d", randomInt),
		ItemTypeId: ItemType_ITEM_TYPE_PERSON,
	})

	CreatePicture(
		t,
		cnt,
		"./test/test.jpg",
		PicturePostForm{ItemID: itemID},
		token.AccessToken,
	)

	_, err = client.GetPictures(metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+token.AccessToken,
	), &PicturesRequest{
		Fields: &PictureFields{
			CommentsCount: true,
			ModerVote:     true,
			NameHtml:      true,
			NameText:      true,
			ThumbMedium:   true,
			Views:         true,
			Votes:         true,
		},
		Language: "en",
		Limit:    12,
		Options: &PictureListOptions{
			PictureItem: &PictureItemListOptions{
				ItemId: itemID,
				TypeId: PictureItemType_PICTURE_ITEM_AUTHOR,
			},
			Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
		},
		Order:     PicturesRequest_ORDER_LIKES,
		Page:      1,
		Paginator: true,
	})
	require.NoError(t, err)
}

func TestGetCanonicalRouteHidesRemovingFromAnonymous(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()

	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	goquDB, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
		PictureStatus_PICTURE_STATUS_INBOX, adminToken.AccessToken)

	var identity string

	_, err = goquDB.Select(schema.PictureTableIdentityCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &identity)
	require.NoError(t, err)

	_, err = goquDB.Update(schema.PictureTable).
		Set(goqu.Record{schema.PictureTableStatusColName: schema.PictureStatusRemoving}).
		Where(schema.PictureTableIDCol.Eq(pictureID)).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	client := NewPicturesClient(conn)

	// Anonymous (every SSR render) must get NOT_FOUND, not a redirect to a URL that then 404s.
	_, err = client.GetCanonicalRoute(ctx, &CanonicalRouteRequest{Identity: identity})
	require.ErrorContains(t, err, "NotFound")

	// A moderator still gets the route.
	_, err = client.GetCanonicalRoute(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken.AccessToken),
		&CanonicalRouteRequest{Identity: identity},
	)
	require.NoError(t, err)
}
