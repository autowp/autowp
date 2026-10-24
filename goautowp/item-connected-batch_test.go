package goautowp

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newConnectedItemExtractor(t *testing.T) (*ItemExtractor, *goqu.Database, *sql.DB) {
	t.Helper()
	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE item (LIKE public.item INCLUDING DEFAULTS INCLUDING INDEXES);
 ALTER TABLE pg_temp.item ALTER COLUMN body SET DEFAULT '';
 ALTER TABLE pg_temp.item ALTER COLUMN produced_exactly SET DEFAULT false;
 CREATE TEMP TABLE item_parent (item_id bigint,parent_id bigint,catname text,type integer,
 manual_catname boolean DEFAULT false);
 CREATE TEMP TABLE item_parent_cache (item_id bigint,parent_id bigint,diff integer);
 CREATE TEMP TABLE item_language_cache (item_id bigint,language text,name text,PRIMARY KEY(item_id,language));
 CREATE TEMP TABLE item_point (item_id bigint,point geography(Point,4326));
 CREATE TEMP TABLE user_item_subscribe (item_id bigint,user_id bigint);
 INSERT INTO item_parent_cache VALUES
 (100,100,0),(101,100,1),(101,101,0),(200,200,0),
 (10,10,0),(11,11,0),(12,12,0),(13,13,0),(14,14,0),(15,15,0),(16,16,0),(20,20,0),
 (10,20,1),(11,20,1),(12,20,1),(13,20,1),(14,20,1),(15,20,1),(16,20,1),
 (10,500,1),(10,501,2),(11,500,1),(100,502,1),(500,500,0),(501,501,0),(502,502,0);
 INSERT INTO user_item_subscribe VALUES (500,7),(700,7),(10,7),(501,8);
 INSERT INTO item_point VALUES (500,ST_GeogFromText('POINT(30 50)')),(700,NULL),(10,ST_GeogFromText('POINT(40 60)'));
 `)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO item (id,catname,item_type_id,engine_item_id) VALUES
 (100,'engine-a',$1,NULL),(101,'engine-child',$1,NULL),(200,'engine-b',$1,NULL),(300,'empty-engine',$1,NULL),
 (10,'a',$2,100),(11,'b',$2,101),(12,'c',$2,100),(13,'d',$2,200),
 (14,'e',$2,200),(15,'f',$2,200),(16,'g',$2,200),(20,'ancestor',$2,100),
 (500,'twin-a',$3,NULL),(501,'twin-b',$3,NULL),(502,'twin-engine',$3,NULL),
 (700,'cat-a',$4,NULL),(701,'cat-b',$4,NULL),(702,'cat-engine',$4,NULL),(703,'excluded',$4,NULL)`,
		schema.ItemTableItemTypeIDEngine, schema.ItemTableItemTypeIDVehicle,
		schema.ItemTableItemTypeIDTwins, schema.ItemTableItemTypeIDCategory)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO item_parent (item_id,parent_id,catname,type) VALUES
 (10,700,'cat-a',$1),(11,701,'cat-b',$1),(20,701,'shared',$1),(100,702,'engine',$1),(500,703,'excluded',$1)`,
		schema.ItemParentTypeDefault)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `
 INSERT INTO item_language_cache
 SELECT id,'en',CASE id WHEN 500 THEN 'Zebra' WHEN 501 THEN 'apple' ELSE 'EN ' || id END FROM item;
 INSERT INTO item_language_cache
 SELECT id,'ru',CASE id WHEN 500 THEN 'Яблоко' WHEN 501 THEN 'Арбуз' ELSE 'РУ ' || id END FROM item;
 `)
	require.NoError(t, err)
	db.Logger(nil)

	return NewItemExtractor(&Container{config: config.LoadConfig("."), goquDB: db}), db, pool
}

func connectedItemRows() []*items.Item {
	rows := [...]*items.Item{
		{ID: 10, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 11, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 100, ItemTypeID: schema.ItemTableItemTypeIDEngine},
		{ID: 101, ItemTypeID: schema.ItemTableItemTypeIDEngine},
		{ID: 200, ItemTypeID: schema.ItemTableItemTypeIDEngine},
		{ID: 300, ItemTypeID: schema.ItemTableItemTypeIDEngine},
		{ID: 999, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 0, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
	}

	return append(rows[:], rows[0], rows[2])
}

func requireConnectedItemsEqual(t *testing.T, want, got []*Item) {
	t.Helper()
	require.Equal(t, want == nil, got == nil, "nil and empty lists retain their meaning")
	require.Len(t, got, len(want))

	for index, row := range want {
		require.True(t, proto.Equal(row, got[index]), "item %d differs", row.GetId())
	}
}

func TestConnectedItemBatchMatchesIndividual(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newConnectedItemExtractor(t)
	rows := connectedItemRows()
	user := UserContext{UserID: 7}
	childFields := &ItemFields{NameOnly: true, Location: true, Subscription: true}

	for _, lang := range []string{"en", "ru"} {
		for _, order := range []ItemsRequest_Order{
			ItemsRequest_NAME, ItemsRequest_NAME_NAT, ItemsRequest_ID_DESC, ItemsRequest_AGE,
		} {
			request := &ItemsRequest{
				Fields:  childFields,
				Order:   order,
				Options: &ItemListOptions{Id: 999},
				Limit:   1,
				Page:    99,
			}
			for _, fields := range []*ItemFields{
				{},
				{Twins: request},
				{Categories: request},
				{EngineVehicles: &ItemsRequest{Fields: childFields, Order: order, Limit: 1, Page: 99}},
				{Twins: request, Categories: request, EngineVehicles: &ItemsRequest{Fields: childFields, Order: order}},
			} {
				expected := make([]*Item, len(rows))
				for index, row := range rows {
					expected[index] = &Item{}
					require.NoError(t, extractor.extractPlain(t.Context(), fields, row, expected[index], lang, user))
				}

				logger := &itemBatchQueryLogger{}
				db.Logger(logger)

				selected := proto.Clone(fields)
				actual, err := extractor.ExtractRows(t.Context(), rows, fields, lang, user)
				require.NoError(t, err)
				db.Logger(nil)
				require.True(t, proto.Equal(selected, fields))

				queries := 0
				if fields.GetTwins() != nil {
					queries += 3
				}

				if fields.GetCategories() != nil {
					queries += 3
				}

				if fields.GetEngineVehicles() != nil {
					queries += 5
				}

				require.EqualValues(t, queries, logger.queries.Load())
				require.Len(t, actual, len(rows))

				for index, row := range actual {
					require.Equal(t, rows[index].ID, row.GetId())
					requireConnectedItemsEqual(t, expected[index].GetTwins(), row.GetTwins())
					requireConnectedItemsEqual(t, expected[index].GetCategories(), row.GetCategories())
					requireConnectedItemsEqual(t, expected[index].GetEngineVehicles(), row.GetEngineVehicles())
				}

				if fields.GetTwins() != nil {
					require.Len(t, actual[0].GetTwins(), 2, "Options/Limit remain ignored")
				}

				if fields.GetCategories() != nil {
					require.Len(t, actual[0].GetCategories(), 2)
				}

				if fields.GetEngineVehicles() != nil {
					require.Nil(t, actual[0].GetEngineVehicles(), "non-engines have no engine vehicles")
					require.Nil(t, actual[5].GetEngineVehicles(), "no candidates retains nil")
					require.Len(t, actual[2].GetEngineVehicles(), 3)
					require.Len(t, actual[4].GetEngineVehicles(), 1, "large groups are merged")
					require.EqualValues(t, 20, actual[4].GetEngineVehicles()[0].GetId())
				}
			}
		}
	}
}

func TestConnectedItemBatchEngineOptionsAndNestedFields(t *testing.T) {
	t.Parallel()
	extractor, _, _ := newConnectedItemExtractor(t)
	rows := connectedItemRows()

	cases := []*ItemFields{
		{EngineVehicles: &ItemsRequest{Options: &ItemListOptions{Id: 10}, Fields: &ItemFields{NameOnly: true}}},
		{Twins: &ItemsRequest{}, Categories: &ItemsRequest{}, EngineVehicles: &ItemsRequest{}},
		{
			Twins: &ItemsRequest{
				Fields: &ItemFields{NameOnly: true, Twins: &ItemsRequest{Fields: &ItemFields{Location: true}}},
			},
		},
		{
			Categories: &ItemsRequest{
				Fields: &ItemFields{NameOnly: true, Categories: &ItemsRequest{Fields: &ItemFields{NameOnly: true}}},
			},
		},
	}
	for _, fields := range cases {
		actual, err := extractor.ExtractRows(t.Context(), rows, fields, "ru", UserContext{})
		require.NoError(t, err)

		for index, row := range rows {
			expected := &Item{}
			require.NoError(t, extractor.extractPlain(t.Context(), fields, row, expected, "ru", UserContext{}))
			requireConnectedItemsEqual(t, expected.GetTwins(), actual[index].GetTwins())
			requireConnectedItemsEqual(t, expected.GetCategories(), actual[index].GetCategories())
			requireConnectedItemsEqual(t, expected.GetEngineVehicles(), actual[index].GetEngineVehicles())
		}
	}
}

func TestEngineVehicleGroupsBatchMatchesIndividual(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newConnectedItemExtractor(t)
	repository, err := extractor.container.ItemsRepository(t.Context())
	require.NoError(t, err)

	ids := []int64{100, 101, 200, 300, 0, -1, 100}

	for _, limit := range []int{0, 1, 3, 10} {
		expected := make(map[int64][]int64)
		for _, id := range ids {
			expected[id], err = repository.EngineVehiclesGroups(t.Context(), id, limit)
			require.NoError(t, err)
		}

		logger := &itemBatchQueryLogger{}
		db.Logger(logger)

		actual, err := repository.EngineVehiclesGroupsBatch(t.Context(), ids, limit)
		require.NoError(t, err)
		db.Logger(nil)
		require.EqualValues(t, 2, logger.queries.Load())

		for _, id := range ids {
			require.Equal(t, expected[id], actual[id], "engine %d limit %d", id, limit)
		}
	}
}

func TestConnectedItemBatchParallelAndFailure(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newConnectedItemExtractor(t)
	fields := &ItemFields{Twins: &ItemsRequest{}, Categories: &ItemsRequest{}, EngineVehicles: &ItemsRequest{}}
	logger := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, logger, 3)
	db.Logger(logger)

	joined := make(chan error, 1)

	go func() {
		_, err := extractor.ExtractRows(t.Context(), connectedItemRows(), fields, "en", UserContext{})
		joined <- err
	}()

	unblock()
	require.NoError(t, <-joined)
	db.Logger(nil)

	for _, invalid := range []*ItemFields{
		{Twins: &ItemsRequest{Order: ItemsRequest_NAME_NAT}},
		{Categories: &ItemsRequest{Order: ItemsRequest_NAME_NAT}},
	} {
		actual, err := extractor.ExtractRows(t.Context(), connectedItemRows(), invalid, "en", UserContext{})
		require.Error(t, err)
		require.Nil(t, actual)
	}

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	actual, err := extractor.ExtractRows(ctx, connectedItemRows(), fields, "en", UserContext{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, actual)
}

func TestGroupedItemListMatchesList(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newConnectedItemExtractor(t)
	repository, err := extractor.container.ItemsRepository(t.Context())
	require.NoError(t, err)

	fields := &items.ItemFields{NameOnly: true, NameText: true, NameHTML: true, Meta: true}
	groups := map[int64]*query.ItemListOptions{
		1: {ItemIDs: []int64{500, 501}, Language: "ru"},
		2: {ItemIDs: []int64{500, 501}, Language: "en", SortByName: true},
		3: {ItemIDs: []int64{999}, Language: "en"},
	}
	// SQL language belongs to each group, independently of the extraction language.
	for _, order := range []items.OrderBy{items.OrderByName, items.OrderByIDDesc, items.OrderByAge} {
		expected := make(map[int64][]*items.Item)
		for id, options := range groups {
			expected[id], _, err = repository.List(t.Context(), options.Clone(), fields, order, false)
			require.NoError(t, err)
		}

		logger := &itemBatchQueryLogger{}
		db.Logger(logger)

		actual, err := repository.GroupedList(t.Context(), groups, fields, order)
		require.NoError(t, err)
		db.Logger(nil)
		require.Equal(t, expected, actual)
		require.EqualValues(t, 1, logger.queries.Load())
	}
	// Retain the same clamped page selection for each limited query.
	for _, options := range groups {
		options.Limit = 1
		options.Page = 99
	}

	actual, err := repository.GroupedList(t.Context(), groups, fields, items.OrderByName)
	require.NoError(t, err)

	for id, options := range groups {
		expected, _, err := repository.List(t.Context(), options.Clone(), fields, items.OrderByName, false)
		require.NoError(t, err)
		require.Equal(t, expected, actual[id])
	}
}

func TestConnectedItemBatchSQLFailure(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		statement string
		fields    *ItemFields
	}{
		{"ALTER TABLE pg_temp.item_parent_cache RENAME COLUMN parent_id TO broken", &ItemFields{Twins: &ItemsRequest{}}},
		{"ALTER TABLE pg_temp.item_parent RENAME COLUMN parent_id TO broken", &ItemFields{Categories: &ItemsRequest{}}},
		{"ALTER TABLE pg_temp.item_parent_cache RENAME COLUMN diff TO broken", &ItemFields{EngineVehicles: &ItemsRequest{}}},
		{"ALTER TABLE pg_temp.item_point RENAME COLUMN point TO broken", &ItemFields{
			Twins: &ItemsRequest{Fields: &ItemFields{Location: true}},
		}},
	} {
		t.Run(test.statement, func(t *testing.T) {
			t.Parallel()
			extractor, _, pool := newConnectedItemExtractor(t)
			_, err := pool.ExecContext(t.Context(), test.statement)
			require.NoError(t, err)
			actual, err := extractor.ExtractRows(t.Context(), connectedItemRows(), test.fields, "en", UserContext{})
			require.Error(t, err)
			require.Nil(t, actual)
		})
	}
}

func TestConnectedItemBatchEmptyAndNonEngines(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newConnectedItemExtractor(t)
	logger := &itemBatchQueryLogger{}
	db.Logger(logger)

	fields := &ItemFields{Twins: &ItemsRequest{}, Categories: &ItemsRequest{}, EngineVehicles: &ItemsRequest{}}
	empty, err := extractor.ExtractRows(t.Context(), nil, fields, "en", UserContext{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
	actual, err := extractor.ExtractRows(t.Context(), connectedItemRows()[:2],
		&ItemFields{EngineVehicles: &ItemsRequest{}}, "en", UserContext{})
	require.NoError(t, err)

	for _, row := range actual {
		require.Nil(t, row.GetEngineVehicles())
	}

	require.Zero(t, logger.queries.Load())
	repository, err := extractor.container.ItemsRepository(t.Context())
	require.NoError(t, err)
	groups, err := repository.EngineVehiclesGroupsBatch(t.Context(), nil, engineVehiclesGroupsLimit)
	require.NoError(t, err)
	require.Empty(t, groups)
	selected, err := repository.GroupedList(t.Context(), nil, nil, items.OrderByNone)
	require.NoError(t, err)
	require.Empty(t, selected)
	require.Zero(t, logger.queries.Load())
}
