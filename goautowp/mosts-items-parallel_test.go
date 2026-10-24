package goautowp

import (
	"context"
	"fmt"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/mosts"
	"github.com/autowp/goautowp/query"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func mostsExtractionTestFields() *ItemFields {
	return &ItemFields{
		NameHtml: true, Description: true, Route: true,
		PreviewPictures: &PreviewPicturesRequest{
			PerspectivePageId: 1,
			Pictures: &PicturesRequest{
				Fields: &PictureFields{NameText: true},
				Options: &PictureListOptions{
					PictureItem: &PictureItemListOptions{TypeId: PictureItemType_PICTURE_ITEM_CONTENT},
				},
			},
		},
	}
}

func TestMostsItemsConcurrentExtraction(t *testing.T) {
	t.Parallel()

	extractor, vehicle, db, pool := newItemOfDayTestExtractor(t)
	secondID := createItem(t, conn, cnt, &Item{Name: "Mosts without pictures", ItemTypeId: ItemType_ITEM_TYPE_VEHICLE})
	repository, err := extractor.container.ItemsRepository(t.Context())
	require.NoError(t, err)

	fields := mostsExtractionTestFields()
	requested := proto.Clone(fields)
	first, err := repository.Item(t.Context(), &query.ItemListOptions{ItemID: vehicle.ID, Language: "en"},
		convertItemFields(fields))
	require.NoError(t, err)
	second, err := repository.Item(t.Context(), &query.ItemListOptions{ItemID: secondID, Language: "en"},
		convertItemFields(fields))
	require.NoError(t, err)

	list := make([]mosts.ResultItem, 7)
	for index := range list {
		row := first
		if index%2 != 0 {
			row = second
		}

		list[index] = mosts.ResultItem{Item: row, ValueHTML: fmt.Sprintf("%d <span>km/h</span>", 100-index)}
	}

	server := &MostsGRPCServer{extractor: extractor}

	ctx, cancel := context.WithTimeout(t.Context(), 15*time.Second)
	defer cancel()

	gate := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, gate, 4)

	var active, maximum atomic.Int64

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() {
		current := active.Add(1)
		defer active.Add(-1)

		for old := maximum.Load(); current > old; old = maximum.Load() {
			if maximum.CompareAndSwap(old, current) {
				break
			}
		}

		gate.Printf("")
	}})

	done := make(chan error, 1)

	var actual []*MostsItem

	go func() {
		var extractErr error

		actual, extractErr = server.extractItems(ctx, list, 42, fields, "en", UserContext{})
		done <- extractErr
	}()

	unblock()
	require.NoError(t, <-done)

	db.Logger(nil)
	require.EqualValues(t, 4, maximum.Load(), "card and picture reads must share one limit")
	require.Len(t, actual, len(list))

	for _, lang := range []string{"en", "ru"} {
		if lang != "en" {
			actual, err = server.extractItems(ctx, list, 42, fields, lang, UserContext{})
			require.NoError(t, err)
		}

		// Compare with the original sequential card extraction, retaining the
		// extractor's default internal parallelism and picture field selection.
		individualQueries := &itemBatchQueryLogger{}
		db.Logger(individualQueries)

		for index, car := range list {
			expected, err := extractor.Extract(ctx, car.Item, fields, lang, UserContext{})
			require.NoError(t, err)
			require.True(t, proto.Equal(expected, actual[index].GetItem()), "card %d, language %s", index, lang)
			require.Equal(t, car.ValueHTML, actual[index].GetValueHtml())
			require.EqualValues(t, 42, actual[index].GetUnitId())
			require.NotEmpty(t, actual[index].GetItem().GetNameHtml())
		}

		db.Logger(nil)

		batchedQueries := &itemBatchQueryLogger{}
		db.Logger(batchedQueries)

		_, err = server.extractItems(ctx, list, 42, fields, lang, UserContext{})
		require.NoError(t, err)
		db.Logger(nil)
		require.Less(t, batchedQueries.queries.Load(), individualQueries.queries.Load(),
			"shared preview extraction must reduce SQL for the actual rating field set")
	}

	require.NotEmpty(t, actual[0].GetItem().GetRoute())

	var withThumbnail bool

	for _, picture := range actual[0].GetItem().GetPreviewPictures().GetPictures() {
		if picture.GetPicture().GetThumbMedium().GetSrc() != "" {
			withThumbnail = true
		}
	}

	require.True(t, withThumbnail)
	require.True(t, proto.Equal(requested, fields))

	single, err := server.extractItems(ctx, list[:1], 42, fields, "ru", UserContext{})
	require.NoError(t, err)
	require.Len(t, single, 1)
	require.True(t, proto.Equal(single[0], actual[0]))

	empty, err := server.extractItems(ctx, nil, 42, fields, "en", UserContext{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)

	badItem := *first
	badItem.ID = -1
	failedList := append([]mosts.ResultItem{{Item: &badItem}}, list...)
	failed, err := server.extractItems(ctx, failedList, 42, fields, "en", UserContext{})
	require.Error(t, err)
	require.Nil(t, failed)
	require.NoError(t, ctx.Err())
	require.Zero(t, pool.Stats().InUse)

	// Occupy the only connection so cancellation interrupts all in-flight reads.
	pool.SetMaxOpenConns(1)
	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	defer connection.Close()

	childCtx, stop := context.WithCancel(ctx)
	defer stop()

	started := make(chan struct{}, 4)

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

	go func() {
		_, extractErr := server.extractItems(childCtx, list, 42, fields, "en", UserContext{})
		done <- extractErr
	}()

	for range 4 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("card reads did not start together")
		}
	}

	stop()
	require.ErrorIs(t, <-done, context.Canceled)
}
