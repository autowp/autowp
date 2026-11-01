package goautowp

import (
	"fmt"
	"math/rand"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/schema"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

func TestLikesRating(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewRatingClient(conn)

	r, err := client.GetUserCommentsRating(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	for _, item := range r.GetUsers() {
		_, err = client.GetUserCommentsRatingFans(
			ctx,
			&pb.UserRatingDetailsRequest{UserId: item.GetUserId()},
		)
		require.NoError(t, err)
	}
}

func TestPictureLikesRating(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	picturesClient := pb.NewPicturesClient(conn)

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
	itemID := createItem(t, conn, cnt, &pb.Item{
		Name:       fmt.Sprintf("vehicle-%d", random.Int()),
		ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
	})

	pictureID := CreatePicture(
		t,
		cnt,
		"./test/test.jpg",
		pictureshttp.PicturePostForm{ItemID: itemID},
		testerToken.AccessToken,
	)

	_, err = picturesClient.UpdatePicture(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.UpdatePictureRequest{
			Picture: &pb.Picture{
				Id:     pictureID,
				Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
			},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	_, err = picturesClient.Vote(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.PicturesVoteRequest{PictureId: pictureID, Value: 1},
	)
	require.NoError(t, err)

	client := pb.NewRatingClient(conn)

	r, err := client.GetUserPictureLikesRating(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	for _, item := range r.GetUsers() {
		_, err = client.GetUserPictureLikesRatingFans(
			ctx,
			&pb.UserRatingDetailsRequest{UserId: item.GetUserId()},
		)
		require.NoError(t, err)
	}
}

func TestPicturesRating(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewRatingClient(conn)

	r, err := client.GetUserPicturesRating(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	for _, item := range r.GetUsers() {
		_, err = client.GetUserPicturesRatingBrands(ctx, &pb.UserRatingDetailsRequest{
			UserId:   item.GetUserId(),
			Language: schema.EnglishLanguageCode,
		})
		require.NoError(t, err)
	}
}

func TestSpecsRating(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewRatingClient(conn)

	r, err := client.GetUserSpecsRating(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	for _, item := range r.GetUsers() {
		_, err = client.GetUserSpecsRatingBrands(
			ctx,
			&pb.UserRatingDetailsRequest{UserId: item.GetUserId()},
		)
		require.NoError(t, err)
	}
}
