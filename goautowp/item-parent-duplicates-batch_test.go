package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"testing"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newDuplicateParentExtractor(t *testing.T) (*ItemParentExtractor, *goqu.Database, *sql.DB) {
	t.Helper()
	fixture, db, pool := newConnectedItemExtractor(t)
	_, err := pool.ExecContext(t.Context(), `
 TRUNCATE pg_temp.item_parent,pg_temp.item_parent_cache;
 ALTER TABLE pg_temp.item_parent_cache ADD COLUMN tuning boolean DEFAULT false;
 ALTER TABLE pg_temp.item_parent_cache ADD COLUMN sport boolean DEFAULT false;
 INSERT INTO item_parent (item_id,parent_id,catname,type) VALUES
 (10,100,'original',0),(10,101,'tuned-parent',1),(10,200,'tuned',0),(10,300,'sport',0),
 (10,500,'null-stock',0),(10,501,'other-parent',2),(10,9999,'missing',0),
 (11,100,'other-child',0),(12,100,'tuning-a',1),(13,100,'sport',2),(14,100,'tuning-b',1),(20,100,'another',0);
 INSERT INTO item_parent_cache (item_id,parent_id,diff,tuning,sport) VALUES
 (101,100,1,false,false),(200,100,1,true,false),(300,100,1,false,true),
 (500,100,1,NULL,false),(501,100,1,false,false),(9999,100,1,false,false),
 (10,10,0,false,false),(10,11,1,false,false),(10,12,1,false,false),
 (10,13,1,false,false),(10,14,1,false,false),(10,20,1,false,false),(11,10,1,false,false);
 `)
	require.NoError(t, err)

	return NewItemParentExtractor(fixture.container), db, pool
}

func duplicateParentRows() []*items.ItemParent {
	rows := [...]*items.ItemParent{
		{
			ItemParentRow: schema.ItemParentRow{
				ItemID:   10,
				ParentID: 100,
				Type:     schema.ItemParentTypeDefault,
				Catname:  "original",
			},
		},
		{
			ItemParentRow: schema.ItemParentRow{
				ItemID:   10,
				ParentID: 100,
				Type:     schema.ItemParentTypeTuning,
				Catname:  "tuning",
			},
		},
		{
			ItemParentRow: schema.ItemParentRow{
				ItemID:   10,
				ParentID: 100,
				Type:     schema.ItemParentTypeSport,
				Catname:  "sport",
			},
		},
		{ItemParentRow: schema.ItemParentRow{ItemID: 11, ParentID: 100, Type: schema.ItemParentTypeDefault}},
		{ItemParentRow: schema.ItemParentRow{ItemID: 999, ParentID: 100}},
		{ItemParentRow: schema.ItemParentRow{ItemID: 10, ParentID: 999}},
		{ItemParentRow: schema.ItemParentRow{ItemID: 0, ParentID: 100}},
	}

	return append(rows[:], rows[0], rows[1])
}

// Reproduce the previous per-row Item + Extract calls with their original filters.
func legacyParentDuplicate(
	t *testing.T,
	extractor *ItemParentExtractor,
	row *items.ItemParent,
	fields *ItemFields,
	parent bool,
	lang string,
	user UserContext,
) *Item {
	t.Helper()

	if fields == nil {
		return nil
	}

	options := &query.ItemListOptions{
		ExcludeID:                 row.ItemID,
		ItemParentParent:          &query.ItemParentListOptions{ParentID: row.ParentID, Type: row.Type},
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{ItemID: row.ItemID},
	}
	if parent {
		options = &query.ItemListOptions{
			ExcludeID: row.ParentID,
			ItemParentChild: &query.ItemParentListOptions{
				ItemID: row.ItemID,
				Type:   schema.ItemParentTypeDefault,
			},
			ItemParentCacheAncestor: &query.ItemParentCacheListOptions{ParentID: row.ParentID, StockOnly: true},
		}
	}

	repository, err := extractor.container.ItemsRepository(t.Context())
	require.NoError(t, err)

	selected, err := repository.Item(t.Context(), options, convertItemFields(fields))
	if errors.Is(err, items.ErrItemNotFound) {
		return nil
	}

	require.NoError(t, err)
	result, err := extractor.container.ItemExtractor().Extract(t.Context(), selected, fields, lang, user)
	require.NoError(t, err)

	return result
}

