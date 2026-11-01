package itemextractor

import (
	"context"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestItemOfDayPicturesConcurrentReads(t *testing.T) {
	t.Parallel()

	extractor, row, database, _ := newItemOfDayTestExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	data, lookups, err := extractor.prepareItemOfDayPictures(ctx, row, "en")
	require.NoError(t, err)

	gate := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, gate, 3)

	database.Logger(&itemBatchQueryLogger{beforeQuery: func() {
		select {
		case <-data.picturesReady:
			gate.Printf("")
		default:
		}
	}})

	done := make(chan error, 1)
	go func() { done <- extraction.Run(ctx, lookups) }()

	// Selection must finish first, then both image formats and names can issue
	// SQL together. Waiting for selection must not consume the whole field queue.
	unblock()
	require.NoError(t, <-done)

	actual, err := data.extract(row, "en")
	require.NoError(t, err)
	require.Len(t, actual, 2)

	for _, picture := range actual {
		require.NotEmpty(t, picture.GetName())
		require.NotEmpty(t, picture.GetRoute())
		require.NotEmpty(t, picture.GetThumb().GetSrc())
	}

	require.NotEqual(t, actual[0].GetThumb().GetWidth(), actual[1].GetThumb().GetWidth())

	fields := &pb.ItemFields{ItemOfDayPictures: true}

	for _, language := range []string{"en", "ru"} {
		inlineCtx := extraction.WithInlineLookups(ctx)
		expected, err := extractor.extractItemOfDayPictures(inlineCtx, fields, row, language)
		require.NoError(t, err)

		result := &pb.Item{}
		require.NoError(t, extractor.extractPlain(ctx, fields, row, result, language, authn.User{}))
		require.Len(t, result.GetItemOfDayPictures(), len(expected))

		for index := range expected {
			require.True(t, proto.Equal(expected[index], result.GetItemOfDayPictures()[index]))

			if language == "en" {
				require.True(t, proto.Equal(expected[index], actual[index]))
			}
		}
	}
}

func TestItemOfDayPicturesCancellationDuringSelection(t *testing.T) {
	t.Parallel()

	extractor, row, database, connectionPool := newItemOfDayTestExtractor(t)

	parentCtx, timeout := context.WithTimeout(t.Context(), 5*time.Second)
	defer timeout()

	ctx, cancel := context.WithCancel(parentCtx)
	defer cancel()

	data, lookups, err := extractor.prepareItemOfDayPictures(ctx, row, "en")
	require.NoError(t, err)
	connectionPool.SetMaxOpenConns(1)
	held, err := connectionPool.Conn(ctx)
	require.NoError(t, err)

	defer held.Close()

	started := make(chan struct{}, 2)

	database.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

	done := make(chan error, 1)
	go func() { done <- extraction.Run(ctx, lookups) }()

	for range 2 {
		select {
		case <-started:
		case <-parentCtx.Done():
			t.Fatal("path and selection queries did not start")
		}
	}

	cancel()

	select {
	case err := <-done:
		require.ErrorIs(t, err, context.Canceled)
	case <-parentCtx.Done():
		t.Fatal("dependent lookups failed to exit after selection was canceled")
	}

	require.NoError(t, parentCtx.Err())
	require.Equal(t, 1, connectionPool.Stats().InUse, "only the held connection remains")

	select {
	case <-data.picturesReady:
		t.Fatal("failed selection must not publish partial pictures")
	default:
	}
}

func TestItemOfDayPicturesUnselected(t *testing.T) {
	t.Parallel()

	pictures, err := (&Extractor{}).extractItemOfDayPictures(t.Context(), nil, &items.Item{}, "en")
	require.NoError(t, err)
	require.Nil(t, pictures)
}
