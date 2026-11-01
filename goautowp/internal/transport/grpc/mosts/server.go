package mosts

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/mosts"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
)

type ItemExtractor interface {
	ExtractPreviewPicturesBatch(
		ctx context.Context,
		rows []*items.Item,
		fields *pb.ItemFields,
		lang string,
		userCtx authn.User,
	) ([]*pb.PreviewPictures, error)
	ExtractRowsParallel(
		ctx context.Context,
		rows []*items.Item,
		fields *pb.ItemFields,
		lang string,
		userCtx authn.User,
	) ([]*pb.Item, error)
}

type Server struct {
	pb.UnimplementedMostsServer

	auth       *authn.Service
	extractor  ItemExtractor
	repository *mosts.Repository
}

func NewServer(
	auth *authn.Service,
	extractor ItemExtractor,
	repository *mosts.Repository,
) *Server {
	return &Server{
		auth:       auth,
		extractor:  extractor,
		repository: repository,
	}
}

func (s *Server) GetItems(
	ctx context.Context,
	in *pb.MostsItemsRequest,
) (*pb.MostsItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	var (
		lang           = in.GetLanguage()
		yearsCatname   = in.GetYearsCatname()
		carTypeCatname = in.GetTypeCatname()
		mostCatname    = in.GetRatingCatname()
		brandID        = in.GetBrandId()

		fields = pb.ItemFields{
			NameHtml:    true,
			Description: true,
			Route:       true,
			PreviewPictures: &pb.PreviewPicturesRequest{
				PerspectivePageId: 1,
				Pictures: &pb.PicturesRequest{
					Fields: &pb.PictureFields{
						NameText: true,
					},
					Options: &pb.PictureListOptions{
						PictureItem: &pb.PictureItemListOptions{
							TypeId: pb.PictureItemType_PICTURE_ITEM_CONTENT,
						},
					},
				},
			},
		}

		repoFields = itemmapper.FieldsFromProto(&fields)
	)

	list, unitID, err := s.repository.Items(ctx, mosts.ItemsOptions{
		Language: lang,
		Most:     mostCatname,
		Years:    yearsCatname,
		CarType:  carTypeCatname,
		BrandID:  brandID,
	}, repoFields)
	if err != nil {
		// A rating or years range that doesn't exist is a 404, not a server fault: these URLs are
		// walked by crawlers all the time, and answering Internal both hides real failures in the
		// noise and makes the frontend render an error page (HTTP 200) where it should render a
		// 404.
		if errors.Is(err, mosts.ErrRatingNotFound) || errors.Is(err, mosts.ErrYearsRangeNotFound) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	result, err := s.ExtractItems(ctx, list, unitID, &fields, lang, userCtx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.MostsItems{Items: result}, nil
}

func (s *Server) GetMenu(ctx context.Context, in *pb.MostsMenuRequest) (*pb.MostsMenu, error) {
	yearsListItems := s.repository.YearsMenu()

	yearsRanges := make([]*pb.YearsRange, 0, len(yearsListItems))
	for _, yearRange := range yearsListItems {
		yearsRanges = append(yearsRanges, &pb.YearsRange{
			Name:    yearRange.Name,
			Catname: yearRange.Folder,
		})
	}

	ratingsListItems := s.repository.RatingsMenu()

	ratings := make([]*pb.MostsRating, 0, len(ratingsListItems))
	for _, rating := range ratingsListItems {
		ratings = append(ratings, &pb.MostsRating{
			Name:    rating.Name,
			Catname: rating.Catname,
		})
	}

	rows, err := s.repository.VehicleTypes(ctx, in.GetBrandId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	vehicleTypes := make([]*pb.MostsVehicleType, 0, len(rows))

	for _, row := range rows {
		svehicleTypes := make([]*pb.MostsVehicleType, 0, len(row.Childs))
		for _, srow := range row.Childs {
			svehicleTypes = append(svehicleTypes, &pb.MostsVehicleType{
				NameRp:  srow.NameRp,
				Catname: srow.Catname,
			})
		}

		vehicleTypes = append(vehicleTypes, &pb.MostsVehicleType{
			NameRp:  row.NameRp,
			Catname: row.Catname,
			Childs:  svehicleTypes,
		})
	}

	return &pb.MostsMenu{
		Years:        yearsRanges,
		Ratings:      ratings,
		VehicleTypes: vehicleTypes,
	}, nil
}

// ExtractItems enriches selected rating rows with shared preview and field batches.
func (s *Server) ExtractItems(
	ctx context.Context, list []mosts.ResultItem, unitID int64, fields *pb.ItemFields, lang string, userCtx authn.User,
) ([]*pb.MostsItem, error) {
	rows := make([]*items.Item, len(list))
	for index, car := range list {
		rows[index] = car.Item
	}

	var previews []*pb.PreviewPictures

	if fields.GetPreviewPictures() != nil && len(rows) > 0 {
		var err error

		previews, err = s.extractor.ExtractPreviewPicturesBatch(ctx, rows, fields, lang, userCtx)
		if err != nil {
			return nil, err
		}

		fields, _ = proto.Clone(fields).(*pb.ItemFields)
		fields.PreviewPictures = nil
	}

	if len(rows) > 1 {
		ctx = extraction.WithPictureParallelism(ctx, 1)
	}

	extracted, err := s.extractor.ExtractRowsParallel(ctx, rows, fields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	result := make([]*pb.MostsItem, len(list))
	for index, car := range list {
		if previews != nil {
			extracted[index].PreviewPictures = previews[index]
		}

		result[index] = &pb.MostsItem{Item: extracted[index], ValueHtml: car.ValueHTML, UnitId: unitID}
	}

	return result, nil
}
