package integration

import (
	"database/sql"
	"fmt"
	"math/rand"
	"net"
	"net/http"
	"strconv"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/textstorage"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
	"github.com/autowp/goautowp/internal/util"
	"github.com/doug-martin/goqu/v9"
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
	client := pb.NewPicturesClient(conn)
	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := createPicture(
		t,
		"../testdata/images/test.jpg",
		pictureshttp.PicturePostForm{ItemID: itemID},
		token.AccessToken,
	)

	_, err = client.View(ctx, &pb.PicturesViewRequest{PictureId: pictureID})
	require.NoError(t, err)
}

func TestVote(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := pb.NewPicturesClient(conn)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := createPicture(
		t,
		"../testdata/images/test.jpg",
		pictureshttp.PicturePostForm{ItemID: itemID},
		token.AccessToken,
	)

	_, err = client.Vote(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PicturesVoteRequest{PictureId: pictureID, Value: 1},
	)
	require.NoError(t, err)
}

func TestModerVoteTemplate(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := pb.NewPicturesClient(conn)

	template, err := client.CreateModerVoteTemplate(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.ModerVoteTemplate{Message: "test", Vote: 1},
	)
	require.NoError(t, err)

	_, err = client.GetModerVoteTemplates(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = client.DeleteModerVoteTemplate(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.DeleteModerVoteTemplateRequest{Id: template.GetId()},
	)
	require.NoError(t, err)
}

func TestModerVote(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := cnt.Config()

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: 1, Save: true},
	)
	require.NoError(t, err)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: 1, Save: true},
	)
	require.NoError(t, err)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: -1, Save: true},
	)
	require.NoError(t, err)

	_, err = client.DeleteModerVote(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.DeleteModerVoteRequest{PictureId: pictureID},
	)
	require.NoError(t, err)

	secondUserID, _ := testutil.UserWithCleanHistory(
		t,
		conn,
		cfg.Keycloak,
		goquDB,
		testUsername,
		testPassword,
	)

	var picStatus schema.PictureStatus

	// test unaccepting
	_, err = goquDB.Update(schema.PictureTable).Set(goqu.Record{
		schema.PictureTableStatusColName:             schema.PictureStatusAccepted,
		schema.PictureTableChangeStatusUserIDColName: secondUserID,
	}).Where(schema.PictureTableIDCol.Eq(pictureID)).Executor().ExecContext(ctx)
	require.NoError(t, err)

	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: -1, Save: false},
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: 1, Save: false},
	)
	require.NoError(t, err)

	success, err = goquDB.Select(schema.PictureTableStatusCol).
		From(schema.PictureTable).Where(schema.PictureTableIDCol.Eq(pictureID)).
		ScanValContext(ctx, &picStatus)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, schema.PictureStatusInbox, picStatus)

	_, err = client.DeleteModerVote(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.DeleteModerVoteRequest{PictureId: pictureID},
	)
	require.NoError(t, err)
}

