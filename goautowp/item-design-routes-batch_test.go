package goautowp

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newDesignRoutesExtractor(t *testing.T) (*ItemExtractor, *goqu.Database, *sql.DB) {
	t.Helper()
	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE item (LIKE public.item INCLUDING DEFAULTS INCLUDING INDEXES);
 ALTER TABLE pg_temp.item ALTER COLUMN body SET DEFAULT '';
 ALTER TABLE pg_temp.item ALTER COLUMN produced_exactly SET DEFAULT false;
 CREATE TEMP TABLE item_parent (
 item_id bigint,parent_id bigint,catname text,type integer,manual_catname boolean DEFAULT false);
 CREATE TEMP TABLE item_parent_cache (item_id bigint,parent_id bigint,diff integer,design boolean);
 CREATE TEMP TABLE item_language_cache (item_id bigint,language text,name text);
 CREATE TEMP TABLE picture (id bigint,status text);
 CREATE TEMP TABLE picture_item (picture_id bigint,item_id bigint);
 CREATE TEMP TABLE of_day (item_id bigint);
 INSERT INTO item_language_cache VALUES (1,'en','First'),(1,'ru','Первый'),(2,'en','Second'),(2,'ru','Второй');
 INSERT INTO picture VALUES (1,'accepted'),(2,'accepted'),(3,'accepted'),(4,'inbox');
 INSERT INTO picture_item VALUES
 (1,101),(2,101),(3,101),(4,101),(1,102),(2,102),
 (1,103),(2,103),(3,103),(1,104),(1,104),(1,104);
 INSERT INTO of_day VALUES (12),(NULL);
 INSERT INTO item_parent_cache VALUES
 (101,10,1,false),(102,11,1,false),(103,12,1,false),(104,13,1,false),
 (11,50,10,false),(11,60,1,true),(12,60,1,true),(13,50,10,false),(13,70,2,false),
 (0,50,1,false),(-1,60,1,true);
 `)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO item (id,catname,item_type_id) VALUES
 (1,'first',$1),(2,'second',$1),(10,'model',$2),(11,'child',$2),(12,'alternative',$2),
 (13,'other',$2),(20,'empty',$2),(30,'orphan',$2),(-1,'negative',$2),
 (40,'factory',$3),(41,'category',$4),(42,'twins',$5),
 (50,'direct',$2),(60,'inherited',$2),(70,'near',$2)`,
		schema.ItemTableItemTypeIDBrand, schema.ItemTableItemTypeIDVehicle,
		schema.ItemTableItemTypeIDFactory, schema.ItemTableItemTypeIDCategory, schema.ItemTableItemTypeIDTwins)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO item_parent (item_id,parent_id,catname,type) VALUES
 (11,10,'child',$1),(11,12,'alias',$1),(12,1,'alternative',$1),
 (13,2,'other',$1),(10,1,'model',$1),(1,2,'above-brand',$1),(30,999,'missing',$1),
 (50,1,'direct',$2),(60,2,'inherited',$1),(70,2,'near',$2)`,
		schema.ItemParentTypeDefault, schema.ItemParentTypeDesign)
	require.NoError(t, err)
	db.Logger(nil)

	return NewItemExtractor(&Container{config: config.LoadConfig("."), goquDB: db}), db, pool
}

func designRouteTestRows() []*items.Item {
	rows := [...]*items.Item{
		{ID: 11, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 12, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 13, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 10, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 20, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 30, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: -1, ItemTypeID: schema.ItemTableItemTypeIDVehicle},
		{ID: 1, ItemTypeID: schema.ItemTableItemTypeIDBrand},
		{ID: 40, ItemTypeID: schema.ItemTableItemTypeIDFactory},
		{ID: 41, ItemTypeID: schema.ItemTableItemTypeIDCategory},
		{ID: 42, ItemTypeID: schema.ItemTableItemTypeIDTwins},
	}

	duplicates := make([]*items.Item, 30)
	for index := range duplicates {
		duplicates[index] = rows[0]
	}

	return append(rows[:], duplicates...)
}

func TestItemDesignRoutesBatchMatchesIndividual(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newDesignRoutesExtractor(t)
	rows := designRouteTestRows()

	for _, lang := range []string{"en", "ru"} {
		for _, fields := range []*ItemFields{
			{},
			{Design: true},
			{PublicRoutes: true},
			{IsCompilesItemOfDay: true},
			{Design: true, PublicRoutes: true, IsCompilesItemOfDay: true},
		} {
			expected := make([]*Item, len(rows))
			for index, row := range rows {
				expected[index] = &Item{}
				require.NoError(
					t,
					extractor.extractPlain(t.Context(), fields, row, expected[index], lang, UserContext{}),
				)
			}

			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			selected := proto.Clone(fields)
			result, err := extractor.ExtractRows(t.Context(), rows, fields, lang, UserContext{})
			require.NoError(t, err)
			db.Logger(nil)

			queries := 0
			if fields.GetDesign() {
				queries += 2
			}

			if fields.GetPublicRoutes() {
				queries += 3
			}

			if fields.GetIsCompilesItemOfDay() {
				queries++
			}

			require.EqualValues(t, queries, logger.queries.Load())
			require.True(t, proto.Equal(selected, fields))
			require.Len(t, result, len(rows))

			for index, row := range result {
				require.Equal(t, rows[index].ID, row.GetId())
				require.True(
					t,
					proto.Equal(expected[index].GetDesign(), row.GetDesign()),
					"design item %d",
					row.GetId(),
				)
				require.Equal(
					t,
					expected[index].GetPublicRoutes(),
					row.GetPublicRoutes(),
					"routes item %d",
					row.GetId(),
				)
				require.Equal(t, expected[index].GetIsCompilesItemOfDay(), row.GetIsCompilesItemOfDay())
			}

			if fields.GetDesign() {
				require.Equal(t, []string{"/", "first", "direct"}, result[0].GetDesign().GetRoute())
				require.Equal(t, []string{"/", "second", "inherited"}, result[1].GetDesign().GetRoute())
				require.Equal(t, []string{"/", "second", "near"}, result[2].GetDesign().GetRoute())
				require.Nil(t, result[4].GetDesign())
			}

			if fields.GetPublicRoutes() {
				require.Len(
					t,
					result[0].GetPublicRoutes(),
					4,
					"both paths and routes through ancestors above a brand survive",
				)
				require.NotNil(t, result[4].GetPublicRoutes())
				require.Empty(t, result[4].GetPublicRoutes())
				require.Empty(t, result[5].GetPublicRoutes())
			}

			if fields.GetIsCompilesItemOfDay() {
				require.True(t, result[3].GetIsCompilesItemOfDay())
				require.False(t, result[0].GetIsCompilesItemOfDay(), "too few pictures")
				require.False(t, result[1].GetIsCompilesItemOfDay(), "already selected")
				require.False(t, result[2].GetIsCompilesItemOfDay(), "count distinct pictures")
			}
		}
	}
}

func TestItemDesignRoutesBatchEmptyAndSpecialIDs(t *testing.T) {
	t.Parallel()
	extractor, db, pool := newDesignRoutesExtractor(t)
	repository, err := extractor.container.ItemsRepository(t.Context())
	require.NoError(t, err)
	dayRepository, err := extractor.container.ItemOfDayRepository(t.Context())
	require.NoError(t, err)

	logger := &itemBatchQueryLogger{}
	db.Logger(logger)

	result, err := extractor.ExtractRows(t.Context(), nil,
		&ItemFields{Design: true, PublicRoutes: true, IsCompilesItemOfDay: true}, "en", UserContext{})
	require.NoError(t, err)
	require.NotNil(t, result)
	require.Empty(t, result)
	designs, err := repository.DesignInfos(t.Context(), nil, "en")
	require.NoError(t, err)
	require.Empty(t, designs)
	complies, err := dayRepository.CompliesBatch(t.Context(), nil)
	require.NoError(t, err)
	require.Empty(t, complies)
	graph, err := repository.PublicRouteAncestors(t.Context(), nil)
	require.NoError(t, err)
	require.Empty(t, graph.Parents)
	require.Zero(t, logger.queries.Load())
	db.Logger(nil)

	_, expectedErr := dayRepository.IsComplies(t.Context(), 0)
	_, err = dayRepository.CompliesBatch(t.Context(), []int64{10, 0})
	require.ErrorIs(t, err, expectedErr)
	result, err = extractor.ExtractRows(t.Context(), []*items.Item{{ID: 0}},
		&ItemFields{IsCompilesItemOfDay: true}, "en", UserContext{})
	require.ErrorIs(t, err, expectedErr)
	require.Nil(t, result)

	// Every item has a direct design, so a broken fallback-only column is never read.
	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.item_parent_cache RENAME COLUMN design TO broken")
	require.NoError(t, err)
	db.Logger(logger)

	designs, err = repository.DesignInfos(t.Context(), []int64{0, 11, 13, 11}, "ru")
	require.NoError(t, err)
	require.EqualValues(t, 1, logger.queries.Load())
	require.Equal(t, "Первый", designs[0].Name)
	require.Equal(t, "Первый", designs[11].Name)
	require.Equal(t, "Второй", designs[13].Name)
}

func TestItemDesignRoutesBatchFailure(t *testing.T) {
	t.Parallel()

	cases := []struct {
		statement string
		fields    *ItemFields
	}{
		{"ALTER TABLE pg_temp.item_parent_cache RENAME COLUMN design TO broken", &ItemFields{Design: true}},
		{"ALTER TABLE pg_temp.item_parent RENAME COLUMN manual_catname TO broken", &ItemFields{PublicRoutes: true}},
		{"ALTER TABLE pg_temp.picture RENAME COLUMN status TO broken", &ItemFields{IsCompilesItemOfDay: true}},
	}
	for _, test := range cases {
		t.Run(test.statement, func(t *testing.T) {
			t.Parallel()
			extractor, _, pool := newDesignRoutesExtractor(t)
			_, err := pool.ExecContext(t.Context(), test.statement)
			require.NoError(t, err)
			result, err := extractor.ExtractRows(t.Context(), designRouteTestRows(), test.fields, "en", UserContext{})
			require.Error(t, err)
			require.Nil(t, result)
		})
	}

	extractor, _, _ := newDesignRoutesExtractor(t)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err := extractor.ExtractRows(ctx, designRouteTestRows(),
		&ItemFields{Design: true, PublicRoutes: true, IsCompilesItemOfDay: true}, "en", UserContext{})
	require.ErrorIs(t, err, context.Canceled)
}

func TestItemDesignRoutesBatchParallelLookups(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newDesignRoutesExtractor(t)
	logger := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, logger, 3)
	db.Logger(logger)

	joined := make(chan error, 1)

	go func() {
		_, err := extractor.ExtractRows(t.Context(), designRouteTestRows(),
			&ItemFields{Design: true, PublicRoutes: true, IsCompilesItemOfDay: true}, "en", UserContext{})
		joined <- err
	}()

	unblock()
	require.NoError(t, <-joined)
}

func TestItemPublicRoutesBatchDirectAndZeroID(t *testing.T) {
	t.Parallel()
	extractor, db, pool := newDesignRoutesExtractor(t)
	logger := &itemBatchQueryLogger{}
	db.Logger(logger)

	rows := designRouteTestRows()[7:11]
	result, err := extractor.ExtractRows(t.Context(), rows, &ItemFields{PublicRoutes: true}, "en", UserContext{})
	require.NoError(t, err)
	require.Len(t, result, 4)
	require.Zero(t, logger.queries.Load(), "direct routes need no graph queries")
	db.Logger(nil)

	// With no relations, the legacy unfiltered zero-ID traversal terminates with an empty list.
	_, err = pool.ExecContext(t.Context(), "DELETE FROM pg_temp.item_parent")
	require.NoError(t, err)

	zero := &items.Item{ID: 0, ItemTypeID: schema.ItemTableItemTypeIDVehicle}
	expected, err := extractor.itemPublicRoutes(t.Context(), zero)
	require.NoError(t, err)
	db.Logger(logger)

	result, err = extractor.ExtractRows(
		t.Context(),
		[]*items.Item{zero},
		&ItemFields{PublicRoutes: true},
		"en",
		UserContext{},
	)
	require.NoError(t, err)
	require.Equal(t, expected, result[0].GetPublicRoutes())
	require.EqualValues(t, 1, logger.queries.Load())
}
