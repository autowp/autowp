package items

import (
	"context"
	"maps"
	"slices"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

// GroupedList uses List's SQL, scanner and natural-name sorting for each group.
// Each query retains its own filters, order and pagination before UNION ALL.
func (s *Repository) GroupedList(
	ctx context.Context, groups map[int64]*query.ItemListOptions, fields *ItemFields, order OrderBy,
) (map[int64][]*Item, error) {
	return s.groupedList(ctx, groups, fields, order, false)
}

// GroupedFirst matches Item's first-row selection for every group without
// issuing a redundant count query for each first page.
func (s *Repository) GroupedFirst(
	ctx context.Context, groups map[int64]*query.ItemListOptions, fields *ItemFields,
) (map[int64][]*Item, error) {
	return s.groupedList(ctx, groups, fields, OrderByNone, true)
}

func (s *Repository) groupedList(
	ctx context.Context, groups map[int64]*query.ItemListOptions, fields *ItemFields, order OrderBy, first bool,
) (map[int64][]*Item, error) {
	result := make(map[int64][]*Item, len(groups))

	var combined *goqu.SelectDataset

	for _, id := range slices.Sorted(maps.Keys(groups)) {
		result[id] = nil

		options := groups[id].Clone()
		if first {
			options.Limit = 1
			options.Page = 0
		}

		selection, _, err := s.listSelect(ctx, options, fields, order, false, first)
		if err != nil {
			return nil, err
		}

		var orderColumns []interface{}

		if selection.GetClauses().HasOrder() {
			for _, column := range selection.GetClauses().Order().Columns() {
				orderColumns = append(orderColumns, column)
			}
		}

		selection = selection.SelectAppend(goqu.V(id).As("batch_group"),
			goqu.ROW_NUMBER().Over(goqu.W().OrderBy(orderColumns...)).As("batch_position"))

		part := s.db.From(selection.As("selection")).Select(goqu.Star())
		if combined == nil {
			combined = part
		} else {
			combined = combined.UnionAll(part)
		}
	}

	if combined == nil {
		return result, nil
	}

	selection := s.db.From(combined.As("batch")).Select(goqu.Star()).
		Order(goqu.C("batch_group").Asc(), goqu.C("batch_position").Asc())

	rows, err := selection.Executor().QueryContext(ctx)
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	selected, groupIDs, err := scanItemRows(rows)
	if err != nil {
		return nil, err
	}

	for index, row := range selected {
		result[groupIDs[index]] = append(result[groupIDs[index]], row)
	}

	for id, options := range groups {
		if options.SortByName {
			sortItemsByName(result[id], options.Language)
		}
	}

	return result, nil
}
