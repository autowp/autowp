package goautowp

import (
	"context"
	"fmt"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/mosts"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	mostsgrpc "github.com/autowp/goautowp/internal/transport/grpc/mosts"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func mostsExtractionTestFields() *pb.ItemFields {
	return &pb.ItemFields{
		NameHtml: true, Description: true, Route: true,
		PreviewPictures: &pb.PreviewPicturesRequest{
			PerspectivePageId: 1,
			Pictures: &pb.PicturesRequest{
				Fields: &pb.PictureFields{NameText: true},
				Options: &pb.PictureListOptions{
					PictureItem: &pb.PictureItemListOptions{TypeId: pb.PictureItemType_PICTURE_ITEM_CONTENT},
				},
			},
		},
	}
}

func TestMostsItemsConcurrentExtraction(t *testing.T) {
	t.Parallel()

	extractor, vehicle, db, pool := newItemOfDayTestExtractor(t)
	secondID := createItem(
		t,
		conn,
		cnt,
		&pb.Item{Name: "Mosts without pictures", ItemTypeId: pb.ItemType_ITEM_TYPE_VEHICLE},
	)
	repository, err := extractor.container.ItemsRepository(t.Context())
	require.NoError(t, err)

	fields := mostsExtractionTestFields()
	requested := proto.Clone(fields)
	first, err := repository.Item(t.Context(), &query.ItemListOptions{ItemID: vehicle.ID, Language: "en"},
		itemmapper.FieldsFromProto(fields))
	require.NoError(t, err)
	second, err := repository.Item(t.Context(), &query.ItemListOptions{ItemID: secondID, Language: "en"},
		itemmapper.FieldsFromProto(fields))
	require.NoError(t, err)

	list := make([]mosts.ResultItem, 7)
	for index := range list {
		row := first
		if index%2 != 0 {
			row = second
		}

		list[index] = mosts.ResultItem{Item: row, ValueHTML: fmt.Sprintf("%d <span>km/h</span>", 100-index)}
	}

	server := mostsgrpc.NewServer(nil, extractor, nil)

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

	var actual []*pb.MostsItem

	go func() {
		var extractErr error

		actual, extractErr = server.ExtractItems(ctx, list, 42, fields, "en", authn.User{})
		done <- extractErr
	}()

	unblock()
	require.NoError(t, <-done)

	db.Logger(nil)
	require.EqualValues(t, 4, maximum.Load(), "card and picture reads must share one limit")
	require.Len(t, actual, len(list))

	for _, lang := range []string{"en", "ru"} {
		if lang != "en" {
			actual, err = server.ExtractItems(ctx, list, 42, fields, lang, authn.User{})
			require.NoError(t, err)
		}

		// Compare with the original sequential card extraction, retaining the
		// extractor's default internal parallelism and picture field selection.
		individualQueries := &itemBatchQueryLogger{}
		db.Logger(individualQueries)

		for index, car := range list {
			expected, err := extractor.Extract(ctx, car.Item, fields, lang, authn.User{})
			require.NoError(t, err)
			require.True(t, proto.Equal(expected, actual[index].GetItem()), "card %d, language %s", index, lang)
			require.Equal(t, car.ValueHTML, actual[index].GetValueHtml())
			require.EqualValues(t, 42, actual[index].GetUnitId())
			require.NotEmpty(t, actual[index].GetItem().GetNameHtml())
		}

		db.Logger(nil)

		batchedQueries := &itemBatchQueryLogger{}
		db.Logger(batchedQueries)

		_, err = server.ExtractItems(ctx, list, 42, fields, lang, authn.User{})
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

	single, err := server.ExtractItems(ctx, list[:1], 42, fields, "ru", authn.User{})
	require.NoError(t, err)
	require.Len(t, single, 1)
	require.True(t, proto.Equal(single[0], actual[0]))

	empty, err := server.ExtractItems(ctx, nil, 42, fields, "en", authn.User{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)

	badItem := *first
	badItem.ID = -1
	failedList := append([]mosts.ResultItem{{Item: &badItem}}, list...)
	failed, err := server.ExtractItems(ctx, failedList, 42, fields, "en", authn.User{})
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
		_, extractErr := server.ExtractItems(childCtx, list, 42, fields, "en", authn.User{})
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
