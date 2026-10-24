package goautowp

import (
	"context"
	"testing"
	"time"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestDfDistanceExtractorConcurrentRows(t *testing.T) {
	t.Parallel()

	itemExtractor, vehicle, database, pool := newItemOfDayTestExtractor(t)
	pictures, err := itemExtractor.orientedPictureList(t.Context(), vehicle.ID)
	require.NoError(t, err)

	var ids []int64

	for _, picture := range pictures {
		if picture != nil {
			ids = append(ids, picture.ID)
		}
	}

	require.Len(t, ids, 2)

	extractor := NewDfDistanceExtractor(itemExtractor.container)
	rows := make([]*schema.DfDistanceRow, 1, 9)

	rows[0] = &schema.DfDistanceRow{SrcPictureID: 10}
	for index := range 8 {
		rows = append(rows, &schema.DfDistanceRow{
			SrcPictureID: int64(index + 20), DstPictureID: ids[index%len(ids)], Distance: int64(index),
		})
	}

	fields := &DfDistanceFields{DstPicture: &PicturesRequest{
		Fields: &PictureFields{ThumbMedium: true, NameText: true},
	}}
	original := proto.Clone(fields)
	// Retain the original per-distance reads as the reference.
	repository, err := itemExtractor.container.PicturesRepository(t.Context())
	require.NoError(t, err)

	individualQueries := &itemBatchQueryLogger{}
	database.Logger(individualQueries)

	expected := make([]*DfDistance, len(rows))
	for index, row := range rows {
		expected[index] = &DfDistance{
			SrcPictureId: row.SrcPictureID, DstPictureId: row.DstPictureID,
			Distance: int32(row.Distance), //nolint:gosec
		}
		if row.DstPictureID == 0 {
			continue
		}

		picture, err := repository.Picture(
			t.Context(),
			&query.PictureListOptions{ID: row.DstPictureID},
			convertPictureFields(
				fields.GetDstPicture().GetFields(),
			),
			convertPicturesOrder(fields.GetDstPicture().GetOrder()),
		)
		require.NoError(t, err)
		expected[index].DstPicture, err = itemExtractor.container.PictureExtractor().Extract(
			t.Context(), picture, fields.GetDstPicture().GetFields(), "en", UserContext{})
		require.NoError(t, err)
	}

	database.Logger(nil)

	for _, limit := range []int{4, 2} {
		ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
		ctx = context.WithValue(ctx, pictureExtractionParallelismKey{}, limit)
		logger := &itemBatchQueryLogger{}
		database.Logger(logger)

		done := make(chan error, 1)

		var actual []*DfDistance

		go func() {
			var extractErr error

			actual, extractErr = extractor.ExtractRows(ctx, rows, fields, "en", UserContext{})
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

	withoutPictures, err := extractor.ExtractRows(t.Context(), rows, nil, "en", UserContext{})
	require.NoError(t, err)

	for index, row := range withoutPictures {
		require.Nil(t, row.GetDstPicture())
		require.Equal(t, rows[index].DstPictureID, row.GetDstPictureId())
	}

	empty, err := extractor.ExtractRows(t.Context(), nil, fields, "en", UserContext{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)

	failed, err := extractor.ExtractRows(
		t.Context(), []*schema.DfDistanceRow{{DstPictureID: -1}}, fields, "en", UserContext{},
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
		result, extractErr := extractor.ExtractRows(ctx, rows, fields, "en", UserContext{})
		if result != nil {
			done <- errItemNotFound

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
