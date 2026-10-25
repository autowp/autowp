package goautowp

import (
	"context"
	"database/sql"
	"fmt"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/proto"
)

func newItemOfDayTestExtractor(t *testing.T) (*ItemExtractor, *items.Item, *goqu.Database, *sql.DB) {
	t.Helper()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	suffix := time.Now().UnixNano()
	brandID := createItem(t, conn, cnt, &Item{
		Name: fmt.Sprintf("Day brand %d", suffix), ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname: fmt.Sprintf("day-%d", suffix),
	})
	vehicleID := createItem(t, conn, cnt, &Item{
		Name: fmt.Sprintf("Day vehicle %d", suffix), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
	})
	apiCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)
	_, err = NewItemsClient(conn).CreateItemParent(apiCtx, &ItemParent{
		ItemId: vehicleID, ParentId: brandID, Catname: "model",
	})
	require.NoError(t, err)

	for _, perspective := range []int32{schema.PerspectiveFrontStrict, schema.PerspectiveBackStrict} {
		addPicture(t, cnt, conn, "./test/test.jpg",
			PicturePostForm{ItemID: vehicleID, PerspectiveID: perspective},
			PictureStatus_PICTURE_STATUS_ACCEPTED, token.AccessToken)
	}

	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(2)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	goquDB := goqu.New("postgres", database)
	container := &Container{config: cfg, goquDB: goquDB}
	repository, err := container.ItemsRepository(ctx)
	require.NoError(t, err)
	row, err := repository.Item(ctx, &query.ItemListOptions{ItemID: vehicleID, Language: "en"}, nil)
	require.NoError(t, err)

	return NewItemExtractor(container), row, goquDB, database
}

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
	go func() { done <- runItemLookups(ctx, lookups) }()

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

	fields := &ItemFields{ItemOfDayPictures: true}

	for _, language := range []string{"en", "ru"} {
		inlineCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
		expected, err := extractor.extractItemOfDayPictures(inlineCtx, fields, row, language)
		require.NoError(t, err)

		result := &Item{}
		require.NoError(t, extractor.extractPlain(ctx, fields, row, result, language, UserContext{}))
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
	go func() { done <- runItemLookups(ctx, lookups) }()

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

	pictures, err := (&ItemExtractor{}).extractItemOfDayPictures(t.Context(), nil, &items.Item{}, "en")
	require.NoError(t, err)
	require.Nil(t, pictures)
}
