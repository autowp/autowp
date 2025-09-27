package goautowp

import (
	"context"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
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
	result := make([]*DfDistance, 0, len(rows))

	picturesRepository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	pictureExtractor := s.container.PictureExtractor()

	for _, row := range rows {
		var dstPicture *Picture

		dstPictureRequest := fields.GetDstPicture()
		if row.DstPictureID != 0 && dstPictureRequest != nil {
			dstPictureFields := dstPictureRequest.GetFields()

			dstPictureOptions, err := convertPictureListOptions(dstPictureRequest.GetOptions())
			if err != nil {
				return nil, err
			}

			if dstPictureOptions == nil {
				dstPictureOptions = &query.PictureListOptions{}
			}

			dstPictureOptions.ID = row.DstPictureID

			picRow, err := picturesRepository.Picture(
				ctx,
				dstPictureOptions,
				convertPictureFields(dstPictureFields),
				convertPicturesOrder(dstPictureRequest.GetOrder()),
			)
			if err != nil {
				return nil, err
			}

			dstPicture, err = pictureExtractor.Extract(ctx, picRow, dstPictureFields, lang, userCtx)
			if err != nil {
				return nil, err
			}
		}

		result = append(result, &DfDistance{
			SrcPictureId: row.SrcPictureID,
			DstPictureId: row.DstPictureID,
			Distance:     int32(row.Distance), //nolint: gosec
			DstPicture:   dstPicture,
		})
	}

	return result, nil
}
