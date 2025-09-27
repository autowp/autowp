package goautowp

import (
	"fmt"
	"math/rand"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestLikesRating(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewRatingClient(conn)

	r, err := client.GetUserCommentsRating(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	for _, item := range r.GetUsers() {
		_, err = client.GetUserCommentsRatingFans(
			ctx,
			&UserRatingDetailsRequest{UserId: item.GetUserId()},
		)
		require.NoError(t, err)
	}
}

func TestPictureLikesRating(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	picturesClient := NewPicturesClient(conn)

	// tester
	testerToken, err := kc.Login(
		ctx,
		"frontend",
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	// admin
	adminToken, err := kc.Login(
		ctx,
		"frontend",
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	itemID := createItem(t, conn, cnt, &APIItem{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := CreatePicture(
		t,
		cnt,
		"./test/test.jpg",
		PicturePostForm{ItemID: itemID},
		testerToken.AccessToken,
	)

	_, err = picturesClient.SetPictureStatus(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&SetPictureStatusRequest{
			Id:     pictureID,
			Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
		},
	)
	require.NoError(t, err)

	_, err = picturesClient.Vote(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&PicturesVoteRequest{PictureId: pictureID, Value: 1},
	)
	require.NoError(t, err)

	client := NewRatingClient(conn)

	r, err := client.GetUserPictureLikesRating(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	for _, item := range r.GetUsers() {
		_, err = client.GetUserPictureLikesRatingFans(
			ctx,
			&UserRatingDetailsRequest{UserId: item.GetUserId()},
		)
		require.NoError(t, err)
	}
}

func TestPicturesRating(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewRatingClient(conn)

	r, err := client.GetUserPicturesRating(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	for _, item := range r.GetUsers() {
		_, err = client.GetUserPicturesRatingBrands(ctx, &UserRatingDetailsRequest{
			UserId:   item.GetUserId(),
			Language: "en",
		})
		require.NoError(t, err)
	}
}

func TestSpecsRating(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewRatingClient(conn)

	r, err := client.GetUserSpecsRating(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	for _, item := range r.GetUsers() {
		_, err = client.GetUserSpecsRatingBrands(
			ctx,
			&UserRatingDetailsRequest{UserId: item.GetUserId()},
		)
		require.NoError(t, err)
	}
}
