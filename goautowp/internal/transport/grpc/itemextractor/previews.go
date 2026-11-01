package itemextractor

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/picturefetcher"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
)

// ExtractPreviewPicturesBatch keeps perspective selection per item, then shares
// picture extraction across the whole list, with one batch per thumbnail size.
func (s *Extractor) ExtractPreviewPicturesBatch(
	ctx context.Context, rows []*items.Item, fields *pb.ItemFields, lang string, userCtx authn.User,
) ([]*pb.PreviewPictures, error) {
	selections := make([]*picturefetcher.Result, len(rows))

	lookups := make([]func(context.Context) error, 0, len(rows))
	for index, row := range rows {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			selections[index], err = s.fetchPreviewPictures(ctx, fields, row)

			return err
		})
	}

	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	return extractPreviewPictureGroups(ctx, selections, fields.GetPreviewPictures().GetPictures().GetFields(),
		func(ctx context.Context, rows []*schema.PictureRow, fields *pb.PictureFields) ([]*pb.Picture, error) {
			return s.dependencies.PictureExtractor().ExtractRows(ctx, rows, fields, lang, userCtx)
		})
}

type previewPictureSlot struct {
	item    int
	picture int
}

func extractPreviewPictureGroups(
	ctx context.Context, selections []*picturefetcher.Result, requested *pb.PictureFields,
	extract func(context.Context, []*schema.PictureRow, *pb.PictureFields) ([]*pb.Picture, error),
) ([]*pb.PreviewPictures, error) {
	result := make([]*pb.PreviewPictures, len(selections))

	var (
		largeRows, mediumRows   []*schema.PictureRow
		largeSlots, mediumSlots []previewPictureSlot
	)

	for index, selection := range selections {
		result[index] = &pb.PreviewPictures{
			LargeFormat: selection.LargeFormat, TotalPictures: selection.TotalPictures,
			Pictures: make([]*pb.NullPicture, len(selection.Pictures)),
		}
		for slot, picture := range selection.Pictures {
			result[index].Pictures[slot] = &pb.NullPicture{Kind: &pb.NullPicture_Null{}}

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
				result[slot.item].Pictures[slot.picture] = &pb.NullPicture{
					Kind: &pb.NullPicture_Picture{Picture: picture},
				}
			}

			return nil
		})
	}
	// Selection has already joined. Spend at most the caller's picture budget on
	// these two batches, including their internal reads.
	parallelism := extraction.PictureParallelism(ctx)
	if parallelism < 2 || len(lookups) < 2 {
		for _, lookup := range lookups {
			if err := lookup(ctx); err != nil {
				return nil, err
			}
		}
	} else {
		ctx = extraction.WithPictureParallelism(ctx, parallelism/len(lookups))
		if err := extraction.Run(ctx, lookups); err != nil {
			return nil, err
		}
	}

	return result, nil
}
