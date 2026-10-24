package goautowp

import (
	"context"
	"database/sql"
	"fmt"
	"maps"
	"slices"

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
	result := make([]*DfDistance, len(rows))

	repository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	ids := make(map[int64]struct{})
	request := fields.GetDstPicture()

	for index, row := range rows {
		result[index] = &DfDistance{
			SrcPictureId: row.SrcPictureID, DstPictureId: row.DstPictureID,
			Distance: int32(row.Distance), //nolint:gosec
		}
		if request != nil && row.DstPictureID != 0 {
			ids[row.DstPictureID] = struct{}{}
		}
	}

	if len(ids) == 0 {
		return result, nil
	}

	options, err := convertPictureListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	pictures, err := repository.PicturesByIDs(ctx, slices.Collect(maps.Keys(ids)), options,
		convertPictureFields(request.GetFields()), convertPicturesOrder(request.GetOrder()))
	if err != nil {
		return nil, fmt.Errorf("Pictures(): %w", err)
	}

	found := make(map[int64]struct{}, len(pictures))
	for _, picture := range pictures {
		found[picture.ID] = struct{}{}
	}

	for id := range ids {
		if _, ok := found[id]; !ok {
			return nil, sql.ErrNoRows
		}
	}

	// Keep nested item reads within the inherited picture budget.
	ctx = context.WithValue(ctx, itemLookupContextKey{}, true)

	extracted, err := s.container.PictureExtractor().ExtractRows(ctx, pictures, request.GetFields(), lang, userCtx)
	if err != nil {
		return nil, err
	}

	byID := make(map[int64]*Picture, len(extracted))
	for _, picture := range extracted {
		byID[picture.GetId()] = picture
	}

	for index, row := range rows {
		if row.DstPictureID != 0 {
			result[index].DstPicture = byID[row.DstPictureID]
		}
	}

	return result, nil
}
