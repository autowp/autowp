package rating

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/attrs"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/frontend"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

const (
	usersRatingLimit    = 30
	numOfItemsInDetails = 3
)

type Server struct {
	pb.UnimplementedRatingServer

	picturesRepository *pictures.Repository
	userRepository     *users.Repository
	itemsRepository    *items.Repository
	commentsRepository *comments.Repository
	attrsRepository    *attrs.Repository
}

func NewServer(
	picturesRepository *pictures.Repository,
	userRepository *users.Repository,
	itemsRepository *items.Repository,
	commentsRepository *comments.Repository,
	attrsRepository *attrs.Repository,
) *Server {
	return &Server{
		picturesRepository: picturesRepository,
		userRepository:     userRepository,
		itemsRepository:    itemsRepository,
		commentsRepository: commentsRepository,
		attrsRepository:    attrsRepository,
	}
}

func (s *Server) GetUserPicturesRating(
	ctx context.Context, _ *emptypb.Empty,
) (*pb.UsersRatingResponse, error) {
	falseRef := false
	trueRef := true

	rows, _, err := s.userRepository.Users(ctx, &query.UserListOptions{
		Deleted:     &falseRef,
		Limit:       usersRatingLimit,
		HasPictures: &trueRef,
	}, users.UserFields{}, users.OrderByPicturesTotalDesc)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.UsersRatingUser, len(rows))
	for idx, row := range rows {
		result[idx] = &pb.UsersRatingUser{
			UserId: row.ID,
			Volume: row.PicturesTotal,
		}
	}

	return &pb.UsersRatingResponse{
		Users: result,
	}, nil
}

func (s *Server) GetUserPicturesRatingBrands(
	ctx context.Context, in *pb.UserRatingDetailsRequest,
) (*pb.UserRatingBrandsResponse, error) {
	brands, _, err := s.itemsRepository.List(ctx, &query.ItemListOptions{
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{
				Pictures: &query.PictureListOptions{
					OwnerID: in.GetUserId(),
					Status:  schema.PictureStatusAccepted,
				},
			},
		},
		Limit:    numOfItemsInDetails,
		Language: in.GetLanguage(),
	}, &items.ItemFields{
		NameOnly:                true,
		DescendantPicturesCount: true,
	}, items.OrderByDescendantPicturesCount, false)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.UsersRatingUserBrand, len(brands))
	for idx, brand := range brands {
		result[idx] = &pb.UsersRatingUserBrand{
			Name:   brand.NameOnly,
			Route:  frontend.BrandRoute(util.NullStringToString(brand.Catname)),
			Volume: int64(brand.DescendantPicturesCount),
		}
	}

	return &pb.UserRatingBrandsResponse{
		Brands: result,
	}, nil
}

func (s *Server) GetUserCommentsRating(
	ctx context.Context, _ *emptypb.Empty,
) (*pb.UsersRatingResponse, error) {
	ratingUsers, err := s.commentsRepository.TopAuthors(ctx, usersRatingLimit)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.UsersRatingUser, len(ratingUsers))
	for idx, ratingUser := range ratingUsers {
		result[idx] = &pb.UsersRatingUser{
			UserId: ratingUser.AuthorID,
			Volume: ratingUser.Volume,
		}
	}

	return &pb.UsersRatingResponse{
		Users: result,
	}, nil
}

func (s *Server) GetUserCommentsRatingFans(
	ctx context.Context, in *pb.UserRatingDetailsRequest,
) (*pb.GetUserRatingFansResponse, error) {
	fans, err := s.commentsRepository.AuthorsFans(ctx, in.GetUserId(), numOfItemsInDetails)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.UsersRatingUserFan, len(fans))
	for idx, fan := range fans {
		result[idx] = &pb.UsersRatingUserFan{
			UserId: fan.UserID,
			Volume: fan.Volume,
		}
	}

	return &pb.GetUserRatingFansResponse{
		Fans: result,
	}, nil
}

func (s *Server) GetUserPictureLikesRating(
	ctx context.Context, _ *emptypb.Empty,
) (*pb.UsersRatingResponse, error) {
	ratingUsers, err := s.picturesRepository.TopLikes(ctx, usersRatingLimit)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.UsersRatingUser, len(ratingUsers))
	for idx, ratingUser := range ratingUsers {
		result[idx] = &pb.UsersRatingUser{
			UserId: ratingUser.OwnerID,
			Volume: ratingUser.Volume,
		}
	}

	return &pb.UsersRatingResponse{
		Users: result,
	}, nil
}

func (s *Server) GetUserPictureLikesRatingFans(
	ctx context.Context, in *pb.UserRatingDetailsRequest,
) (*pb.GetUserRatingFansResponse, error) {
	fans, err := s.picturesRepository.TopOwnerFans(ctx, in.GetUserId(), numOfItemsInDetails)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.UsersRatingUserFan, len(fans))
	for idx, fan := range fans {
		result[idx] = &pb.UsersRatingUserFan{
			UserId: fan.UserID,
			Volume: fan.Volume,
		}
	}

	return &pb.GetUserRatingFansResponse{
		Fans: result,
	}, nil
}

func (s *Server) GetUserSpecsRating(
	ctx context.Context, _ *emptypb.Empty,
) (*pb.UsersRatingResponse, error) {
	falseRef := false
	trueRef := true

	ratingUsers, _, err := s.userRepository.Users(ctx, &query.UserListOptions{
		Deleted:  &falseRef,
		HasSpecs: &trueRef,
		Limit:    usersRatingLimit,
	}, users.UserFields{}, users.OrderBySpecsVolumeDesc)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.UsersRatingUser, len(ratingUsers))
	for idx, u := range ratingUsers {
		result[idx] = &pb.UsersRatingUser{
			UserId: u.ID,
			Volume: u.SpecsVolume,
			Weight: u.SpecsWeight,
		}
	}

	return &pb.UsersRatingResponse{
		Users: result,
	}, nil
}

func (s *Server) GetUserSpecsRatingBrands(
	ctx context.Context, in *pb.UserRatingDetailsRequest,
) (*pb.UserRatingBrandsResponse, error) {
	brands, err := s.attrsRepository.TopUserBrands(ctx, in.GetUserId(), numOfItemsInDetails, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.UsersRatingUserBrand, len(brands))
	for idx, brand := range brands {
		result[idx] = &pb.UsersRatingUserBrand{
			Name:   brand.Name,
			Route:  frontend.BrandRoute(brand.Catname),
			Volume: brand.Volume,
		}
	}

	return &pb.UserRatingBrandsResponse{
		Brands: result,
	}, nil
}
