package goautowp

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

// Keep the former depth-first walk as a reference for sibling order and formatting.
func legacyCarTree(t *testing.T, server *ItemsGRPCServer, car *items.Item, lang string,
	parentType schema.ItemParentType,
) *TreeItem {
	t.Helper()

	name, err := server.formatItemNameHTML(car, lang)
	require.NoError(t, err)

	result := &TreeItem{Id: car.ID, NameHtml: name, Type: extractItemParentType(parentType), Childs: []*TreeItem{}}
	relations, _, err := server.repository.ItemParents(t.Context(), &query.ItemParentListOptions{ParentID: car.ID},
		items.ItemParentFields{}, items.ItemParentOrderByAuto)
	require.NoError(t, err)

	for _, relation := range relations {
		child, err := server.repository.Item(
			t.Context(),
			&query.ItemListOptions{ItemID: relation.ItemID},
			&items.ItemFields{NameHTML: true},
		)
		require.NoError(t, err)
		result.Childs = append(result.Childs, legacyCarTree(t, server, child, lang, relation.Type))
	}

	return result
}

func TestCarTreeBatchesLevels(t *testing.T) {
	t.Parallel()
	server, db, _, rootID, _ := newBrandSectionsTestServer(t)
	children, _, err := server.repository.ItemParents(t.Context(), &query.ItemParentListOptions{ParentID: rootID},
		items.ItemParentFields{}, items.ItemParentOrderByAuto)
	require.NoError(t, err)
	_, err = db.Update(schema.ItemTable).Set(goqu.Record{schema.ItemTableIsGroupColName: true}).
		Where(schema.ItemTableIDCol.In(children[0].ItemID, children[1].ItemID)).Executor().ExecContext(t.Context())
	require.NoError(t, err)
	shared := createItem(
		t,
		conn,
		cnt,
		&Item{Name: "Shared <child>", IsGroup: true, ItemTypeId: ItemType_ITEM_TYPE_VEHICLE, BeginYear: 1990},
	)

	leaf := createItem(t, conn, cnt, &Item{Name: "Leaf", ItemTypeId: ItemType_ITEM_TYPE_VEHICLE, EndYear: 2000})
	for index, parent := range children[:2] {
		_, err = server.repository.CreateItemParent(
			t.Context(),
			shared,
			parent.ItemID,
			schema.ItemParentType(index+1),
			"shared",
		)
		require.NoError(t, err)
	}

	_, err = server.repository.CreateItemParent(t.Context(), leaf, shared, schema.ItemParentTypeDefault, "leaf")
	require.NoError(t, err)
	car, err := server.repository.Item(t.Context(), &query.ItemListOptions{ItemID: rootID}, nil)
	require.NoError(t, err)

	for _, lang := range []string{"en", "ru"} {
		expected := legacyCarTree(t, server, car, lang, schema.ItemParentTypeDefault)
		logger := &itemBatchQueryLogger{}
		db.Logger(logger)

		actual, err := server.carTreeWalk(t.Context(), car, lang)
		require.NoError(t, err)
		db.Logger(nil)
		require.True(t, proto.Equal(expected, actual))
		require.EqualValues(t, 7, logger.queries.Load(), "four relation batches and three item batches")

		first := actual.GetChilds()[0].GetChilds()[0]
		second := actual.GetChilds()[1].GetChilds()[0]

		require.Equal(t, shared, first.GetId())
		require.Equal(t, shared, second.GetId())
		require.NotSame(t, first, second)
		require.NotSame(t, first.GetChilds()[0], second.GetChilds()[0])
		require.NotEqual(t, first.GetType(), second.GetType())
	}

	leafCar, err := server.repository.Item(t.Context(), &query.ItemListOptions{ItemID: leaf}, nil)
	require.NoError(t, err)

	logger := &itemBatchQueryLogger{}
	db.Logger(logger)

	result, err := server.carTreeWalk(t.Context(), leafCar, "en")
	require.NoError(t, err)
	require.NotNil(t, result.GetChilds())
	require.Empty(t, result.GetChilds())
	require.EqualValues(t, 1, logger.queries.Load())
	db.Logger(nil)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	result, err = server.carTreeWalk(ctx, car, "en")
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, result)
}

func TestCarTreeMissingBatchedChild(t *testing.T) {
	t.Parallel()
	server, db, pool, rootID, _ := newBrandSectionsTestServer(t)
	car, err := server.repository.Item(t.Context(), &query.ItemListOptions{ItemID: rootID}, nil)
	require.NoError(t, err)
	children, _, err := server.repository.ItemParents(t.Context(), &query.ItemParentListOptions{ParentID: rootID},
		items.ItemParentFields{}, items.ItemParentOrderByAuto)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	_, err = pool.ExecContext(t.Context(), `CREATE TEMP TABLE item (LIKE public.item INCLUDING ALL);
 INSERT INTO pg_temp.item SELECT * FROM public.item;`)
	require.NoError(t, err)

	logger := &itemBatchQueryLogger{}
	logger.beforeQuery = func() {
		if logger.queries.Load() == 2 {
			// Emulate a child disappearing after its relation was fetched. Only the connection-local copy changes.
			_, err := pool.ExecContext(t.Context(), "DELETE FROM pg_temp.item WHERE id=$1", children[0].ItemID)
			require.NoError(t, err)
		}
	}
	db.Logger(logger)

	result, err := server.carTreeWalk(t.Context(), car, "en")
	require.ErrorIs(t, err, items.ErrItemNotFound)
	require.Nil(t, result)
}
