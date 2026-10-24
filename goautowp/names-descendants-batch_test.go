package goautowp

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newNamesBatchExtractor(t *testing.T) (*ItemExtractor, *goqu.Database, *sql.DB) {
	t.Helper()
	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE item_language (item_id bigint,language text,name text);
 INSERT INTO item_language VALUES (1,'xx','First'),(1,'en','Common'),(1,'fr','Common'),(1,'ru','Первое'),
 (1,'de',''),(1,'es',NULL),(1,'it',' '),(2,'xx','Second'),(2,'ru','Второе'),(4,'en',''),(4,'ru',NULL);
 `)
	require.NoError(t, err)
	db.Logger(nil)

	return NewItemExtractor(&Container{config: config.LoadConfig("."), goquDB: db}), db, pool
}

func TestItemNamesBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newNamesBatchExtractor(t)
	rows := []*items.Item{
		{ID: 1, NameOnly: "First"},
		{ID: 2, NameOnly: "Second"},
		{ID: 3},
		{ID: 4},
		{ID: 1, NameOnly: "Common"},
	}

	for _, lang := range []string{"en", "ru", "xx", "unknown"} {
		for _, fields := range []*ItemFields{{}, {AltNames: true}, {OtherNames: true}, {AltNames: true, OtherNames: true}} {
			expectedAlt := make([][]*AltName, len(rows))

			expectedOther := make([][]string, len(rows))
			for index, row := range rows {
				var err error

				expectedAlt[index], err = extractor.extractAltNames(t.Context(), fields, row, lang)
				require.NoError(t, err)
				expectedOther[index], err = extractor.extractOtherNames(t.Context(), fields, row)
				require.NoError(t, err)
			}

			original := proto.Clone(fields)
			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			result, err := extractor.ExtractRows(t.Context(), rows, fields, lang, UserContext{})
			require.NoError(t, err)
			db.Logger(nil)

			want := 0
			if fields.GetAltNames() || fields.GetOtherNames() {
				want = 1
			}

			require.EqualValues(t, want, logger.queries.Load())

			for index, row := range result {
				require.Equal(t, rows[index].ID, row.GetId())
				require.ElementsMatch(t, expectedOther[index], row.GetOtherNames())
				require.Len(t, row.GetAltNames(), len(expectedAlt[index]))

				byName := make(map[string][]string)
				for _, name := range row.GetAltNames() {
					byName[name.GetName()] = name.GetLanguages()
				}

				for _, name := range expectedAlt[index] {
					require.ElementsMatch(t, name.GetLanguages(), byName[name.GetName()])
				}

				if fields.GetAltNames() {
					require.NotNil(t, row.GetAltNames())
				}

				if fields.GetOtherNames() {
					require.NotNil(t, row.GetOtherNames())
				}
			}

			require.True(t, proto.Equal(original, fields))
		}
	}
}

func TestItemNamesBatchErrorsAndCancellation(t *testing.T) {
	t.Parallel()
	extractor, db, pool := newNamesBatchExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	started := make(chan struct{}, 1)

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

	done := make(chan error, 1)

	go func() {
		_, err := extractor.ExtractRows(
			ctx,
			[]*items.Item{{ID: 1}},
			&ItemFields{AltNames: true, OtherNames: true},
			"en",
			UserContext{},
		)
		done <- err
	}()

	select {
	case <-started:
	case <-ctx.Done():
		t.Fatal("names batch did not start")
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
	require.NoError(t, connection.Close())
	db.Logger(nil)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.item_language RENAME COLUMN name TO broken")
	require.NoError(t, err)
	result, err := extractor.ExtractRows(
		t.Context(),
		[]*items.Item{{ID: 1}},
		&ItemFields{AltNames: true, OtherNames: true},
		"en",
		UserContext{},
	)
	require.Error(t, err)
	require.Nil(t, result)
	_, err = extractor.ExtractRows(t.Context(), []*items.Item{{ID: 1}}, nil, "en", UserContext{})
	require.NoError(t, err)
	empty, err := extractor.ExtractRows(
		t.Context(),
		nil,
		&ItemFields{AltNames: true, OtherNames: true},
		"en",
		UserContext{},
	)
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
}

func newDescendantPicturesExtractor(t *testing.T) (*ItemParentExtractor, *goqu.Database, *sql.DB) {
	t.Helper()
	fixture, db, pool, _ := newRemainingPictureExtractor(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE item_parent_cache (item_id bigint,parent_id bigint,
 tuning boolean,sport boolean,design boolean,diff integer);
 INSERT INTO item_parent_cache VALUES
 (100,10,false,false,false,0),(200,10,false,false,false,1),(200,20,false,false,false,1),
 (300,20,false,false,false,1),(400,30,false,false,false,1),(500,40,false,false,false,1);
 UPDATE pg_temp.picture SET width=id,height=id+1,filesize=id*100;
 `)
	require.NoError(t, err)

	return NewItemParentExtractor(fixture.container), db, pool
}

