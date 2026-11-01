package pictures

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestNewboxPictureBatchesMatchIndividualGroups(t *testing.T) {
	t.Parallel()

	fixture, repository, db, pool, rows := newNewboxTestExtractor(t)
	for id := int64(20); id < 29; id++ {
		_, err := pool.ExecContext(
			t.Context(),
			`INSERT INTO pg_temp.picture(id,identity,status,
 width,height,created_at,license_id)
 VALUES($1,'newbox','accepted',1,1,'2020-01-01',0);`,
			id,
		)
		require.NoError(t, err)
		_, err = pool.ExecContext(t.Context(), "INSERT INTO pg_temp.picture_item VALUES($1,200,1)", id)
		require.NoError(t, err)
	}

	all, _, err := repository.Pictures(t.Context(), &query.PictureListOptions{}, nil, pictures.OrderByIDAsc, false)
	require.NoError(t, err)

	groups := []*NewboxGroupDraft{
		{Type: newboxGroupTypeItem, ItemID: 200, Pictures: all},
		{Type: newboxGroupTypePictures, Pictures: rows[:2]},
		{Type: newboxGroupTypeItem, ItemID: 300, Pictures: all},
		{Type: newboxGroupTypeItem, ItemID: 200, Pictures: all},
		{Type: newboxGroupTypePictures, Pictures: []*schema.PictureRow{}},
		{Type: newboxGroupTypeItem, ItemID: 999, Pictures: all},
		{Type: newboxGroupTypeItem, ItemID: 200},
	}
	server := &Server{repository: repository, pictureExtractor: fixture}
	itemFields := &pb.PictureFields{Subscribed: true}
	looseFields := &pb.PictureFields{Rights: true}
	itemOriginal, looseOriginal := proto.Clone(itemFields), proto.Clone(looseFields)
	expected := make(map[int64][]*pb.Picture)
	individual := &itemBatchQueryLogger{}
	db.Logger(individual)

	for index, group := range groups {
		source := group.Pictures

		fields := looseFields
		if group.Type == newboxGroupTypeItem {
			fields = itemFields

			ids := make([]int64, 0, len(source))
			for _, row := range source {
				ids = append(ids, row.ID)
			}

			source, _, err = repository.Pictures(t.Context(), &query.PictureListOptions{
				IDs: ids, Status: schema.PictureStatusAccepted,
				PictureItem: &query.PictureItemListOptions{ItemID: group.ItemID}, Limit: newboxPicturesPerLine,
			}, picturemapper.FieldsFromProto(fields), pictures.OrderByAcceptDatetimeDesc, false)
			require.NoError(t, err)
		}

		expected[int64(index)], err = fixture.ExtractRows(t.Context(), source, fields, "en", authn.User{UserID: 7})
		require.NoError(t, err)
	}

	db.Logger(nil)

	for _, budget := range []int{4, 1} {
		ctx := extraction.WithPictureParallelism(t.Context(), budget)
		logger := &itemBatchQueryLogger{}
		db.Logger(logger)

		actual, err := server.extractNewboxPictures(ctx, groups, looseFields, itemFields, "en", authn.User{UserID: 7})
		require.NoError(t, err)
		db.Logger(nil)
		require.EqualValues(t, 3, logger.queries.Load())
		require.Less(t, logger.queries.Load(), individual.queries.Load())

		for key, want := range expected {
			require.True(t, proto.Equal(&pb.PicturesList{Items: want}, &pb.PicturesList{Items: actual[key]}))
		}

		require.Len(t, actual[0], newboxPicturesPerLine)
		require.Len(t, actual[2], 1)
		require.NotNil(t, actual[4])
		require.Empty(t, actual[4])
		require.NotNil(t, actual[5])
		require.Empty(t, actual[5])
	}

	require.True(t, proto.Equal(itemOriginal, itemFields))
	require.True(t, proto.Equal(looseOriginal, looseFields))
	empty, err := server.extractNewboxPictures(t.Context(), nil, looseFields, itemFields, "en", authn.User{})
	require.NoError(t, err)
	require.Empty(t, empty)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = server.extractNewboxPictures(ctx, groups, looseFields, itemFields, "en", authn.User{UserID: 7})
	require.ErrorIs(t, err, context.Canceled)
}
