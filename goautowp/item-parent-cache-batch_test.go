package goautowp

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newParentCacheBatchTestExtractor(
	t *testing.T,
) (*ItemParentCacheExtractor, []int64, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	ids := make([]int64, 0, 3)
	for range 3 {
		ids = append(ids, createItem(t, conn, cnt, &Item{
			Name: "parent-cache-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		}))
	}

	cfg := config.LoadConfig(".")
	pool, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })

	_, err = pool.ExecContext(t.Context(), `
  CREATE TEMP TABLE item_link (item_id bigint);
  CREATE TEMP TABLE attrs_values (item_id bigint, attribute_id bigint);
  CREATE TEMP TABLE comment_topic (item_id bigint, type_id integer, messages integer);
  CREATE TEMP TABLE user_item_subscribe (item_id bigint, user_id bigint);
 `)
	require.NoError(t, err)

	for _, statement := range []string{
		"INSERT INTO pg_temp.item_link VALUES ($1), ($1), ($2)",
		"INSERT INTO pg_temp.attrs_values VALUES ($1, 11), ($1, 12), ($2, 11)",
		"INSERT INTO pg_temp.comment_topic VALUES ($1, 2, 12), ($2, 2, 4)",
		"INSERT INTO pg_temp.user_item_subscribe VALUES ($1, 7), ($2, 8)",
	} {
		_, err := pool.ExecContext(t.Context(), statement, ids[0], ids[1])
		require.NoError(t, err)
	}

	db := goqu.New("postgres", pool)
	logger := &itemBatchQueryLogger{}
	db.Logger(logger)
	container := &Container{config: cfg, goquDB: db}

	return NewItemParentCacheExtractor(container), ids, pool, logger
}

func parentCacheBatchTestFields() *ItemParentCacheFields {
	return &ItemParentCacheFields{ParentItem: &ItemsRequest{
		Options: &ItemListOptions{TypeId: ItemType_ITEM_TYPE_VEHICLE},
		Fields: &ItemFields{
			Meta: true, NameHtml: true, NameText: true, LinksCount: true, ItemLanguageCount: true,
			SpecificationsCount: true, Subscription: true, HasText: true, CommentsCount: true,
		},
	}}
}

func TestItemParentCacheBatchesExtraction(t *testing.T) {
	t.Parallel()

	extractor, ids, _, logger := newParentCacheBatchTestExtractor(t)
	fields := parentCacheBatchTestFields()
	requested := proto.Clone(fields)
	rows := []*schema.ItemParentCacheRow{
		{ItemID: 101, ParentID: ids[1]},
		{ItemID: 102, ParentID: ids[0]},
		{ItemID: 103, ParentID: ids[1]},
		{ItemID: 104, ParentID: ids[2]},
		{ItemID: 105},
		{ItemID: 106, ParentID: -1},
	}
	userCtx := UserContext{UserID: 7, Roles: []string{users.RoleModer}}
	repository, err := extractor.container.ItemsRepository(t.Context())
	require.NoError(t, err)

	for _, lang := range []string{"en", "ru"} {
		source, _, err := repository.List(t.Context(), &query.ItemListOptions{ItemIDs: ids, Language: lang},
			convertItemFields(fields.GetParentItem().GetFields()), items.OrderByNone, false)
		require.NoError(t, err)

		expected := make(map[int64]*Item, len(source))

		logger.queries.Store(0)

		for _, row := range source {
			parent, err := extractor.container.ItemExtractor().Extract(
				t.Context(), row, fields.GetParentItem().GetFields(), lang, userCtx,
			)
			require.NoError(t, err)

			expected[row.ID] = parent
		}

		require.EqualValues(t, 21, logger.queries.Load(), "old extraction issues seven reads for each of three parents")

		for _, nested := range []bool{false, true} {
			ctx := t.Context()
			if nested {
				ctx = context.WithValue(ctx, itemLookupContextKey{}, true)
			}

			logger.queries.Store(0)

			actual, err := extractor.ExtractRows(ctx, rows, fields, lang, userCtx)
			require.NoError(t, err)
			require.EqualValues(t, 8, logger.queries.Load(), "one parent list plus seven field batches")
			require.Len(t, actual, len(rows))

			for index, row := range rows {
				require.Equal(t, row.ItemID, actual[index].GetItemId())
				require.Equal(t, row.ParentID, actual[index].GetParentId())
				require.True(t, proto.Equal(expected[row.ParentID], actual[index].GetParentItem()), "row %d", index)
			}

			require.Same(t, actual[0].GetParentItem(), actual[2].GetParentItem())
			require.EqualValues(t, 2, actual[1].GetParentItem().GetLinksCount())
			require.EqualValues(t, 2, actual[1].GetParentItem().GetSpecificationsCount())
			require.EqualValues(t, 12, actual[1].GetParentItem().GetCommentsCount())
			require.True(t, actual[1].GetParentItem().GetSubscription())
		}
	}

	require.True(t, proto.Equal(requested, fields))

	public, err := extractor.ExtractRows(t.Context(), rows, fields, "en", UserContext{UserID: 7})
	require.NoError(t, err)
	require.Zero(t, public[1].GetParentItem().GetLinksCount())
	require.Zero(t, public[1].GetParentItem().GetSpecificationsCount())
	require.Empty(t, public[1].GetParentItem().GetName())
	require.NotEmpty(t, public[1].GetParentItem().GetNameHtml())

	filtered, ok := proto.Clone(fields).(*ItemParentCacheFields)
	require.True(t, ok)

	filtered.GetParentItem().GetOptions().TypeId = ItemType_ITEM_TYPE_ENGINE
	actual, err := extractor.ExtractRows(t.Context(), rows, filtered, "en", userCtx)
	require.NoError(t, err)

	for _, row := range actual {
		require.Nil(t, row.GetParentItem())
	}
}

func TestItemParentCacheBatchCancellation(t *testing.T) {
	t.Parallel()

	extractor, ids, pool, logger := newParentCacheBatchTestExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	var queries atomic.Int64

	started := make(chan struct{}, 4)
	logger.beforeQuery = func() {
		if queries.Add(1) > 1 {
			started <- struct{}{}

			<-ctx.Done()
		}
	}

	done := make(chan error, 1)

	go func() {
		_, extractErr := extractor.preloadItems(ctx, parentCacheBatchTestFields().GetParentItem(), ids,
			"en", UserContext{UserID: 7, Roles: []string{users.RoleModer}})
		done <- extractErr
	}()

	for range 4 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("batched parent fields did not start")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
	require.Zero(t, pool.Stats().InUse)
}

func TestItemParentCacheBatchReadFailure(t *testing.T) {
	t.Parallel()

	extractor, ids, pool, _ := newParentCacheBatchTestExtractor(t)
	_, err := pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.item_link RENAME COLUMN item_id TO broken_item_id")
	require.NoError(t, err)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	actual, err := extractor.preloadItems(ctx, parentCacheBatchTestFields().GetParentItem(), ids,
		"en", UserContext{UserID: 7, Roles: []string{users.RoleModer}})
	require.ErrorContains(t, err, "item_id")
	require.Nil(t, actual)
	require.NoError(t, ctx.Err())
	require.Zero(t, pool.Stats().InUse)
}

func TestItemParentCacheBatchUnselectedAndEmpty(t *testing.T) {
	t.Parallel()

	extractor := &ItemParentCacheExtractor{}
	unselected, err := extractor.preloadItems(t.Context(), nil, []int64{7}, "en", UserContext{})
	require.NoError(t, err)
	require.Nil(t, unselected)
	empty, err := extractor.preloadItems(t.Context(), &ItemsRequest{}, nil, "en", UserContext{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
	result, err := extractor.ExtractRows(t.Context(), []*schema.ItemParentCacheRow{{ItemID: 1, ParentID: 7}},
		nil, "en", UserContext{})
	require.NoError(t, err)
	require.Nil(t, result[0].GetParentItem())
}
