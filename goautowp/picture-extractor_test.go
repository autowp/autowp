package goautowp

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
)

func pathTreeTestItem(id int64, catname string) *items.Item {
	return &items.Item{
		ItemRow: schema.ItemRow{
			ID:         id,
			Catname:    sql.NullString{String: catname, Valid: true},
			ItemTypeID: schema.ItemTableItemTypeIDVehicle,
		},
	}
}

func pathTreeTestParent(itemID int64, parentID int64, catname string) *items.ItemParent {
	return &items.ItemParent{
		ItemParentRow: schema.ItemParentRow{
			ItemID:   itemID,
			ParentID: parentID,
			Catname:  catname,
		},
	}
}

func newPathTreeBuilder(graph *pathTreeGraph, targetItemID int64) *pathTreeBuilder {
	return &pathTreeBuilder{
		graph:        graph,
		built:        make(map[int64]*PathTreeItem),
		building:     make(map[int64]bool),
		targetItemID: targetItemID,
	}
}

// A node reachable through two parents is built once and shared, rather than expanded per route.
func TestPathTreeBuilderSharesNodeReachedTwice(t *testing.T) {
	t.Parallel()

	graph := &pathTreeGraph{
		items: map[int64]*items.Item{
			1: pathTreeTestItem(1, "car"),
			2: pathTreeTestItem(2, "left"),
			3: pathTreeTestItem(3, "right"),
			4: pathTreeTestItem(4, "root"),
		},
		parents: map[int64][]*items.ItemParent{
			1: {pathTreeTestParent(1, 2, "via-left"), pathTreeTestParent(1, 3, "via-right")},
			2: {pathTreeTestParent(2, 4, "left-root")},
			3: {pathTreeTestParent(3, 4, "right-root")},
		},
	}

	route := newPathTreeBuilder(graph, 0).route(1)

	require.NotNil(t, route)
	require.Equal(t, "car", route.GetCatname())
	require.Len(t, route.GetParents(), 2)
	require.Equal(t, "via-left", route.GetParents()[0].GetCatname())
	require.Equal(t, "via-right", route.GetParents()[1].GetCatname())

	left := route.GetParents()[0].GetItem()
	right := route.GetParents()[1].GetItem()
	require.Equal(t, "root", left.GetParents()[0].GetItem().GetCatname())
	require.Same(t, left.GetParents()[0].GetItem(), right.GetParents()[0].GetItem())
}

// With a target, routes that reach neither it nor anything above it are dropped.
func TestPathTreeBuilderDropsRoutesMissingTheTarget(t *testing.T) {
	t.Parallel()

	graph := &pathTreeGraph{
		items: map[int64]*items.Item{
			1: pathTreeTestItem(1, "car"),
			2: pathTreeTestItem(2, "wanted"),
			3: pathTreeTestItem(3, "unrelated"),
		},
		parents: map[int64][]*items.ItemParent{
			1: {pathTreeTestParent(1, 2, "via-wanted"), pathTreeTestParent(1, 3, "via-unrelated")},
		},
	}

	route := newPathTreeBuilder(graph, 2).route(1)

	require.NotNil(t, route)
	require.Len(t, route.GetParents(), 1)
	require.Equal(t, "via-wanted", route.GetParents()[0].GetCatname())

	// Nothing leads to the target at all: the whole route goes.
	require.Nil(t, newPathTreeBuilder(graph, 4).route(1))
}

// An item the graph never got a row for - deleted between the two queries, say - drops its route.
func TestPathTreeBuilderDropsMissingItem(t *testing.T) {
	t.Parallel()

	graph := &pathTreeGraph{
		items: map[int64]*items.Item{
			1: pathTreeTestItem(1, "car"),
		},
		parents: map[int64][]*items.ItemParent{
			1: {pathTreeTestParent(1, 2, "via-gone")},
		},
	}

	route := newPathTreeBuilder(graph, 0).route(1)

	require.NotNil(t, route)
	require.Empty(t, route.GetParents())
	require.Nil(t, newPathTreeBuilder(graph, 0).route(2))
}

// A cycle in item_parent terminates instead of recursing until the stack runs out.
func TestPathTreeBuilderTerminatesOnCycle(t *testing.T) {
	t.Parallel()

	graph := &pathTreeGraph{
		items: map[int64]*items.Item{
			1: pathTreeTestItem(1, "one"),
			2: pathTreeTestItem(2, "two"),
		},
		parents: map[int64][]*items.ItemParent{
			1: {pathTreeTestParent(1, 2, "up")},
			2: {pathTreeTestParent(2, 1, "back")},
		},
	}

	route := newPathTreeBuilder(graph, 0).route(1)

	require.NotNil(t, route)
	require.Len(t, route.GetParents(), 1)
	require.Empty(t, route.GetParents()[0].GetItem().GetParents())
}
