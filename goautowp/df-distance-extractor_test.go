package goautowp

import (
	"context"
	"testing"
	"time"

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
	serialCtx := context.WithValue(t.Context(), pictureExtractionParallelismKey{}, 1)
	expected, err := extractor.ExtractRows(serialCtx, rows, fields, "en", UserContext{})
	require.NoError(t, err)

	for _, limit := range []int{4, 2} {
		ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
		ctx = context.WithValue(ctx, pictureExtractionParallelismKey{}, limit)
		logger := &itemBatchQueryLogger{}
		unblock := blockItemExtractionQueries(t, logger, limit)
		database.Logger(logger)

		done := make(chan error, 1)

		var actual []*DfDistance

		go func() {
			var extractErr error

			actual, extractErr = extractor.ExtractRows(ctx, rows, fields, "en", UserContext{})
			done <- extractErr
		}()

		unblock()
		require.NoError(t, <-done)
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

	started := make(chan struct{}, 4)

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

	for range 4 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("distance rows did not issue SQL together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
}
