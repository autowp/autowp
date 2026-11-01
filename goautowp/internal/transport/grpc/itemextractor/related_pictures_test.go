package itemextractor

import (
	"context"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestRelatedGroupPicturesConcurrentReads(t *testing.T) {
	t.Parallel()

	extractor, vehicle, database, pool := newItemOfDayTestExtractor(t)
	categoryID := createTestItem(t, extractor, schema.ItemTableItemTypeIDCategory, true, "Parallel related groups")
	secondID := createTestItem(t, extractor, schema.ItemTableItemTypeIDVehicle, false, "Related without picture")
	repository, err := extractor.dependencies.ItemsRepository(t.Context())
	require.NoError(t, err)

	for _, id := range []int64{vehicle.ID, secondID} {
		_, err := repository.CreateItemParent(t.Context(), id, categoryID, schema.ItemParentTypeDefault, "")
		require.NoError(t, err)
	}

	row := &items.Item{ID: categoryID, ItemTypeID: schema.ItemTableItemTypeIDCategory}
	fields := &pb.ItemFields{RelatedGroupPictures: true}

	for _, lang := range []string{"en", "ru"} {
		ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
		data, lookups, err := extractor.prepareRelatedGroupPictures(ctx, row, lang)
		require.NoError(t, err)

		// Finish group discovery before gating the two independent consumers.
		require.NoError(t, lookups[0](ctx))

		gate := &itemBatchQueryLogger{}
		unblock := blockItemExtractionQueries(t, gate, 2)
		database.Logger(gate)

		done := make(chan error, 1)
		go func() { done <- extraction.Run(ctx, lookups[1:]) }()

		unblock()
		require.NoError(t, <-done)

		actual, err := data.extract(lang)
		require.NoError(t, err)
		require.Len(t, actual, 2)

		database.Logger(nil)

		serialCtx := extraction.WithInlineLookups(ctx)
		expected, err := extractor.extractRelatedGroupsPictures(serialCtx, fields, row, lang)
		require.NoError(t, err)

		plain := &pb.Item{}
		require.NoError(t, extractor.extractPlain(ctx, fields, row, plain, lang, authn.User{}))

		for index := range expected {
			require.True(t, proto.Equal(expected[index], actual[index]))
			require.True(t, proto.Equal(expected[index], plain.GetRelatedGroupPictures()[index]))
			require.NotEmpty(t, actual[index].GetNameHtml())
		}

		cancel()
	}

	empty, err := extractor.extractRelatedGroupsPictures(t.Context(), fields, &items.Item{ID: secondID}, "en")
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)

	// A failure before publishing groups must cancel waiting consumers. Once
	// groups are known, cancellation must also interrupt both independent reads.
	for _, afterGroups := range []bool{false, true} {
		ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
		data, lookups, err := extractor.prepareRelatedGroupPictures(ctx, row, "en")
		require.NoError(t, err)

		if afterGroups {
			require.NoError(t, lookups[0](ctx))
			lookups = lookups[1:]
		}

		pool.SetMaxOpenConns(1)
		connection, err := pool.Conn(ctx)
		require.NoError(t, err)

		started := make(chan struct{}, 2)

		database.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

		done := make(chan error, 1)
		go func() { done <- extraction.Run(ctx, lookups) }()

		count := 1

		if afterGroups {
			count = 2
		}

		for range count {
			select {
			case <-started:
			case <-ctx.Done():
				t.Fatal("related group reads did not start")
			}
		}

		cancel()
		require.ErrorIs(t, <-done, context.Canceled)
		require.Empty(t, data.cars)
		require.NoError(t, connection.Close())

		database.Logger(nil)
	}
}
