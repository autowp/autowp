package goautowp

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func TestPictureItemsBatchGroupingAndFilters(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, db.Close()) })
	_, err = db.ExecContext(t.Context(), `CREATE TEMP TABLE picture_item (
		picture_id bigint, item_id bigint, type integer,
		crop_left integer, crop_top integer, crop_width integer, crop_height integer,
		perspective_id integer, add_user_id bigint, perspective_user_id bigint,
		created_at timestamptz NOT NULL DEFAULT NOW()
	)`)
	require.NoError(t, err)
	_, err = db.ExecContext(t.Context(), `INSERT INTO picture_item
		(picture_id, item_id, type, perspective_id, crop_width, crop_height) VALUES
		(1, 11, 1, $1, 0, 0), (1, 10, 1, $2, 10, 20), (1, 12, 2, NULL, 0, 0),
		(2, 21, 1, $1, 0, 0), (2, 20, 1, $2, 10, 20), (99, 99, 1, $2, 10, 20)`,
		schema.PerspectiveBack, schema.PerspectiveFront)
	require.NoError(t, err)

	repository := pictures.NewRepository(goqu.New("postgres", db), nil, nil, nil, nil,
		config.DuplicateFinderConfig{}, nil, nil, nil, nil)
	extractor := NewPictureExtractor(&Container{picturesRepository: repository})
	request := &PictureItemsRequest{
		Options: &PictureItemListOptions{PictureId: 999, TypeId: PictureItemType_PICTURE_ITEM_CONTENT},
		Order:   PictureItemsRequest_FRONT_PERSPECTIVES_FIRST,
	}
	grouped, err := extractor.preloadPictureItems(t.Context(), []int64{2, 1, 3}, request, "en", UserContext{})
	require.NoError(t, err)
	require.Len(t, grouped, 3)
	require.Empty(t, grouped[3])
	require.NotNil(t, grouped[3])
	require.EqualValues(t, 999, request.GetOptions().GetPictureId())

	for _, id := range []int64{1, 2} {
		individual, err := repository.PictureItems(t.Context(), &query.PictureItemListOptions{
			PictureID: id, TypeID: schema.PictureItemTypeContent,
		}, pictures.PictureItemOrderByFrontPerspectivesFirst, 0)
		require.NoError(t, err)
		require.Len(t, grouped[id], len(individual))

		for idx, row := range individual {
			require.Equal(t, row.PictureID, grouped[id][idx].GetPictureId())
			require.Equal(t, row.ItemID, grouped[id][idx].GetItemId())
		}
	}

	request.Options.HasArea = true
	filtered, err := extractor.preloadPictureItems(t.Context(), []int64{1, 2, 3}, request, "en", UserContext{})
	require.NoError(t, err)
	require.Len(t, filtered[1], 1)
	require.Len(t, filtered[2], 1)
	require.EqualValues(t, 10, filtered[1][0].GetItemId())
	require.EqualValues(t, 20, filtered[2][0].GetItemId())
	empty, err := (&PictureExtractor{}).preloadPictureItems(t.Context(), nil, request, "en", UserContext{})
	require.NoError(t, err)
	require.Empty(t, empty)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = extractor.preloadPictureItems(ctx, []int64{1}, request, "en", UserContext{})
	require.ErrorIs(t, err, context.Canceled)
}

func TestPictureBatchExtractionWithNestedFields(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	authCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)
	me, err := NewUsersClient(conn).Me(authCtx, &MeRequest{})
	require.NoError(t, err)
	db, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	itemByPicture := make(map[int64]int64)
	ids := make([]int64, 0, 2)

	for index := range 2 {
		itemID := createItem(t, conn, cnt, &Item{
			Name: "picture-batch-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		})
		pictureID := addPicture(t, cnt, conn, "./test/test.jpg", PicturePostForm{ItemID: itemID},
			PictureStatus_PICTURE_STATUS_INBOX, token.AccessToken)
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

	response, err := NewPicturesClient(conn).GetPictures(authCtx, &PicturesRequest{
		Language: "en", Options: &PictureListOptions{Ids: ids},
		Fields: &PictureFields{
			ModerVote: true, ModerVoted: true,
			PictureModerVotes: &PictureModerVoteRequest{Options: &PictureModerVoteListOptions{VoteGtZero: true}},
			PictureItem: &PictureItemsRequest{
				Options: &PictureItemListOptions{TypeId: PictureItemType_PICTURE_ITEM_CONTENT},
				Fields: &PictureItemFields{
					Item:    &ItemsRequest{Fields: &ItemFields{NameText: true}},
					Picture: &PicturesRequest{Fields: &PictureFields{ModerVote: true}},
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

	flags, err := NewPicturesClient(conn).GetPictures(authCtx, &PicturesRequest{
		Options: &PictureListOptions{Ids: ids}, Fields: &PictureFields{ModerVoted: true},
	})
	require.NoError(t, err)
	require.Len(t, flags.GetItems(), 2)

	for _, picture := range flags.GetItems() {
		require.True(t, picture.GetModerVoted())
		require.Zero(t, picture.GetModerVoteCount())
	}
}
