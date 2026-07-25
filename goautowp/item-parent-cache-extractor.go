package goautowp

import (
	"context"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
)

type ItemParentCacheExtractor struct {
	container *Container
}

func NewItemParentCacheExtractor(container *Container) *ItemParentCacheExtractor {
	return &ItemParentCacheExtractor{container: container}
}

func (s *ItemParentCacheExtractor) ExtractRows(
	ctx context.Context,
	rows []*schema.ItemParentCacheRow,
	fields *ItemParentCacheFields,
	lang string,
	userCtx UserContext,
) ([]*ItemParentCache, error) {
	parentIDs := make([]int64, 0, len(rows))

	for _, row := range rows {
		if row.ParentID != 0 {
			parentIDs = append(parentIDs, row.ParentID)
		}
	}

	itemRequest := fields.GetParentItem()

	parentItemRows, err := s.preloadItems(ctx, itemRequest, parentIDs, lang, userCtx)
	if err != nil {
		return nil, err
	}

	result := make([]*ItemParentCache, 0, len(rows))

	for _, row := range rows {
		resultRow := &ItemParentCache{
			ItemId:   row.ItemID,
			ParentId: row.ParentID,
		}

		if itemRequest != nil {
			resultRow.ParentItem = parentItemRows[row.ParentID]
		}

		result = append(result, resultRow)
	}

	return result, nil
}

func (s *ItemParentCacheExtractor) preloadItems(
	ctx context.Context, request *ItemsRequest, ids []int64, lang string, userCtx UserContext,
) (map[int64]*Item, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64]*Item, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	itemFields := request.GetFields()

	options, err := convertItemListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.ItemListOptions{}
	}

	options.ItemIDs = ids
	options.Language = lang

	itemsRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	itemExtractor := s.container.ItemExtractor()

	rows, _, err := itemsRepository.List(
		ctx,
		options,
		convertItemFields(itemFields),
		items.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ID], err = itemExtractor.Extract(ctx, row, itemFields, lang, userCtx)
		if err != nil {
			return nil, err
		}
	}

	return result, nil
}
