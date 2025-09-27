package goautowp

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
)

type ItemParentExtractor struct {
	container *Container
}

func NewItemParentExtractor(container *Container) *ItemParentExtractor {
	return &ItemParentExtractor{container: container}
}

func (s *ItemParentExtractor) ExtractRow(
	ctx context.Context,
	row *items.ItemParent,
	fields *ItemParentFields,
	lang string,
	userCtx UserContext,
) (*ItemParent, error) {
	res, err := s.ExtractRows(ctx, []*items.ItemParent{row}, fields, lang, userCtx)

	return res[0], err
}

func (s *ItemParentExtractor) ExtractRows(
	ctx context.Context,
	rows []*items.ItemParent,
	fields *ItemParentFields,
	lang string,
	userCtx UserContext,
) ([]*ItemParent, error) {
	var err error

	itemFields := fields.GetItem()
	itemsMap := make(map[int64]*items.Item)

	if itemFields != nil && len(rows) > 0 {
		ids := make([]int64, 0, len(rows))
		for _, row := range rows {
			ids = append(ids, row.ItemID)
		}

		itemsMap, err = s.prefetchItems(ctx, ids, lang, convertItemFields(itemFields))
		if err != nil {
			return nil, err
		}
	}

	parentFields := fields.GetParent()
	parentsMap := make(map[int64]*items.Item, len(rows))

	if parentFields != nil && len(rows) > 0 {
		ids := make([]int64, 0, len(rows))
		for _, row := range rows {
			ids = append(ids, row.ParentID)
		}

		parentsMap, err = s.prefetchItems(ctx, ids, lang, convertItemFields(parentFields))
		if err != nil {
			return nil, err
		}
	}

	res := make([]*ItemParent, 0, len(rows))

	itemExtractor := s.container.ItemExtractor()

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		resRow := &ItemParent{
			ItemId:   row.ItemID,
			ParentId: row.ParentID,
			Type:     extractItemParentType(row.Type),
			Catname:  row.Catname,
		}

		if itemFields != nil {
			itemRow, ok := itemsMap[row.ItemID]
			if ok && itemRow != nil {
				resRow.Item, err = itemExtractor.Extract(ctx, itemRow, itemFields, lang, userCtx)
				if err != nil {
					return nil, err
				}
			}
		}

		if parentFields != nil {
			itemRow, ok := parentsMap[row.ParentID]
			if ok && itemRow != nil {
				resRow.Parent, err = itemExtractor.Extract(
					ctx,
					itemRow,
					parentFields,
					lang,
					userCtx,
				)
				if err != nil {
					return nil, err
				}
			}
		}

		duplicateParentFields := fields.GetDuplicateParent()
		if duplicateParentFields != nil {
			duplicateRow, err := itemRepository.Item(ctx, &query.ItemListOptions{
				ExcludeID: row.ParentID,
				ItemParentChild: &query.ItemParentListOptions{
					ItemID: row.ItemID,
					Type:   schema.ItemParentTypeDefault,
				},
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
					ParentID:  row.ParentID,
					StockOnly: true,
				},
			}, convertItemFields(duplicateParentFields))
			if err != nil && !errors.Is(err, items.ErrItemNotFound) {
				return nil, err
			}

			if err == nil {
				resRow.DuplicateParent, err = itemExtractor.Extract(
					ctx, duplicateRow, duplicateParentFields, lang, userCtx,
				)
				if err != nil {
					return nil, err
				}
			}
		}

		duplicateChildFields := fields.GetDuplicateChild()
		if duplicateChildFields != nil {
			duplicateRow, err := itemRepository.Item(ctx, &query.ItemListOptions{
				ExcludeID: row.ItemID,
				ItemParentParent: &query.ItemParentListOptions{
					ParentID: row.ParentID,
					Type:     row.Type,
				},
				ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
					ItemID: row.ItemID,
				},
			}, convertItemFields(duplicateChildFields))
			if err != nil && !errors.Is(err, items.ErrItemNotFound) {
				return nil, err
			}

			if err == nil {
				resRow.DuplicateChild, err = itemExtractor.Extract(
					ctx, duplicateRow, duplicateChildFields, lang, userCtx,
				)
				if err != nil {
					return nil, err
				}
			}
		}

		resRow.ChildDescendantPictures, err = s.extractChildDescendantPictures(
			ctx,
			row,
			fields,
			lang,
			userCtx,
		)
		if err != nil {
			return nil, err
		}

		res = append(res, resRow)
	}

	return res, nil
}

func (s *ItemParentExtractor) prefetchItems(
	ctx context.Context, ids []int64, lang string, fields *items.ItemFields,
) (map[int64]*items.Item, error) {
	itemsRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	itemRows, _, err := itemsRepository.List(ctx, &query.ItemListOptions{
		ItemIDs:  ids,
		Language: lang,
	}, fields, items.OrderByNone, false)
	if err != nil {
		return nil, err
	}

	itemsMap := make(map[int64]*items.Item, len(itemRows))

	for _, itemRow := range itemRows {
		itemsMap[itemRow.ID] = itemRow
	}

	return itemsMap, nil
}

func (s *ItemParentExtractor) extractChildDescendantPictures(
	ctx context.Context,
	row *items.ItemParent,
	fields *ItemParentFields,
	lang string,
	userCtx UserContext,
) (*PicturesList, error) {
	request := fields.GetChildDescendantPictures()
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	picturesRepo, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	pictureFields := convertPictureFields(request.GetFields())
	pictureOrder := convertPicturesOrder(request.GetOrder())

	pictureListOptions, err := convertPictureListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if pictureListOptions == nil {
		pictureListOptions = &query.PictureListOptions{}
	}

	if pictureListOptions.PictureItem == nil {
		pictureListOptions.PictureItem = &query.PictureItemListOptions{}
	}

	if pictureListOptions.PictureItem.ItemParentCacheAncestor == nil {
		pictureListOptions.PictureItem.ItemParentCacheAncestor = &query.ItemParentCacheListOptions{}
	}

	pictureListOptions.PictureItem.ItemParentCacheAncestor.ParentID = row.ItemID

	pictureRows, _, err := picturesRepo.Pictures(
		ctx,
		pictureListOptions,
		pictureFields,
		pictureOrder,
		false,
	)
	if err != nil {
		return nil, err
	}

	extracted, err := s.container.PictureExtractor().ExtractRows(
		ctx, pictureRows, request.GetFields(), lang, userCtx,
	)
	if err != nil {
		return nil, err
	}

	return &PicturesList{
		Items: extracted,
	}, nil
}
