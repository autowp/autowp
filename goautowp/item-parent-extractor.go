package goautowp

import (
	"context"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
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
	itemFields := fields.GetItem()
	parentFields := fields.GetParent()

	var (
		itemsMap           map[int64]*Item
		parentsMap         map[int64]*Item
		lookups            []func(context.Context) error
		descendantPictures map[int64]*PicturesList
		duplicateParents   map[int64]*Item
		duplicateChildren  map[int64]*Item
	)

	// Child and parent selections are independent. Share the existing lookup
	// runner so nested extraction keeps its concurrency limit and cancellation.
	if itemFields != nil && len(rows) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			ids := make([]int64, 0, len(rows))
			for _, row := range rows {
				ids = append(ids, row.ItemID)
			}

			var err error

			itemsMap, err = s.prefetchItems(ctx, ids, lang, itemFields, userCtx)

			return err
		})
	}

	if parentFields != nil && len(rows) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			ids := make([]int64, 0, len(rows))
			for _, row := range rows {
				ids = append(ids, row.ParentID)
			}

			var err error

			parentsMap, err = s.prefetchItems(ctx, ids, lang, parentFields, userCtx)

			return err
		})
	}

	if request := fields.GetChildDescendantPictures(); request != nil && len(rows) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			ctx = context.WithValue(ctx, pictureExtractionParallelismKey{}, 1)
			descendantPictures, err = s.preloadChildDescendantPictures(ctx, rows, request, lang, userCtx)

			return err
		})
	}

	for _, selection := range []struct {
		fields *ItemFields
		parent bool
		target *map[int64]*Item
	}{
		{fields.GetDuplicateParent(), true, &duplicateParents},
		{fields.GetDuplicateChild(), false, &duplicateChildren},
	} {
		if selection.fields == nil || len(rows) == 0 {
			continue
		}

		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			ctx = context.WithValue(ctx, pictureExtractionParallelismKey{}, 1)
			*selection.target, err = s.preloadDuplicates(ctx, rows, selection.fields, selection.parent, lang, userCtx)

			return err
		})
	}

	if err := runItemLookups(ctx, lookups); err != nil {
		return nil, err
	}

	res := make([]*ItemParent, 0, len(rows))

	_, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	for index, row := range rows {
		resRow := &ItemParent{
			ItemId:   row.ItemID,
			ParentId: row.ParentID,
			Type:     extractItemParentType(row.Type),
			Catname:  row.Catname,
		}

		resRow.Item = itemsMap[row.ItemID]
		resRow.Parent = parentsMap[row.ParentID]

		resRow.DuplicateParent = duplicateParents[int64(index)]
		resRow.DuplicateChild = duplicateChildren[int64(index)]

		if fields.GetChildDescendantPictures() != nil {
			resRow.ChildDescendantPictures = descendantPictures[row.ItemID]
		}

		res = append(res, resRow)
	}

	return res, nil
}

func (s *ItemParentExtractor) prefetchItems(
	ctx context.Context, ids []int64, lang string, fields *ItemFields, userCtx UserContext,
) (map[int64]*Item, error) {
	itemsRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	itemRows, _, err := itemsRepository.List(ctx, &query.ItemListOptions{
		ItemIDs:  ids,
		Language: lang,
	}, convertItemFields(fields), items.OrderByNone, false)
	if err != nil {
		return nil, err
	}

	// The two relation branches share the outer lookup budget. Nested pictures
	// stay inline rather than multiplying that budget for each branch.
	ctx = context.WithValue(ctx, pictureExtractionParallelismKey{}, 1)

	extracted, err := s.container.ItemExtractor().ExtractRows(ctx, itemRows, fields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	itemsMap := make(map[int64]*Item, len(extracted))
	for _, item := range extracted {
		itemsMap[item.GetId()] = item
	}

	return itemsMap, nil
}