func TestUserSummary(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := pb.NewPicturesClient(conn)

	_, err = client.GetUserSummary(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestFlopNormalizeAndRepair(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := cnt.Config()

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	_, err = goquDB.Update(schema.PictureTable).Set(goqu.Record{
		schema.PictureTableStatusColName: schema.PictureStatusInbox,
	}).Where(schema.PictureTableIDCol.Eq(pictureID)).Executor().ExecContext(ctx)
	require.NoError(t, err)

	client := pb.NewPicturesClient(conn)

	_, err = client.Flop(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	_, err = client.Normalize(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	_, err = client.Repair(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)
}

func TestDeleteSimilar(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := pb.NewPicturesClient(conn)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	picture1ID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)
	picture2ID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	_, err = client.DeleteSimilar(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.DeleteSimilarRequest{Id: picture1ID, SimilarPictureId: picture2ID},
	)
	require.NoError(t, err)
}

func TestPictureItemAreaAndPerspective(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.UpdatePictureItem(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureItemRequest{
			PictureItem: &pb.PictureItem{
				PictureId:  pictureID,
				ItemId:     itemID,
				Type:       pb.PictureItemType_PICTURE_ITEM_CONTENT,
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureItemRequest{
			PictureItem: &pb.PictureItem{
				PictureId:  pictureID,
				ItemId:     itemID,
				Type:       pb.PictureItemType_PICTURE_ITEM_CONTENT,
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureItemRequest{
			PictureItem: &pb.PictureItem{
				PictureId:     pictureID,
				ItemId:        itemID,
				Type:          pb.PictureItemType_PICTURE_ITEM_CONTENT,
				PerspectiveId: 1,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"perspective_id"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdatePictureItem(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureItemRequest{
			PictureItem: &pb.PictureItem{
				PictureId:     pictureID,
				ItemId:        itemID,
				Type:          pb.PictureItemType_PICTURE_ITEM_CONTENT,
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

	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	itemID1 := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-1-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	itemID2 := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-2-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID1},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.SetPictureItemItemID(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.SetPictureItemItemIDRequest{
			PictureId: pictureID,
			ItemId:    itemID1,
			Type:      pb.PictureItemType_PICTURE_ITEM_CONTENT,
			NewItemId: itemID2,
		},
	)
	require.NoError(t, err)

	_, err = client.DeletePictureItem(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.DeletePictureItemRequest{
			PictureId: pictureID,
			ItemId:    itemID1,
			Type:      pb.PictureItemType_PICTURE_ITEM_CONTENT,
		},
	)
	require.Error(t, err)

	_, err = client.DeletePictureItem(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.DeletePictureItemRequest{
			PictureId: pictureID,
			ItemId:    itemID2,
			Type:      pb.PictureItemType_PICTURE_ITEM_CONTENT,
		},
	)
	require.NoError(t, err)
}

func TestCreatePictureItemAuthorByOwner(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()

	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	testerToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, testUsername, testPassword)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	personID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("person-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_PERSON,
	})
	vehicleID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	// Picture owned by the tester, still in the inbox.
	ownPictureID := createPicture(
		t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: vehicleID}, testerToken.AccessToken,
	)
	// Picture owned by the admin.
	otherPictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: vehicleID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, adminToken.AccessToken)

	client := pb.NewPicturesClient(conn)
	testerCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+testerToken.AccessToken)

	// The owner may credit an author on their own inbox picture.
	_, err = client.CreatePictureItem(testerCtx, &pb.CreatePictureItemRequest{
		PictureId: ownPictureID,
		ItemId:    personID,
		Type:      pb.PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.NoError(t, err)

	// ... and remove it again.
	_, err = client.DeletePictureItem(testerCtx, &pb.DeletePictureItemRequest{
		PictureId: ownPictureID,
		ItemId:    personID,
		Type:      pb.PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.NoError(t, err)

	// Not a CONTENT link, though - that stays moderator-only.
	_, err = client.CreatePictureItem(testerCtx, &pb.CreatePictureItemRequest{
		PictureId: ownPictureID,
		ItemId:    vehicleID,
		Type:      pb.PictureItemType_PICTURE_ITEM_CONTENT,
	})
	require.ErrorContains(t, err, "PermissionDenied")

	// ... and not an author link on someone else's picture.
	_, err = client.CreatePictureItem(testerCtx, &pb.CreatePictureItemRequest{
		PictureId: otherPictureID,
		ItemId:    personID,
		Type:      pb.PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.ErrorContains(t, err, "PermissionDenied")
}

func TestCreatePictureItemBlockedWhenAuthorSuppressed(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()

	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	suppressedPersonID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("Suppressed Author %d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_PERSON,
	})
	otherPersonID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("Other Author %d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_PERSON,
	})
	vehicleID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := createPicture(
		t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: vehicleID}, adminToken.AccessToken,
	)

	apiCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken.AccessToken)

	picturesClient := pb.NewPicturesClient(conn)
	_, err = picturesClient.CreatePictureItem(apiCtx, &pb.CreatePictureItemRequest{
		PictureId: pictureID,
		ItemId:    suppressedPersonID,
		Type:      pb.PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.NoError(t, err)

	itemsClient := pb.NewItemsClient(conn)
	_, err = itemsClient.SuppressAuthor(apiCtx, &pb.SuppressAuthorRequest{
		ItemId:       suppressedPersonID,
		Reference:    fmt.Sprintf("TEST-%d", random.Int()),
		ContactEmail: "requester@example.com",
		Note:         "test note",
	})
	require.NoError(t, err)

	// The picture is now flagged author-suppressed - re-adding *any* author, not just the
	// suppressed one, must be refused.
	_, err = picturesClient.CreatePictureItem(apiCtx, &pb.CreatePictureItemRequest{
		PictureId: pictureID,
		ItemId:    otherPersonID,
		Type:      pb.PictureItemType_PICTURE_ITEM_AUTHOR,
	})
	require.Error(t, err)
	require.Equal(t, codes.FailedPrecondition, status.Code(err))

	// A non-author link is unaffected.
	_, err = picturesClient.CreatePictureItem(apiCtx, &pb.CreatePictureItemRequest{
		PictureId: pictureID,
		ItemId:    vehicleID,
		Type:      pb.PictureItemType_PICTURE_ITEM_CONTENT,
	})
	require.NoError(t, err)
}

func TestPictureCrop(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id: pictureID,
				Crop: &pb.PictureCrop{
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id: pictureID,
				Crop: &pb.PictureCrop{
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

	res, err := client.GetPicture(ctx, &pb.PicturesRequest{
		Options: &pb.PictureListOptions{Id: pictureID},
		Fields:  &pb.PictureFields{ImageGallery: true},
	})
	require.NoError(t, err)
	require.NotEmpty(t, res.GetImageGallery())
	require.EqualValues(t, 10, res.GetImageGallery().GetWidth())
	require.EqualValues(t, 10, res.GetImageGallery().GetHeight())
}

func TestPictureCropByOneAxis(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	imageStorage, err := cnt.ImageStorage(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id: pictureID,
				Crop: &pb.PictureCrop{
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

	res, err := client.GetPicture(ctx, &pb.PicturesRequest{
		Options: &pb.PictureListOptions{Id: pictureID},
		Fields:  &pb.PictureFields{Image: true},
	})
	require.NoError(t, err)

	crop, err := imageStorage.ImageCrop(ctx, int(res.GetImage().GetId()))
	require.NoError(t, err)
	require.Equal(t, 0, crop.Left)
	require.Equal(t, 0, crop.Top)
	require.Equal(t, 200, crop.Width)
	require.Equal(t, 130, crop.Height)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id: pictureID,
				Crop: &pb.PictureCrop{
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

	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	imageStorage, err := cnt.ImageStorage(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id: pictureID,
				Crop: &pb.PictureCrop{
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

	res, err := client.GetPicture(ctx, &pb.PicturesRequest{
		Options: &pb.PictureListOptions{Id: pictureID},
		Fields:  &pb.PictureFields{Image: true},
	})
	require.NoError(t, err)

	crop, err := imageStorage.ImageCrop(ctx, int(res.GetImage().GetId()))
	require.NoError(t, err)
	require.Equal(t, 0, crop.Left)
	require.Equal(t, 0, crop.Top)
	require.Equal(t, 1000, crop.Width)
	require.Equal(t, 140, crop.Height)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id: pictureID,
				Crop: &pb.PictureCrop{
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id: pictureID,
				Crop: &pb.PictureCrop{
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
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	replacePictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)
	pictureID := addPicture(
		t,
		"../testdata/images/test.jpg",
		pictureshttp.PicturePostForm{ReplacePictureID: replacePictureID},
		pb.PictureStatus_PICTURE_STATUS_INBOX,
		token.AccessToken,
	)

	client := pb.NewPicturesClient(conn)

	_, err = client.ClearReplacePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{Id: pictureID},
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

	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
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
		&pb.PicturesRequest{Options: &pb.PictureListOptions{Id: pictureID}},
	)
	require.NoError(t, err)
	require.Nil(t, pic.GetPoint())

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
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

	pic, err = client.GetPicture(ctx, &pb.PicturesRequest{Options: &pb.PictureListOptions{Id: pictureID}})
	require.NoError(t, err)
	require.NotNil(t, pic.GetPoint())
	require.InDelta(t, float64(10), pic.GetPoint().GetLatitude(), 0.001)
	require.InDelta(t, float64(0), pic.GetPoint().GetLongitude(), 0.001)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
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

	pic, err = client.GetPicture(ctx, &pb.PicturesRequest{Options: &pb.PictureListOptions{Id: pictureID}})
	require.NoError(t, err)
	require.NotNil(t, pic.GetPoint())
	require.InDelta(t, float64(0), pic.GetPoint().GetLatitude(), 0.001)
	require.InDelta(t, float64(10), pic.GetPoint().GetLongitude(), 0.001)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
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

	pic, err = client.GetPicture(ctx, &pb.PicturesRequest{Options: &pb.PictureListOptions{Id: pictureID}})
	require.NoError(t, err)
	require.NotNil(t, pic.GetPoint())
	require.InDelta(t, float64(-10), pic.GetPoint().GetLatitude(), 0.001)
	require.InDelta(t, float64(10), pic.GetPoint().GetLongitude(), 0.001)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id: pictureID,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"point"}},
		},
	)
	require.NoError(t, err)

	pic, err = client.GetPicture(ctx, &pb.PicturesRequest{Options: &pb.PictureListOptions{Id: pictureID}})
	require.NoError(t, err)
	require.Nil(t, pic.GetPoint())
}

func TestUpdatePicture(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
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
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	textStorageRepository := textstorage.New(goquDB)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	pictureID2 := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:         pictureID,
				Copyrights: "First",
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"copyrights"}},
		},
	)
	require.NoError(t, err)

	pic, err := client.GetPicture(
		ctx,
		&pb.PicturesRequest{Options: &pb.PictureListOptions{Id: pictureID}},
	)
	require.NoError(t, err)
	require.NotZero(t, pic.GetCopyrightsTextId())
	require.NotEmpty(t, pic.GetCopyrightsTextId())

	text, err := textStorageRepository.Text(ctx, pic.GetCopyrightsTextId())
	require.NoError(t, err)
	require.Equal(t, "First", text)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:         pictureID,
				Copyrights: "Second",
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"copyrights"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
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
		&pb.PicturesRequest{Options: &pb.PictureListOptions{Id: pictureID2}},
	)
	require.NoError(t, err)
	require.NotZero(t, pic2.GetCopyrightsTextId())
	require.NotEmpty(t, pic2.GetCopyrightsTextId())
	require.NotEqual(t, pic.GetCopyrightsTextId(), pic2.GetCopyrightsTextId())

	text, err = textStorageRepository.Text(ctx, pic2.GetCopyrightsTextId())
	require.NoError(t, err)
	require.Equal(t, "Third", text)
}

func TestUpdatePictureCopyrightsRecordsGdprObjectionHit(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	apiCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken)

	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	suppressedName := fmt.Sprintf("Copyright Suppressed %d", random.Int())

	autowpClient := pb.NewAutowpClient(conn)
	_, err = autowpClient.CreateGdprObjection(apiCtx, &pb.CreateGdprObjectionRequest{
		Names:        []string{suppressedName},
		Reference:    fmt.Sprintf("TEST-%d", random.Int()),
		ContactEmail: "requester@example.com",
		Note:         "test note",
	})
	require.NoError(t, err)

	db, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	var objectionID int64

	found, err := db.Select(schema.GdprObjectionNameTableObjectionIDCol).
		From(schema.GdprObjectionNameTable).
		Where(schema.GdprObjectionNameTableNameCol.Eq(suppressedName)).
		ScanValContext(ctx, &objectionID)
	require.NoError(t, err)
	require.True(t, found)

	picturesClient := pb.NewPicturesClient(conn)
	_, err = picturesClient.UpdatePicture(apiCtx, &pb.UpdatePictureRequest{
		Picture: &pb.Picture{
			Id:         pictureID,
			Copyrights: "Photo by " + suppressedName,
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"copyrights"}},
	})
	require.NoError(t, err)

	var hitCount int32

	found, err = db.Select(schema.GdprObjectionTableHitCountCol).
		From(schema.GdprObjectionTable).
		Where(schema.GdprObjectionTableIDCol.Eq(objectionID)).
		ScanValContext(ctx, &hitCount)
	require.NoError(t, err)
	require.True(t, found)
	require.Equal(t, int32(1), hitCount)

	candidateExists, err := db.Select(goqu.V(true)).
		From(schema.GdprObjectionCleanupCandidateTable).
		Where(
			schema.GdprObjectionCleanupCandidateTableObjectionIDCol.Eq(objectionID),
			schema.GdprObjectionCleanupCandidateTableEntityTypeCol.Eq(
				schema.GdprObjectionCleanupCandidateEntityTypeCopyrightsTextPicture,
			),
			schema.GdprObjectionCleanupCandidateTableEntityIDCol.Eq(pictureID),
		).
		ScanValContext(ctx, new(bool))
	require.NoError(t, err)
	require.True(t, candidateExists)
}

func TestGetPicturesHasCopyrightsFilter(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	testerToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, testUsername, testPassword)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	withCopyrightsID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	withoutCopyrightsID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	moderCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken)

	_, err = client.UpdatePicture(
		moderCtx,
		&pb.UpdatePictureRequest{
			Picture:    &pb.Picture{Id: withCopyrightsID, Copyrights: "Some copyrights"},
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

			res, err := client.GetPictures(moderCtx, &pb.PicturesRequest{
				Options: &pb.PictureListOptions{
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

		testerCtx := metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		)

		res, err := client.GetPictures(testerCtx, &pb.PicturesRequest{
			Options: &pb.PictureListOptions{Id: withoutCopyrightsID, HasCopyrights: true},
		})
		require.NoError(t, err)
		require.Len(t, res.GetItems(), 1)
	})
}

func TestGetPicturesLicensesFilter(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	testerToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, testUsername, testPassword)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pdID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	unlicensedID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	moderCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken)

	_, err = client.UpdatePicture(
		moderCtx,
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:        pdID,
				SourceUrl: "https://example.com/source",
				License:   pb.PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"source_url", "license"}},
		},
	)
	require.NoError(t, err)

	t.Run("moder: matches the picture with the filtered licence", func(t *testing.T) {
		t.Parallel()

		res, err := client.GetPictures(moderCtx, &pb.PicturesRequest{
			Options: &pb.PictureListOptions{
				Id:       pdID,
				Licenses: []pb.PictureLicense{pb.PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN},
			},
		})
		require.NoError(t, err)
		require.Len(t, res.GetItems(), 1)
	})

	t.Run("moder: excludes a picture with a different licence", func(t *testing.T) {
		t.Parallel()

		res, err := client.GetPictures(moderCtx, &pb.PicturesRequest{
			Options: &pb.PictureListOptions{
				Id:       unlicensedID,
				Licenses: []pb.PictureLicense{pb.PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN},
			},
		})
		require.NoError(t, err)
		require.Empty(t, res.GetItems())
	})

	t.Run("non-moder: forbidden", func(t *testing.T) {
		t.Parallel()

		testerCtx := metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		)

		_, err := client.GetPictures(testerCtx, &pb.PicturesRequest{
			Options: &pb.PictureListOptions{
				Id:       pdID,
				Licenses: []pb.PictureLicense{pb.PictureLicense_PICTURE_LICENSE_PUBLIC_DOMAIN},
			},
		})
		require.Error(t, err)
		require.Equal(t, codes.PermissionDenied, status.Code(err))
	})
}

func TestUpdatePictureStatus(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

	client := pb.NewPicturesClient(conn)

	var picStatus schema.PictureStatus

	// accept
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_INBOX,
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_REMOVING,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.ErrorContains(t, err, "PermissionDenied")

	// vote for remove
	_, err = client.UpdateModerVote(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdateModerVoteRequest{PictureId: pictureID, Reason: "test", Vote: -1},
	)
	require.NoError(t, err)

	// remove with vote
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_REMOVING,
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_INBOX,
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.Error(t, err)
}

func TestReplacePicture(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)
	pictureID2 := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)

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
	usersClient := pb.NewUsersClient(conn)
	tester, err := usersClient.Me(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&pb.MeRequest{},
	)
	require.NoError(t, err)

	client := pb.NewPicturesClient(conn)

	var picStatus schema.PictureStatus

	// accept
	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{
			Id: pictureID,
		},
	)
	require.Error(t, err)

	_, err = client.AcceptReplacePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{
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

	client := pb.NewPicturesClient(conn)

	_, err := client.GetPictures(ctx, &pb.PicturesRequest{Fields: &pb.PictureFields{
		NameText:    true,
		Image:       true,
		ThumbMedium: true,
	}, Limit: 100})
	require.ErrorContains(
		t,
		err,
		"PictureItem.ItemParentCacheAncestor.ItemID or OwnerID is required",
	)

	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	itemID := createItem(t, &pb.Item{
		Name: "GetPictures fixture", ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})
	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	result, err := client.GetPictures(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PicturesRequest{Options: &pb.PictureListOptions{Ids: []int64{pictureID}}, Fields: &pb.PictureFields{
			NameText:         true,
			NameHtml:         true,
			Image:            true,
			ThumbMedium:      true,
			Views:            true,
			Votes:            true,
			CommentsCount:    true,
			ModerVote:        true,
			PictureItem:      &pb.PictureItemsRequest{},
			DfDistance:       &pb.DfDistanceRequest{},
			ImageGalleryFull: true,
			Path:             &pb.PicturePathRequest{},
		}, Limit: 100},
	)
	require.NoError(t, err)
	require.Len(t, result.GetItems(), 1)
	require.Equal(t, pictureID, result.GetItems()[0].GetId())
}