func TestItemParentDuplicatesBatchMatchesIndividual(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newDuplicateParentExtractor(t)
	rows := duplicateParentRows()
	user := UserContext{UserID: 7}

	for _, lang := range []string{"en", "ru"} {
		for _, fields := range []*ItemParentFields{
			{},
			{DuplicateParent: &ItemFields{}},
			{DuplicateChild: &ItemFields{}},
			{
				DuplicateParent: &ItemFields{NameOnly: true, Location: true, Subscription: true},
				DuplicateChild:  &ItemFields{NameOnly: true, Location: true},
			},
		} {
			expectedParents := make([]*Item, len(rows))

			expectedChildren := make([]*Item, len(rows))
			for index, row := range rows {
				expectedParents[index] = legacyParentDuplicate(
					t,
					extractor,
					row,
					fields.GetDuplicateParent(),
					true,
					lang,
					user,
				)
				expectedChildren[index] = legacyParentDuplicate(
					t,
					extractor,
					row,
					fields.GetDuplicateChild(),
					false,
					lang,
					user,
				)
			}

			selected := proto.Clone(fields)
			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			actual, err := extractor.ExtractRows(t.Context(), rows, fields, lang, user)
			require.NoError(t, err)
			db.Logger(nil)

			queries := 0

			for _, itemFields := range []*ItemFields{fields.GetDuplicateParent(), fields.GetDuplicateChild()} {
				if itemFields == nil {
					continue
				}

				queries++
				if itemFields.GetLocation() {
					queries++
				}

				if itemFields.GetSubscription() {
					queries++
				}
			}

			require.EqualValues(t, queries, logger.queries.Load(), "one selection per field, with no per-row COUNTs")
			require.True(t, proto.Equal(selected, fields))
			require.Len(t, actual, len(rows))

			for index, row := range actual {
				require.Equal(t, rows[index].ItemID, row.GetItemId())
				require.Equal(t, rows[index].ParentID, row.GetParentId())
				require.Equal(t, rows[index].Catname, row.GetCatname())
				require.True(
					t,
					proto.Equal(expectedParents[index], row.GetDuplicateParent()),
					"parent at row %d",
					index,
				)
				require.True(t, proto.Equal(expectedChildren[index], row.GetDuplicateChild()), "child at row %d", index)
			}

			if fields.GetDuplicateParent() != nil {
				require.Contains(
					t,
					[]int64{101, 501},
					actual[0].GetDuplicateParent().GetId(),
					"stock only, without excluding tuning relation types",
				)
				require.Nil(t, actual[4].GetDuplicateParent())
				require.Nil(t, actual[5].GetDuplicateParent())
			}

			if fields.GetDuplicateChild() != nil {
				require.Contains(t, []int64{12, 14}, actual[1].GetDuplicateChild().GetId())
				require.EqualValues(
					t,
					13,
					actual[2].GetDuplicateChild().GetId(),
					"same pair with a different type stays independent",
				)
				require.Nil(t, actual[4].GetDuplicateChild())
				require.Nil(t, actual[5].GetDuplicateChild())
			}
		}
	}
}

func TestItemParentDuplicatesBatchParallelAndEmpty(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newDuplicateParentExtractor(t)
	fields := &ItemParentFields{
		DuplicateParent: &ItemFields{Location: true},
		DuplicateChild:  &ItemFields{Location: true},
	}
	logger := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, logger, 2)
	db.Logger(logger)

	joined := make(chan error, 1)

	go func() {
		_, err := extractor.ExtractRows(t.Context(), duplicateParentRows(), fields, "en", UserContext{})
		joined <- err
	}()

	unblock()
	require.NoError(t, <-joined)
	logger.queries.Store(0)

	empty, err := extractor.ExtractRows(t.Context(), nil, fields, "en", UserContext{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
	require.Zero(t, logger.queries.Load())
}

func TestItemParentDuplicatesBatchErrors(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		statement string
		fields    *ItemParentFields
	}{
		{
			"ALTER TABLE pg_temp.item_parent_cache RENAME COLUMN tuning TO broken",
			&ItemParentFields{DuplicateParent: &ItemFields{}},
		},
		{
			"ALTER TABLE pg_temp.item_parent RENAME COLUMN parent_id TO broken",
			&ItemParentFields{DuplicateChild: &ItemFields{}},
		},
		{
			"ALTER TABLE pg_temp.item_point RENAME COLUMN point TO broken",
			&ItemParentFields{DuplicateParent: &ItemFields{Location: true}, DuplicateChild: &ItemFields{Location: true}},
		},
	} {
		t.Run(test.statement, func(t *testing.T) {
			t.Parallel()
			extractor, _, pool := newDuplicateParentExtractor(t)
			_, err := pool.ExecContext(t.Context(), test.statement)
			require.NoError(t, err)
			actual, err := extractor.ExtractRows(t.Context(), duplicateParentRows(), test.fields, "en", UserContext{})
			require.Error(t, err)
			require.Nil(t, actual)
		})
	}

	extractor, _, _ := newDuplicateParentExtractor(t)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	actual, err := extractor.ExtractRows(ctx, duplicateParentRows(),
		&ItemParentFields{DuplicateParent: &ItemFields{}, DuplicateChild: &ItemFields{}}, "en", UserContext{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, actual)
}
