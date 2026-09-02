package goautowp

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/mosts"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

type MostsGRPCServer struct {
	UnimplementedMostsServer

	auth       *Auth
	extractor  *ItemExtractor
	repository *mosts.Repository
}

func NewMostsGRPCServer(
	auth *Auth,
	extractor *ItemExtractor,
	repository *mosts.Repository,
) *MostsGRPCServer {
	return &MostsGRPCServer{
		auth:       auth,
		extractor:  extractor,
		repository: repository,
	}
}

func (s *MostsGRPCServer) GetItems(
	ctx context.Context,
	in *MostsItemsRequest,
) (*MostsItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, s.auth.GRPCError(err)
	}

	var (
		lang           = in.GetLanguage()
		yearsCatname   = in.GetYearsCatname()
		carTypeCatname = in.GetTypeCatname()
		mostCatname    = in.GetRatingCatname()
		brandID        = in.GetBrandId()

		fields = ItemFields{
			NameHtml:    true,
			Description: true,
			Route:       true,
			PreviewPictures: &PreviewPicturesRequest{
				PerspectivePageId: 1,
				Pictures: &PicturesRequest{
					Fields: &PictureFields{
						NameText: true,
					},
					Options: &PictureListOptions{
						PictureItem: &PictureItemListOptions{
							TypeId: PictureItemType_PICTURE_ITEM_CONTENT,
						},
					},
				},
			},
		}

		repoFields = convertItemFields(&fields)
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

	result := make([]*MostsItem, 0)

	for _, car := range list {
		extracted, err := s.extractor.Extract(ctx, car.Item, &fields, lang, userCtx)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		result = append(result, &MostsItem{
			Item:      extracted,
			ValueHtml: car.ValueHTML,
			UnitId:    unitID,
		})
	}

	return &MostsItems{
		Items: result,
	}, nil
}

func (s *MostsGRPCServer) GetMenu(ctx context.Context, in *MostsMenuRequest) (*MostsMenu, error) {
	yearsListItems := s.repository.YearsMenu()

	yearsRanges := make([]*YearsRange, 0, len(yearsListItems))
	for _, yearRange := range yearsListItems {
		yearsRanges = append(yearsRanges, &YearsRange{
			Name:    yearRange.Name,
			Catname: yearRange.Folder,
		})
	}

	ratingsListItems := s.repository.RatingsMenu()

	ratings := make([]*MostsRating, 0, len(ratingsListItems))
	for _, rating := range ratingsListItems {
		ratings = append(ratings, &MostsRating{
			Name:    rating.Name,
			Catname: rating.Catname,
		})
	}

	rows, err := s.repository.VehicleTypes(ctx, in.GetBrandId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	vehicleTypes := make([]*MostsVehicleType, 0, len(rows))

	for _, row := range rows {
		svehicleTypes := make([]*MostsVehicleType, 0, len(row.Childs))
		for _, srow := range row.Childs {
			svehicleTypes = append(svehicleTypes, &MostsVehicleType{
				NameRp:  srow.NameRp,
				Catname: srow.Catname,
			})
		}

		vehicleTypes = append(vehicleTypes, &MostsVehicleType{
			NameRp:  row.NameRp,
			Catname: row.Catname,
			Childs:  svehicleTypes,
		})
	}

	return &MostsMenu{
		Years:        yearsRanges,
		Ratings:      ratings,
		VehicleTypes: vehicleTypes,
	}, nil
}
