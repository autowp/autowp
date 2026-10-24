package goautowp

import (
	"context"
	"maps"
	"slices"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
)

func (s *ItemParentExtractor) preloadChildDescendantPictures(
	ctx context.Context, rows []*items.ItemParent, request *PicturesRequest, lang string, userCtx UserContext,
) (map[int64]*PicturesList, error) {
	repository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	options, err := convertPictureListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.PictureListOptions{}
	}

	if options.PictureItem == nil {
		options.PictureItem = &query.PictureItemListOptions{}
	}

	if options.PictureItem.ItemParentCacheAncestor == nil {
		options.PictureItem.ItemParentCacheAncestor = &query.ItemParentCacheListOptions{}
	}

	groups := make(map[int64]*query.PictureListOptions)
	for _, row := range rows {
		if _, found := groups[row.ItemID]; found {
			continue
		}

		selected := options.Clone()
		selected.PictureItem.ItemParentCacheAncestor.ParentID = row.ItemID
		groups[row.ItemID] = selected
	}

	selected, err := repository.GroupedPictures(
		ctx,
		groups,
		convertPictureFields(request.GetFields()),
		convertPicturesOrder(request.GetOrder()),
	)
	if err != nil {
		return nil, err
	}

	pictures := make([]*schema.PictureRow, 0)
	seen := make(map[int64]bool)

	for _, id := range slices.Sorted(maps.Keys(groups)) {
		for _, row := range selected[id] {
			if !seen[row.ID] {
				pictures = append(pictures, row)
				seen[row.ID] = true
			}
		}
	}

	extracted, err := s.container.PictureExtractor().ExtractRows(ctx, pictures, request.GetFields(), lang, userCtx)
	if err != nil {
		return nil, err
	}

	byID := make(map[int64]*Picture, len(extracted))
	for _, picture := range extracted {
		byID[picture.GetId()] = picture
	}

	result := make(map[int64]*PicturesList, len(groups))
	for id := range groups {
		result[id] = &PicturesList{Items: make([]*Picture, 0, len(selected[id]))}
		for _, picture := range selected[id] {
			result[id].Items = append(result[id].Items, byID[picture.ID])
		}
	}

	return result, nil
}