func legacyDescendantPictures(
	t *testing.T,
	extractor *ItemParentExtractor,
	id int64,
	request *PicturesRequest,
	lang string,
) *PicturesList {
	t.Helper()
	repository, err := extractor.container.PicturesRepository(t.Context())
	require.NoError(t, err)
	options, err := convertPictureListOptions(request.GetOptions())
	require.NoError(t, err)

	if options == nil {
		options = &query.PictureListOptions{}
	}

	if options.PictureItem == nil {
		options.PictureItem = &query.PictureItemListOptions{}
	}

	if options.PictureItem.ItemParentCacheAncestor == nil {
		options.PictureItem.ItemParentCacheAncestor = &query.ItemParentCacheListOptions{}
	}

	options.PictureItem.ItemParentCacheAncestor.ParentID = id
	rows, _, err := repository.Pictures(
		t.Context(),
		options,
		convertPictureFields(request.GetFields()),
		convertPicturesOrder(request.GetOrder()),
		false,
	)
	require.NoError(t, err)
	values, err := extractor.container.PictureExtractor().
		ExtractRows(t.Context(), rows, request.GetFields(), lang, UserContext{UserID: 7})
	require.NoError(t, err)

	return &PicturesList{Items: values}
}

func TestItemParentDescendantPicturesBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newDescendantPicturesExtractor(t)

	rows := make([]*items.ItemParent, 0, 6)
	for _, id := range []int64{20, 10, 30, 50, 10, 0} {
		rows = append(rows, &items.ItemParent{ItemParentRow: schema.ItemParentRow{ItemID: id, ParentID: 99}})
	}

	for _, order := range []PicturesRequest_Order{
		PicturesRequest_ORDER_CREATED_AT_DESC, PicturesRequest_ORDER_CREATED_AT_ASC,
		PicturesRequest_ORDER_RESOLUTION_DESC, PicturesRequest_ORDER_RESOLUTION_ASC,
	} {
		for _, options := range []*PictureListOptions{
			nil,
			{Status: PictureStatus_PICTURE_STATUS_ACCEPTED},
			{Ids: []int64{1, 3, 5, 6, 7}, PictureItem: &PictureItemListOptions{TypeId: PictureItemType_PICTURE_ITEM_CONTENT}},
		} {
			request := &PicturesRequest{
				Options: options,
				Order:   order,
				Limit:   1,
				Fields:  &PictureFields{Rights: true, Subscribed: true},
			}
			fields := &ItemParentFields{ChildDescendantPictures: request}
			original := proto.Clone(fields)

			expected := make([]*PicturesList, len(rows))
			for index, row := range rows {
				expected[index] = legacyDescendantPictures(t, extractor, row.ItemID, request, "en")
			}

			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", UserContext{UserID: 7})
			require.NoError(t, err)
			db.Logger(nil)

			wantQueries := 2

			for _, group := range expected {
				for _, picture := range group.GetItems() {
					if picture.GetStatus() == PictureStatus_PICTURE_STATUS_INBOX {
						wantQueries = 3
					}
				}
			}

			require.EqualValues(t, wantQueries, logger.queries.Load())

			for index, row := range result {
				require.Equal(t, rows[index].ItemID, row.GetItemId())
				require.True(t, proto.Equal(expected[index], row.GetChildDescendantPictures()))
			}

			require.NotNil(t, result[3].GetChildDescendantPictures().GetItems())
			require.Empty(t, result[3].GetChildDescendantPictures().GetItems())
			require.Greater(
				t,
				len(result[1].GetChildDescendantPictures().GetItems()),
				1,
				"retain the original ignored request limit",
			)
			require.True(t, proto.Equal(original, fields))
		}
	}
}

