package pictureextractor_test

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestDfDistanceBatchPreservesFilters(t *testing.T) {
	t.Parallel()
	fixture, db, pool, _ := newRemainingPictureExtractor(t)
	extractor, err := fixture.dependencies.DfDistanceExtractor(t.Context())
	require.NoError(t, err)

	rows := []*schema.DfDistanceRow{
		{SrcPictureID: 7, DstPictureID: 3, Distance: 12},
		{SrcPictureID: 5, DstPictureID: 1, Distance: 20},
		{SrcPictureID: 9, DstPictureID: 3, Distance: 30},
		{SrcPictureID: 11, DstPictureID: 0},
	}

	for _, options := range []*pb.PictureListOptions{
		nil, {Id: 999, Ids: []int64{1, 3, 5}, OwnerId: 7},
	} {
		fields := &pb.DfDistanceFields{
			DstPicture: &pb.PicturesRequest{
				Options: options,
				Fields:  &pb.PictureFields{Rights: true, Subscribed: true},
			},
		}
		original := proto.Clone(fields)
		logger := &testutil.QueryLogger{}
		db.Logger(logger)

		result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", authn.User{UserID: 7})
		require.NoError(t, err)
		db.Logger(nil)
		require.EqualValues(t, 3, logger.Queries.Load(), "one picture selection and two nested field batches")
		require.Len(t, result, len(rows))

		for index, row := range rows {
			require.Equal(t, row.SrcPictureID, result[index].GetSrcPictureId())
			require.Equal(t, row.DstPictureID, result[index].GetDstPictureId())

			if row.DstPictureID == 0 {
				require.Nil(t, result[index].GetDstPicture())

				continue
			}

			require.Equal(t, row.DstPictureID, result[index].GetDstPicture().GetId())
		}

		require.True(t, proto.Equal(original, fields))
	}

	for _, options := range []*pb.PictureListOptions{
		{Ids: []int64{3}},
		{OwnerId: 8},
		{Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED},
		{Identity: "one"},
	} {
		result, err := extractor.ExtractRows(
			t.Context(),
			rows,
			&pb.DfDistanceFields{DstPicture: &pb.PicturesRequest{Options: options}},
			"en",
			authn.User{},
		)
		require.ErrorIs(t, err, sql.ErrNoRows)
		require.Nil(t, result)
	}

	result, err := extractor.ExtractRows(t.Context(), []*schema.DfDistanceRow{{DstPictureID: 999}},
		&pb.DfDistanceFields{DstPicture: &pb.PicturesRequest{}}, "en", authn.User{})
	require.ErrorIs(t, err, sql.ErrNoRows)
	require.Nil(t, result)
	repository, err := fixture.dependencies.PicturesRepository(t.Context())
	require.NoError(t, err)

	options := &query.PictureListOptions{ID: 999, IDs: []int64{1, 3, 5}, Limit: 1}
	selected, err := repository.PicturesByIDs(
		t.Context(),
		[]int64{1, 3},
		options,
		nil,
		picturemapper.OrderFromProto(pb.PicturesRequest_ORDER_NONE),
	)
	require.NoError(t, err)
	require.Len(t, selected, 2, "the per-picture limit must not truncate the shared batch")
	require.Equal(t, &query.PictureListOptions{ID: 999, IDs: []int64{1, 3, 5}, Limit: 1}, options)

	for _, filter := range []*query.PictureListOptions{{ExcludeID: 1}, {IDGt: 1}, {IDLt: 3}, {IDs: []int64{3}}} {
		selected, err := repository.PicturesByIDs(
			t.Context(),
			[]int64{1, 3},
			filter,
			nil,
			picturemapper.OrderFromProto(pb.PicturesRequest_ORDER_NONE),
		)
		require.NoError(t, err)
		require.Len(t, selected, 1)
	}
	// SQL errors from the shared picture selection still fail the whole request.
	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.picture RENAME COLUMN identity TO broken")
	require.NoError(t, err)
	result, err = extractor.ExtractRows(
		t.Context(),
		rows,
		&pb.DfDistanceFields{DstPicture: &pb.PicturesRequest{}},
		"en",
		authn.User{},
	)
	require.Error(t, err)
	require.Nil(t, result)
	_, singleErr := repository.Picture(
		t.Context(),
		&query.PictureListOptions{ID: 1},
		nil,
		picturemapper.OrderFromProto(pb.PicturesRequest_ORDER_NONE),
	)
	require.EqualError(t, err, singleErr.Error())
}
