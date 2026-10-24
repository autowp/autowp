package goautowp

import (
	"context"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
)

func (s *ItemParentExtractor) preloadDuplicates(
	ctx context.Context, rows []*items.ItemParent, fields *ItemFields, parent bool, lang string, user UserContext,
) (map[int64]*Item, error) {
	repository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	groups := make(map[int64]*query.ItemListOptions, len(rows))
	for index, row := range rows {
		groups[int64(index)] = duplicateItemOptions(row, parent)
	}

	selected, err := repository.GroupedFirst(ctx, groups, convertItemFields(fields))
	if err != nil {
		return nil, err
	}

	itemRows := make([]*items.Item, 0, len(rows))

	indices := make([]int64, 0, len(rows))
	for index := range rows {
		key := int64(index)
		if len(selected[key]) > 0 {
			itemRows = append(itemRows, selected[key][0])
			indices = append(indices, key)
		}
	}

	extracted, err := s.container.ItemExtractor().ExtractRows(ctx, itemRows, fields, lang, user)
	if err != nil {
		return nil, err
	}

	result := make(map[int64]*Item, len(extracted))
	for index, row := range extracted {
		result[indices[index]] = row
	}

	return result, nil
}

func duplicateItemOptions(row *items.ItemParent, parent bool) *query.ItemListOptions {
	if parent {
		return &query.ItemListOptions{
			ExcludeID: row.ParentID,
			ItemParentChild: &query.ItemParentListOptions{
				ItemID: row.ItemID,
				Type:   schema.ItemParentTypeDefault,
			},
			ItemParentCacheAncestor: &query.ItemParentCacheListOptions{ParentID: row.ParentID, StockOnly: true},
		}
	}

	return &query.ItemListOptions{
		ExcludeID:                 row.ItemID,
		ItemParentParent:          &query.ItemParentListOptions{ParentID: row.ParentID, Type: row.Type},
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{ItemID: row.ItemID},
	}
}
