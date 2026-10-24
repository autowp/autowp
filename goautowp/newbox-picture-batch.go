package goautowp

import (
	"context"
	"maps"
	"slices"

	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
)

func (s *PicturesGRPCServer) extractNewboxPictures(
	ctx context.Context,
	groups []*NewboxGroupDraft,
	looseFields, itemFields *PictureFields,
	lang string,
	user UserContext,
) (map[int64][]*Picture, error) {
	selections := make(map[int64]*query.PictureListOptions)
	loose := make(map[int64][]*schema.PictureRow)

	for index, group := range groups {
		key := int64(index)
		if group.Type != newboxGroupTypeItem {
			loose[key] = group.Pictures

			continue
		}

		ids := make([]int64, 0, len(group.Pictures))
		for _, picture := range group.Pictures {
			ids = append(ids, picture.ID)
		}

		selections[key] = &query.PictureListOptions{
			IDs: ids, Status: schema.PictureStatusAccepted,
			PictureItem: &query.PictureItemListOptions{ItemID: group.ItemID}, Limit: newboxPicturesPerLine,
		}
	}

	var (
		itemPictures, loosePictures map[int64][]*Picture
		lookups                     []func(context.Context) error
	)
	if len(selections) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			selected, err := s.repository.GroupedPictures(
				ctx,
				selections,
				convertPictureFields(itemFields),
				pictures.OrderByAcceptDatetimeDesc,
			)
			if err != nil {
				return err
			}
			// Preserve nonnil empty lists for groups whose selection matched nothing.
			for key := range selections {
				if selected[key] == nil {
					selected[key] = []*schema.PictureRow{}
				}
			}

			itemPictures, err = s.extractNewboxPictureRows(ctx, selected, itemFields, lang, user)

			return err
		})
	}

	if len(loose) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			loosePictures, err = s.extractNewboxPictureRows(ctx, loose, looseFields, lang, user)

			return err
		})
	}

	limit := pictureExtractionParallelism(ctx)
	if len(lookups) > 1 && limit > 1 {
		ctx = context.WithValue(ctx, pictureExtractionParallelismKey{}, limit/len(lookups))
		if err := runItemLookups(ctx, lookups); err != nil {
			return nil, err
		}
	} else {
		for _, lookup := range lookups {
			if err := lookup(ctx); err != nil {
				return nil, err
			}
		}
	}

	result := make(map[int64][]*Picture, len(groups))
	maps.Copy(result, itemPictures)
	maps.Copy(result, loosePictures)

	return result, nil
}

func (s *PicturesGRPCServer) extractNewboxPictureRows(
	ctx context.Context, groups map[int64][]*schema.PictureRow, fields *PictureFields, lang string, user UserContext,
) (map[int64][]*Picture, error) {
	rows := make([]*schema.PictureRow, 0)
	seen := make(map[int64]bool)

	for _, key := range slices.Sorted(maps.Keys(groups)) {
		for _, row := range groups[key] {
			if !seen[row.ID] {
				seen[row.ID] = true
				rows = append(rows, row)
			}
		}
	}

	extracted, err := s.pictureExtractor.ExtractRows(ctx, rows, fields, lang, user)
	if err != nil {
		return nil, err
	}

	byID := make(map[int64]*Picture, len(extracted))
	for _, picture := range extracted {
		byID[picture.GetId()] = picture
	}

	result := make(map[int64][]*Picture, len(groups))
	for key, selected := range groups {
		result[key] = make([]*Picture, 0, len(selected))
		for _, row := range selected {
			result[key] = append(result[key], byID[row.ID])
		}
	}

	return result, nil
}
