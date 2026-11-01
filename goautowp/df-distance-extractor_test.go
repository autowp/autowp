package goautowp

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

var errUnexpectedExtractedDistances = errors.New("unexpected extracted distances")

func TestDfDistanceExtractorConcurrentRows(t *testing.T) {
	t.Parallel()

	itemExtractor, vehicle, database, pool := newItemOfDayTestExtractor(t)
	pictureRepository, err := itemExtractor.container.PicturesRepository(t.Context())
	require.NoError(t, err)
	links, err := pictureRepository.PictureItems(t.Context(), &query.PictureItemListOptions{ItemID: vehicle.ID}, 0, 0)
	require.NoError(t, err)

	ids := make([]int64, 0, len(links))
	for _, link := range links {
		ids = append(ids, link.PictureID)
	}

	require.Len(t, ids, 2)

	extractor, err := itemExtractor.container.DfDistanceExtractor(t.Context())
	require.NoError(t, err)

	rows := make([]*schema.DfDistanceRow, 1, 9)

	rows[0] = &schema.DfDistanceRow{SrcPictureID: 10}
	for index := range 8 {
		rows = append(rows, &schema.DfDistanceRow{
			SrcPictureID: int64(index + 20), DstPictureID: ids[index%len(ids)], Distance: int64(index),
		})
	}

	fields := &pb.DfDistanceFields{DstPicture: &pb.PicturesRequest{
		Fields: &pb.PictureFields{ThumbMedium: true, NameText: true},
	}}
	original := proto.Clone(fields)
	// Retain the original per-distance reads as the reference.
	repository, err := itemExtractor.container.PicturesRepository(t.Context())
	require.NoError(t, err)

	individualQueries := &itemBatchQueryLogger{}
	database.Logger(individualQueries)

	expected := make([]*pb.DfDistance, len(rows))
	for index, row := range rows {
		expected[index] = &pb.DfDistance{
			SrcPictureId: row.SrcPictureID, DstPictureId: row.DstPictureID,
			Distance: int32(row.Distance), //nolint:gosec
		}
		if row.DstPictureID == 0 {
			continue
		}

		picture, err := repository.Picture(
			t.Context(),
			&query.PictureListOptions{ID: row.DstPictureID},
			picturemapper.FieldsFromProto(
				fields.GetDstPicture().GetFields(),
			),
			picturemapper.OrderFromProto(fields.GetDstPicture().GetOrder()),
		)
		require.NoError(t, err)
		expected[index].DstPicture, err = itemExtractor.container.PictureExtractor().Extract(
			t.Context(), picture, fields.GetDstPicture().GetFields(), "en", authn.User{})
		require.NoError(t, err)
	}

	database.Logger(nil)

	for _, limit := range []int{4, 2} {
		ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
		ctx = extraction.WithPictureParallelism(ctx, limit)
		logger := &itemBatchQueryLogger{}
		database.Logger(logger)

		done := make(chan error, 1)

		var actual []*pb.DfDistance

		go func() {
			var extractErr error

			actual, extractErr = extractor.ExtractRows(ctx, rows, fields, "en", authn.User{})
			done <- extractErr
		}()

		require.NoError(t, <-done)
		require.Less(t, logger.queries.Load(), individualQueries.queries.Load())
		cancel()
		require.Len(t, actual, len(expected))

		for index := range expected {
			require.True(t, proto.Equal(expected[index], actual[index]), "slot %d", index)
		}
	}

	require.True(t, proto.Equal(original, fields))
	require.Nil(t, expected[0].GetDstPicture())
	require.NotEmpty(t, expected[1].GetDstPicture().GetThumbMedium().GetSrc())

	database.Logger(nil)

	withoutPictures, err := extractor.ExtractRows(t.Context(), rows, nil, "en", authn.User{})
	require.NoError(t, err)

	for index, row := range withoutPictures {
		require.Nil(t, row.GetDstPicture())
		require.Equal(t, rows[index].DstPictureID, row.GetDstPictureId())
	}

	empty, err := extractor.ExtractRows(t.Context(), nil, fields, "en", authn.User{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)

	failed, err := extractor.ExtractRows(
		t.Context(), []*schema.DfDistanceRow{{DstPictureID: -1}}, fields, "en", authn.User{},
	)
	require.Error(t, err)
	require.Nil(t, failed)

	// Hold the only connection so cancellation interrupts in-flight SQL reads.
	pool.SetMaxOpenConns(1)
	connection, err := pool.Conn(t.Context())
	require.NoError(t, err)

	defer connection.Close()

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	started := make(chan struct{}, 1)

	database.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

	done := make(chan error, 1)

	go func() {
		result, extractErr := extractor.ExtractRows(ctx, rows, fields, "en", authn.User{})
		if result != nil {
			done <- errUnexpectedExtractedDistances

			return
		}

		done <- extractErr
	}()

	for range 1 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("batched picture read did not start")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
}
