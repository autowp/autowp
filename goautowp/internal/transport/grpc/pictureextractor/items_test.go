package pictureextractor

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/pictureitemextractor"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/lib/pq"
	"github.com/stretchr/testify/require"
)

func TestPictureItemsBatchGroupingAndFilters(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../../../..")
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
		"", config.DuplicateFinderConfig{}, nil, nil, nil, nil)
	extractor := New(Dependencies{
		PicturesRepository: func(_ context.Context) (*pictures.Repository, error) { return repository, nil },
		PictureItemExtractor: func() *pictureitemextractor.Extractor {
			return pictureitemextractor.New(nil, nil, nil, nil, nil)
		},
	})
	request := &pb.PictureItemsRequest{
		Options: &pb.PictureItemListOptions{PictureId: 999, TypeId: pb.PictureItemType_PICTURE_ITEM_CONTENT},
		Order:   pb.PictureItemsRequest_FRONT_PERSPECTIVES_FIRST,
	}
	grouped, err := extractor.preloadPictureItems(t.Context(), []int64{2, 1, 3}, request, "en", authn.User{})
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
	filtered, err := extractor.preloadPictureItems(t.Context(), []int64{1, 2, 3}, request, "en", authn.User{})
	require.NoError(t, err)
	require.Len(t, filtered[1], 1)
	require.Len(t, filtered[2], 1)
	require.EqualValues(t, 10, filtered[1][0].GetItemId())
	require.EqualValues(t, 20, filtered[2][0].GetItemId())
	empty, err := (&Extractor{}).preloadPictureItems(t.Context(), nil, request, "en", authn.User{})
	require.NoError(t, err)
	require.Empty(t, empty)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = extractor.preloadPictureItems(ctx, []int64{1}, request, "en", authn.User{})
	require.ErrorIs(t, err, context.Canceled)
}
