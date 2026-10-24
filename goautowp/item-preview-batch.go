package goautowp

import (
	"context"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
)

// extractPreviewPicturesBatch keeps perspective selection per item, then shares
// picture extraction across the whole list, with one batch per thumbnail size.
func (s *ItemExtractor) extractPreviewPicturesBatch(
	ctx context.Context, rows []*items.Item, fields *ItemFields, lang string, userCtx UserContext,
) ([]*PreviewPictures, error) {
	selections := make([]*PerspectivePictureFetcherResult, len(rows))

	lookups := make([]func(context.Context) error, 0, len(rows))
	for index, row := range rows {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			selections[index], err = s.fetchPreviewPictures(ctx, fields, row)

			return err
		})
	}

	if err := runItemLookups(ctx, lookups); err != nil {
		return nil, err
	}

	return extractPreviewPictureGroups(ctx, selections, fields.GetPreviewPictures().GetPictures().GetFields(),
		func(ctx context.Context, rows []*schema.PictureRow, fields *PictureFields) ([]*Picture, error) {
			return s.container.PictureExtractor().ExtractRows(ctx, rows, fields, lang, userCtx)
		})
}

type previewPictureSlot struct {
	item    int
	picture int
}

func extractPreviewPictureGroups(
	ctx context.Context, selections []*PerspectivePictureFetcherResult, requested *PictureFields,
	extract func(context.Context, []*schema.PictureRow, *PictureFields) ([]*Picture, error),
) ([]*PreviewPictures, error) {
	result := make([]*PreviewPictures, len(selections))

	var (
		largeRows, mediumRows   []*schema.PictureRow
		largeSlots, mediumSlots []previewPictureSlot
	)

	for index, selection := range selections {
		result[index] = &PreviewPictures{
			LargeFormat: selection.LargeFormat, TotalPictures: selection.TotalPictures,
			Pictures: make([]*NullPicture, len(selection.Pictures)),
		}
		for slot, picture := range selection.Pictures {
			result[index].Pictures[slot] = &NullPicture{Kind: &NullPicture_Null{}}

			if picture == nil || picture.Row == nil {
				continue
			}

			position := previewPictureSlot{item: index, picture: slot}
			if selection.LargeFormat && slot == 0 {
				largeRows = append(largeRows, picture.Row)
				largeSlots = append(largeSlots, position)
			} else {
				mediumRows = append(mediumRows, picture.Row)
				mediumSlots = append(mediumSlots, position)
			}
		}
	}

	var lookups []func(context.Context) error

	for _, batch := range []struct {
		rows  []*schema.PictureRow
		slots []previewPictureSlot
		large bool
	}{{largeRows, largeSlots, true}, {mediumRows, mediumSlots, false}} {
		if len(batch.rows) == 0 {
			continue
		}

		lookups = append(lookups, func(ctx context.Context) error {
			pictures, err := extract(ctx, batch.rows, previewPictureFields(requested, batch.large))
			if err != nil {
				return err
			}

			for index, picture := range pictures {
				slot := batch.slots[index]
				result[slot.item].Pictures[slot.picture] = &NullPicture{Kind: &NullPicture_Picture{Picture: picture}}
			}

			return nil
		})
	}
	// Selection has already joined. Spend at most the caller's picture budget on
	// these two batches, including their internal reads.
	parallelism := pictureExtractionParallelism(ctx)
	if parallelism < 2 || len(lookups) < 2 {
		for _, lookup := range lookups {
			if err := lookup(ctx); err != nil {
				return nil, err
			}
		}
	} else {
		ctx = context.WithValue(ctx, pictureExtractionParallelismKey{}, parallelism/len(lookups))
		if err := runItemLookups(ctx, lookups); err != nil {
			return nil, err
		}
	}

	return result, nil
}
