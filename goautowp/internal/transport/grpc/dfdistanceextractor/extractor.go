package dfdistanceextractor

import (
	"context"
	"database/sql"
	"fmt"
	"maps"
	"slices"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
)

type PictureExtractor interface {
	ExtractRows(
		ctx context.Context,
		rows []*schema.PictureRow,
		fields *pb.PictureFields,
		lang string,
		userCtx authn.User,
	) ([]*pb.Picture, error)
}

type Extractor struct {
	repository *pictures.Repository
	extractor  PictureExtractor
}

func New(repository *pictures.Repository, extractor PictureExtractor) *Extractor {
	return &Extractor{repository: repository, extractor: extractor}
}

func (s *Extractor) ExtractRows(
	ctx context.Context, rows []*schema.DfDistanceRow, fields *pb.DfDistanceFields, lang string,
	userCtx authn.User,
) ([]*pb.DfDistance, error) {
	result := make([]*pb.DfDistance, len(rows))

	ids := make(map[int64]struct{})
	request := fields.GetDstPicture()

	for index, row := range rows {
		result[index] = &pb.DfDistance{
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

	options, err := querymapper.PictureListOptionsFromProto(request.GetOptions())
	if err != nil {
		return nil, err
	}

	pictures, err := s.repository.PicturesByIDs(ctx, slices.Collect(maps.Keys(ids)), options,
		picturemapper.FieldsFromProto(request.GetFields()), picturemapper.OrderFromProto(request.GetOrder()))
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
	ctx = extraction.WithInlineLookups(ctx)

	extracted, err := s.extractor.ExtractRows(ctx, pictures, request.GetFields(), lang, userCtx)
	if err != nil {
		return nil, err
	}

	byID := make(map[int64]*pb.Picture, len(extracted))
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