func TestGetPictureWithPerspectivePrefix(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := pb.NewPicturesClient(conn)
	itemsClient := pb.NewItemsClient(conn)
	cfg := cnt.Config()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	itemName := fmt.Sprintf("vehicle-%d", random.Int())

	itemID := createItem(t, &pb.Item{
		Name:            itemName,
		IsGroup:         false,
		ItemTypeId:      pb.ItemType_ITEM_TYPE_VEHICLE,
		Produced:        &wrapperspb.Int32Value{Value: 777},
		ProducedExactly: true,
		BeginYear:       1999,
		EndYear:         2001,
		BeginModelYear:  2000,
		EndModelYear:    2001,
		SpecId:          schema.SpecIDWorldwide,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{
		ItemID:        itemID,
		PerspectiveID: schema.PerspectiveIDUnderTheHood,
	}, pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	picture, err := client.GetPicture(
		ctx,
		&pb.PicturesRequest{
			Language: "en",
			Options:  &pb.PictureListOptions{Id: pictureID},
			Fields:   &pb.PictureFields{NameText: true, NameHtml: true},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, picture.GetNameText())
	require.NotEmpty(t, picture.GetNameHtml())

	item, err := itemsClient.Item(ctx, &pb.ItemRequest{
		Id:       itemID,
		Fields:   &pb.ItemFields{NameText: true, NameHtml: true},
		Language: "en",
	})
	require.NoError(t, err)
	require.NotEmpty(t, item.GetNameText())
	require.NotEmpty(t, item.GetNameHtml())

	require.Equal(t, picture.GetNameText(), "Under The Hood "+item.GetNameText())
	require.Equal(t, picture.GetNameHtml(), "Under The Hood "+item.GetNameHtml())

	picture, err = client.GetPicture(
		ctx,
		&pb.PicturesRequest{
			Language: schema.RussianLanguageCode,
			Options:  &pb.PictureListOptions{Id: pictureID},
			Fields:   &pb.PictureFields{NameText: true, NameHtml: true},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, picture.GetNameText())
	require.NotEmpty(t, picture.GetNameHtml())

	item, err = itemsClient.Item(ctx, &pb.ItemRequest{
		Id:       itemID,
		Fields:   &pb.ItemFields{NameText: true, NameHtml: true},
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
	client := pb.NewPicturesClient(conn)
	itemsClient := pb.NewItemsClient(conn)
	cfg := cnt.Config()
	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	randomInt := random.Int()

	// create brand
	brandName := fmt.Sprintf("Opel-%d", randomInt)
	brandID := createItem(t, &pb.Item{
		Name:       brandName,
		IsGroup:    true,
		ItemTypeId: pb.ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("opel-%d", randomInt),
		Body:       "",
	})

	itemName := fmt.Sprintf("vehicle-%d", randomInt)
	itemID := createItem(t, &pb.Item{
		Name:       itemName,
		IsGroup:    true,
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	childName := fmt.Sprintf("child-%d", randomInt)
	childID := createItem(t, &pb.Item{
		Name:       childName,
		IsGroup:    false,
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.ItemParent{
			ItemId: itemID, ParentId: brandID, Type: pb.ItemParentType_ITEM_TYPE_DEFAULT, Catname: "item",
		},
	)
	require.NoError(t, err)

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.ItemParent{
			ItemId: childID, ParentId: itemID, Type: pb.ItemParentType_ITEM_TYPE_DEFAULT, Catname: "child",
		},
	)
	require.NoError(t, err)

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: childID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	picture, err := client.GetPicture(
		ctx,
		&pb.PicturesRequest{
			Options: &pb.PictureListOptions{Id: pictureID},
			Fields: &pb.PictureFields{Path: &pb.PicturePathRequest{
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

	client := pb.NewPicturesClient(conn)

	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	testCases := []pb.PicturesRequest_Order{
		pb.PicturesRequest_ORDER_NONE,
		pb.PicturesRequest_ORDER_CREATED_AT_DESC,
		pb.PicturesRequest_ORDER_CREATED_AT_ASC,
		pb.PicturesRequest_ORDER_RESOLUTION_DESC,
		pb.PicturesRequest_ORDER_RESOLUTION_ASC,
		pb.PicturesRequest_ORDER_FILESIZE_DESC,
		pb.PicturesRequest_ORDER_FILESIZE_ASC,
		pb.PicturesRequest_ORDER_COMMENTS,
		pb.PicturesRequest_ORDER_VIEWS,
		pb.PicturesRequest_ORDER_MODER_VOTES,
		pb.PicturesRequest_ORDER_DF_DISTANCE_SIMILARITY,
		pb.PicturesRequest_ORDER_REMOVING_DATE,
		pb.PicturesRequest_ORDER_LIKES,
		pb.PicturesRequest_ORDER_DISLIKES,
		pb.PicturesRequest_ORDER_ACCEPT_DATETIME_DESC,
		pb.PicturesRequest_ORDER_PERSPECTIVES,
	}

	for _, testCase := range testCases {
		t.Run(fmt.Sprintf("%v", testCase), func(t *testing.T) {
			t.Parallel()

			request := pb.PicturesRequest{
				Fields: &pb.PictureFields{
					NameText:      true,
					NameHtml:      true,
					Image:         true,
					ThumbMedium:   true,
					Views:         true,
					Votes:         true,
					CommentsCount: true,
					ModerVote:     true,
					PictureItem:   &pb.PictureItemsRequest{},
				},
				Limit: 100,
				Order: testCase,
				Options: &pb.PictureListOptions{
					PictureModerVote: &pb.PictureModerVoteListOptions{},
					PictureItem:      &pb.PictureItemListOptions{},
					DfDistance:       &pb.DfDistanceListOptions{},
				},
			}

			_, err := client.GetPictures(
				metadata.AppendToOutgoingContext(
					ctx,
					authn.AuthorizationHeader,
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

	client := pb.NewPicturesClient(conn)

	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	request := pb.PicturesRequest{
		Fields: &pb.PictureFields{
			NameText:      true,
			NameHtml:      true,
			Image:         true,
			ThumbMedium:   true,
			Views:         true,
			Votes:         true,
			CommentsCount: true,
			ModerVote:     true,
			PictureItem:   &pb.PictureItemsRequest{},
		},
		Limit: 100,
		Options: &pb.PictureListOptions{
			PictureModerVote: &pb.PictureModerVoteListOptions{},
			PictureItem:      &pb.PictureItemListOptions{},
			DfDistance:       &pb.DfDistanceListOptions{},
			Statuses: []pb.PictureStatus{
				pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
				pb.PictureStatus_PICTURE_STATUS_INBOX,
				pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			OwnerId:               123,
			AcceptedInDays:        3,
			CreateDate:            &date.Date{Year: 2025, Month: 1, Day: 1},
			AcceptDate:            &date.Date{Year: 2025, Month: 1, Day: 1},
			AddedFrom:             &date.Date{Year: 2025, Month: 1, Day: 1},
			CommentTopic:          &pb.CommentTopicListOptions{MessagesGtZero: true},
			HasNoComments:         true,
			HasPoint:              true,
			HasNoPoint:            true,
			HasNoPictureItem:      true,
			ReplacePicture:        &pb.PictureListOptions{},
			HasNoReplacePicture:   true,
			HasNoPictureModerVote: true,
			HasSpecialName:        true,
			HasCopyrights:         true,
			HasNoCopyrights:       true,
		},
	}

	_, err = client.GetPictures(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&request,
	)
	require.NoError(t, err)
}

func TestGetPictureIP(t *testing.T) {
	t.Parallel()

	cfg := cnt.Config()

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

			var pgIP schema.NullInet

			err := pgIP.Scan(ip.String())
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

			client := pb.NewPicturesClient(conn)

			picture, err := client.GetPicture(
				metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
				&pb.PicturesRequest{
					Options: &pb.PictureListOptions{Id: pictureID},
				},
			)
			require.NoError(t, err)
			require.Equal(t, ip.String(), picture.GetIpAddress())
		})
	}
}

func TestInbox(t *testing.T) {
	t.Parallel()

	cfg := cnt.Config()

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	ctx := t.Context()
	kc := cnt.Keycloak()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	identity := "t" + strconv.Itoa(int(random.Uint32()%100000))

	var pgIP schema.NullInet

	err = pgIP.Scan(net.IPv4allrouter.String())
	require.NoError(t, err)

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	authCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken)

	// GetInbox computes "current date" in the requesting user's profile timezone
	// (PicturesGRPCServer.resolveTimezone), not the test process's local timezone —
	// those can differ (the seeded admin user is Europe/London, the test runner may be
	// anything), which made this test flaky around timezone/day boundaries. Resolve
	// `now`/`yesterday` in that same timezone so the comparisons below are apples-to-apples.
	usersClient := pb.NewUsersClient(conn)
	me, err := usersClient.Me(authCtx, &pb.MeRequest{Fields: &pb.UserFields{Timezone: true}})
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

	client := pb.NewPicturesClient(conn)

	resp, err := client.GetInbox(
		authCtx,
		&pb.InboxRequest{
			Language: "en",
		},
	)
	require.NoError(t, err)
	require.EqualValues(t, now.Day(), resp.GetCurrentDate().GetDay())
	require.EqualValues(t, now.Month(), resp.GetCurrentDate().GetMonth())
	require.EqualValues(t, now.Year(), resp.GetCurrentDate().GetYear())

	resp, err = client.GetInbox(
		authCtx,
		&pb.InboxRequest{
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
		&pb.InboxRequest{
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
		&pb.InboxRequest{
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

	cfg := cnt.Config()
	ctx := t.Context()
	kc := cnt.Keycloak()
	client := pb.NewPicturesClient(conn)

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := createPicture(
		t,
		"../testdata/images/test.jpg",
		pictureshttp.PicturePostForm{ItemID: itemID},
		token.AccessToken,
	)

	_, err = client.UpdatePicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	_, err = client.GetNewbox(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.NewboxRequest{
			Language: "en",
		},
	)
	require.NoError(t, err)
}

func TestInboxCount(t *testing.T) {
	t.Parallel()

	cfg := cnt.Config()

	ctx := t.Context()
	kc := cnt.Keycloak()

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := pb.NewPicturesClient(conn)

	res, err := client.GetPicturesPaginator(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PicturesRequest{
			Options: &pb.PictureListOptions{
				Status: pb.PictureStatus_PICTURE_STATUS_INBOX,
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
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := pb.NewPicturesClient(conn)
	itemsClient := pb.NewItemsClient(conn)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	vehicleName := fmt.Sprintf("Toyota %d Corolla", randomInt)
	vehicleID := createItem(t, &pb.Item{
		Name:       vehicleName,
		IsGroup:    false,
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: vehicleID},
		pb.PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)

	newName := fmt.Sprintf("Toyota %d Corolla New", randomInt)
	_, err = itemsClient.UpdateItem(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.UpdateItemRequest{
			Item:       &pb.Item{Id: vehicleID, Name: newName},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"name"}},
		},
	)
	require.NoError(t, err)

	_, err = client.CorrectFileNames(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	picture, err := client.GetPicture(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PicturesRequest{
			Options: &pb.PictureListOptions{Id: pictureID},
			Fields:  &pb.PictureFields{Image: true},
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
	brandID := createItem(t, &pb.Item{
		Name:       brandName,
		IsGroup:    true,
		ItemTypeId: pb.ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("toyota-%d", randomInt),
	})

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.ItemParent{
			ItemId: vehicleID, ParentId: brandID, Type: pb.ItemParentType_ITEM_TYPE_DEFAULT, Catname: "corolla",
		},
	)
	require.NoError(t, err)

	_, err = client.CorrectFileNames(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	picture, err = client.GetPicture(ctx, &pb.PicturesRequest{
		Options: &pb.PictureListOptions{Id: pictureID},
		Fields:  &pb.PictureFields{Image: true},
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
	brand2ID := createItem(t, &pb.Item{
		Name:       brand2Name,
		IsGroup:    true,
		ItemTypeId: pb.ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("peugeot-%d", randomInt),
	})

	_, err = itemsClient.CreateItemParent(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.ItemParent{
			ItemId: vehicleID, ParentId: brand2ID, Type: pb.ItemParentType_ITEM_TYPE_DEFAULT, Catname: "corolla",
		},
	)
	require.NoError(t, err)

	_, err = client.CorrectFileNames(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.PictureIDRequest{Id: pictureID},
	)
	require.NoError(t, err)

	picture, err = client.GetPicture(ctx, &pb.PicturesRequest{
		Options: &pb.PictureListOptions{Id: pictureID},
		Fields:  &pb.PictureFields{Image: true},
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
	client := pb.NewPicturesClient(conn)
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	addPicture(
		t,
		"../testdata/images/test.jpg",
		pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX,
		token.AccessToken,
	)

	_, err = client.GetGallery(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.GalleryRequest{
			Request: &pb.PicturesRequest{
				Options: &pb.PictureListOptions{
					PictureItem: &pb.PictureItemListOptions{
						ItemId: itemID,
					},
				},
				Fields: &pb.PictureFields{
					NameText:         true,
					NameHtml:         true,
					Image:            true,
					ThumbMedium:      true,
					Views:            true,
					Votes:            true,
					CommentsCount:    true,
					ModerVote:        true,
					PictureItem:      &pb.PictureItemsRequest{},
					DfDistance:       &pb.DfDistanceRequest{},
					ImageGalleryFull: true,
					Path:             &pb.PicturePathRequest{},
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

	grpcClient := pb.NewPicturesClient(conn)

	cfg := cnt.Config()

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
			authn.AuthorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestGetPerspectivePages(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	grpcClient := pb.NewPicturesClient(conn)

	cfg := cnt.Config()

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
			authn.AuthorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestGetPersonPictures(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := pb.NewPicturesClient(conn)
	cfg := cnt.Config()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	randomInt := random.Int()

	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("person-%d", randomInt),
		ItemTypeId: pb.ItemType_ITEM_TYPE_PERSON,
	})

	createPicture(
		t,
		"../testdata/images/test.jpg",
		pictureshttp.PicturePostForm{ItemID: itemID},
		token.AccessToken,
	)

	_, err = client.GetPictures(metadata.AppendToOutgoingContext(
		ctx,
		authn.AuthorizationHeader,
		bearerPrefix+token.AccessToken,
	), &pb.PicturesRequest{
		Fields: &pb.PictureFields{
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
		Options: &pb.PictureListOptions{
			PictureItem: &pb.PictureItemListOptions{
				ItemId: itemID,
				TypeId: pb.PictureItemType_PICTURE_ITEM_AUTHOR,
			},
			Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
		},
		Order:     pb.PicturesRequest_ORDER_LIKES,
		Page:      1,
		Paginator: true,
	})
	require.NoError(t, err)
}

func TestGetCanonicalRouteHidesRemovingFromAnonymous(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	kc := cnt.Keycloak()

	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	goquDB, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
		pb.PictureStatus_PICTURE_STATUS_INBOX, adminToken.AccessToken)

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

	client := pb.NewPicturesClient(conn)

	// Anonymous (every SSR render) must get NOT_FOUND, not a redirect to a URL that then 404s.
	_, err = client.GetCanonicalRoute(ctx, &pb.CanonicalRouteRequest{Identity: identity})
	require.ErrorContains(t, err, "NotFound")

	// A moderator still gets the route.
	_, err = client.GetCanonicalRoute(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken.AccessToken),
		&pb.CanonicalRouteRequest{Identity: identity},
	)
	require.NoError(t, err)
}
