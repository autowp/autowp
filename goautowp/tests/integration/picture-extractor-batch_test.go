package integration

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/schema"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func TestPictureBatchExtractionWithNestedFields(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := cnt.Config()
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	authCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken)
	me, err := pb.NewUsersClient(conn).Me(authCtx, &pb.MeRequest{})
	require.NoError(t, err)
	db, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	itemByPicture := make(map[int64]int64)
	ids := make([]int64, 0, 2)

	for index := range 2 {
		itemID := createItem(t, &pb.Item{
			Name: "picture-batch-" + uuid.NewString(), ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE,
		})
		pictureID := addPicture(t, "../testdata/images/test.jpg", pictureshttp.PicturePostForm{ItemID: itemID},
			pb.PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)
		itemByPicture[pictureID] = itemID
		ids = append(ids, pictureID)
		_, err = db.Insert(schema.PictureModerVoteTable).Rows(goqu.Record{
			schema.PictureModerVoteTablePictureIDColName: pictureID,
			schema.PictureModerVoteTableUserIDColName:    me.GetId(),
			schema.PictureModerVoteTableVoteColName:      1 - index,
			schema.PictureModerVoteTableReasonColName:    "batch extraction test",
			schema.PictureModerVoteTableDayDateColName:   goqu.Func("NOW"),
		}).Executor().ExecContext(ctx)
		require.NoError(t, err)
	}

	response, err := pb.NewPicturesClient(conn).GetPictures(authCtx, &pb.PicturesRequest{
		Language: "en", Options: &pb.PictureListOptions{Ids: ids},
		Fields: &pb.PictureFields{
			ModerVote: true, ModerVoted: true,
			PictureModerVotes: &pb.PictureModerVoteRequest{Options: &pb.PictureModerVoteListOptions{VoteGtZero: true}},
			PictureItem: &pb.PictureItemsRequest{
				Options: &pb.PictureItemListOptions{TypeId: pb.PictureItemType_PICTURE_ITEM_CONTENT},
				Fields: &pb.PictureItemFields{
					Item:    &pb.ItemsRequest{Fields: &pb.ItemFields{NameText: true}},
					Picture: &pb.PicturesRequest{Fields: &pb.PictureFields{ModerVote: true}},
				},
			},
		},
	})
	require.NoError(t, err)
	require.Len(t, response.GetItems(), 2)

	for _, picture := range response.GetItems() {
		require.EqualValues(t, 1, picture.GetModerVoteCount())
		require.True(t, picture.GetModerVoted())

		if picture.GetId() == ids[0] {
			require.EqualValues(t, 1, picture.GetModerVoteVote())
			require.Len(t, picture.GetPictureModerVotes().GetItems(), 1)
		} else {
			require.EqualValues(t, -1, picture.GetModerVoteVote())
			require.Empty(t, picture.GetPictureModerVotes().GetItems())
		}

		relations := picture.GetPictureItems().GetItems()
		require.Len(t, relations, 1)
		require.Equal(t, itemByPicture[picture.GetId()], relations[0].GetItem().GetId())
		require.NotEmpty(t, relations[0].GetItem().GetNameText())
		require.Equal(t, picture.GetId(), relations[0].GetPicture().GetId())
		require.Equal(t, picture.GetModerVoteVote(), relations[0].GetPicture().GetModerVoteVote())
	}

	flags, err := pb.NewPicturesClient(conn).GetPictures(authCtx, &pb.PicturesRequest{
		Options: &pb.PictureListOptions{Ids: ids}, Fields: &pb.PictureFields{ModerVoted: true},
	})
	require.NoError(t, err)
	require.Len(t, flags.GetItems(), 2)

	for _, picture := range flags.GetItems() {
		require.True(t, picture.GetModerVoted())
		require.Zero(t, picture.GetModerVoteCount())
	}
}
