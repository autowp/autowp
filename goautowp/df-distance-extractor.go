package goautowp

import (
	"context"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"golang.org/x/sync/errgroup"
)

type DfDistanceExtractor struct {
	container *Container
}

func NewDfDistanceExtractor(container *Container) *DfDistanceExtractor {
	return &DfDistanceExtractor{container: container}
}

func (s *DfDistanceExtractor) ExtractRows(
	ctx context.Context, rows []*schema.DfDistanceRow, fields *DfDistanceFields, lang string,
	userCtx UserContext,
) ([]*DfDistance, error) {
	result := make([]*DfDistance, len(rows))

	picturesRepository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	pictureExtractor := s.container.PictureExtractor()

	group, lookupCtx := errgroup.WithContext(ctx)
	group.SetLimit(pictureExtractionParallelism(ctx))

	// Spend the inherited picture budget on distance rows. Nested picture and
	// item extraction must not start another parallel group for every row.
	lookupCtx = context.WithValue(lookupCtx, pictureExtractionParallelismKey{}, 1)
	lookupCtx = context.WithValue(lookupCtx, itemLookupContextKey{}, true)

	for index, row := range rows {
		result[index] = &DfDistance{
			SrcPictureId: row.SrcPictureID,
			DstPictureId: row.DstPictureID,
			Distance:     int32(row.Distance), //nolint: gosec
		}

		dstPictureRequest := fields.GetDstPicture()
		if row.DstPictureID == 0 || dstPictureRequest == nil {
			continue
		}

		group.Go(func() error {
			if err := lookupCtx.Err(); err != nil {
				return err
			}

			dstPictureFields := dstPictureRequest.GetFields()

			dstPictureOptions, err := convertPictureListOptions(dstPictureRequest.GetOptions())
			if err != nil {
				return err
			}

			if dstPictureOptions == nil {
				dstPictureOptions = &query.PictureListOptions{}
			}

			dstPictureOptions.ID = row.DstPictureID

			picRow, err := picturesRepository.Picture(
				lookupCtx,
				dstPictureOptions,
				convertPictureFields(dstPictureFields),
				convertPicturesOrder(dstPictureRequest.GetOrder()),
			)
			if err != nil {
				return err
			}

			dstPicture, err := pictureExtractor.Extract(lookupCtx, picRow, dstPictureFields, lang, userCtx)
			if err != nil {
				return err
			}

			result[index].DstPicture = dstPicture

			return nil
		})
	}

	if err := group.Wait(); err != nil {
		return nil, err
	}

	return result, nil
}