func TestGroupedPicturesLimitsAndErrors(t *testing.T) {
	t.Parallel()
	extractor, db, pool := newDescendantPicturesExtractor(t)
	repository, err := extractor.container.PicturesRepository(t.Context())
	require.NoError(t, err)

	groups := map[int64]*query.PictureListOptions{
		10: {
			Limit: 1,
			PictureItem: &query.PictureItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{ParentID: 10},
			},
		},
		20: {
			Limit: 2,
			PictureItem: &query.PictureItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{ParentID: 20},
			},
		},
	}
	logger := &itemBatchQueryLogger{}
	db.Logger(logger)

	actual, err := repository.GroupedPictures(t.Context(), groups, nil, pictures.OrderByCreatedAtDesc)
	require.NoError(t, err)
	db.Logger(nil)
	require.EqualValues(t, 1, logger.queries.Load())

	for id, options := range groups {
		expected, _, err := repository.Pictures(t.Context(), options, nil, pictures.OrderByCreatedAtDesc, false)
		require.NoError(t, err)
		require.Equal(t, expected, actual[id])
	}

	logger.queries.Store(0)
	db.Logger(logger)

	empty, err := extractor.ExtractRows(
		t.Context(),
		nil,
		&ItemParentFields{ChildDescendantPictures: &PicturesRequest{}},
		"en",
		UserContext{},
	)
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Zero(t, logger.queries.Load())
	db.Logger(nil)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.item_parent_cache RENAME COLUMN parent_id TO broken")
	require.NoError(t, err)
	result, err := extractor.ExtractRows(
		t.Context(),
		[]*items.ItemParent{{ItemParentRow: schema.ItemParentRow{ItemID: 10}}},
		&ItemParentFields{ChildDescendantPictures: &PicturesRequest{}},
		"en",
		UserContext{},
	)
	require.Error(t, err)
	require.Nil(t, result)
}

func TestDescendantPicturesBatchCancellationAndNestedError(t *testing.T) {
	t.Parallel()
	extractor, db, pool := newDescendantPicturesExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	started := make(chan struct{}, 3)

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

	rows := []*items.ItemParent{{ItemParentRow: schema.ItemParentRow{ItemID: 10, ParentID: 20}}}
	fields := &ItemParentFields{
		Item: &ItemFields{NameText: true}, Parent: &ItemFields{NameText: true},
		ChildDescendantPictures: &PicturesRequest{Fields: &PictureFields{Rights: true}},
	}
	done := make(chan error, 1)

	go func() { _, err := extractor.ExtractRows(ctx, rows, fields, "en", UserContext{}); done <- err }()

	for range 3 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("child, parent and picture selections did not start together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
	require.NoError(t, connection.Close())
	db.Logger(nil)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.picture_moder_vote RENAME COLUMN vote TO broken")
	require.NoError(t, err)
	result, err := extractor.ExtractRows(t.Context(), rows, &ItemParentFields{
		ChildDescendantPictures: &PicturesRequest{Fields: &PictureFields{Rights: true}},
	}, "en", UserContext{})
	require.Error(t, err)
	require.Nil(t, result)
}
