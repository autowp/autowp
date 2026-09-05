package goautowp

import (
	"context"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

const (
	usersRatingLimit    = 30
	numOfItemsInDetails = 3
)

type RatingGRPCServer struct {
	UnimplementedRatingServer

	picturesRepository *pictures.Repository
	userRepository     *users.Repository
	itemsRepository    *items.Repository
	commentsRepository *comments.Repository
	attrsRepository    *attrs.Repository
}

func NewRatingGRPCServer(
	picturesRepository *pictures.Repository,
	userRepository *users.Repository,
	itemsRepository *items.Repository,
	commentsRepository *comments.Repository,
	attrsRepository *attrs.Repository,
) *RatingGRPCServer {
	return &RatingGRPCServer{
		picturesRepository: picturesRepository,
		userRepository:     userRepository,
		itemsRepository:    itemsRepository,
		commentsRepository: commentsRepository,
		attrsRepository:    attrsRepository,
	}
}

func (s *RatingGRPCServer) GetUserPicturesRating(
	ctx context.Context, _ *emptypb.Empty,
) (*UsersRatingResponse, error) {
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

	result := make([]*UsersRatingUser, len(rows))
	for idx, row := range rows {
		result[idx] = &UsersRatingUser{
			UserId: row.ID,
			Volume: row.PicturesTotal,
		}
	}

	return &UsersRatingResponse{
		Users: result,
	}, nil
}

func (s *RatingGRPCServer) GetUserPicturesRatingBrands(
	ctx context.Context, in *UserRatingDetailsRequest,
) (*UserRatingBrandsResponse, error) {
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

	result := make([]*UsersRatingUserBrand, len(brands))
	for idx, brand := range brands {
		result[idx] = &UsersRatingUserBrand{
			Name:   brand.NameOnly,
			Route:  frontend.BrandRoute(util.NullStringToString(brand.Catname)),
			Volume: int64(brand.DescendantPicturesCount),
		}
	}

	return &UserRatingBrandsResponse{
		Brands: result,
	}, nil
}

func (s *RatingGRPCServer) GetUserCommentsRating(
	ctx context.Context, _ *emptypb.Empty,
) (*UsersRatingResponse, error) {
	ratingUsers, err := s.commentsRepository.TopAuthors(ctx, usersRatingLimit)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*UsersRatingUser, len(ratingUsers))
	for idx, ratingUser := range ratingUsers {
		result[idx] = &UsersRatingUser{
			UserId: ratingUser.AuthorID,
			Volume: ratingUser.Volume,
		}
	}

	return &UsersRatingResponse{
		Users: result,
	}, nil
}

func (s *RatingGRPCServer) GetUserCommentsRatingFans(
	ctx context.Context, in *UserRatingDetailsRequest,
) (*GetUserRatingFansResponse, error) {
	fans, err := s.commentsRepository.AuthorsFans(ctx, in.GetUserId(), numOfItemsInDetails)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*UsersRatingUserFan, len(fans))
	for idx, fan := range fans {
		result[idx] = &UsersRatingUserFan{
			UserId: fan.UserID,
			Volume: fan.Volume,
		}
	}

	return &GetUserRatingFansResponse{
		Fans: result,
	}, nil
}

func (s *RatingGRPCServer) GetUserPictureLikesRating(
	ctx context.Context, _ *emptypb.Empty,
) (*UsersRatingResponse, error) {
	ratingUsers, err := s.picturesRepository.TopLikes(ctx, usersRatingLimit)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*UsersRatingUser, len(ratingUsers))
	for idx, ratingUser := range ratingUsers {
		result[idx] = &UsersRatingUser{
			UserId: ratingUser.OwnerID,
			Volume: ratingUser.Volume,
		}
	}

	return &UsersRatingResponse{
		Users: result,
	}, nil
}

func (s *RatingGRPCServer) GetUserPictureLikesRatingFans(
	ctx context.Context, in *UserRatingDetailsRequest,
) (*GetUserRatingFansResponse, error) {
	fans, err := s.picturesRepository.TopOwnerFans(ctx, in.GetUserId(), numOfItemsInDetails)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*UsersRatingUserFan, len(fans))
	for idx, fan := range fans {
		result[idx] = &UsersRatingUserFan{
			UserId: fan.UserID,
			Volume: fan.Volume,
		}
	}

	return &GetUserRatingFansResponse{
		Fans: result,
	}, nil
}

func (s *RatingGRPCServer) GetUserSpecsRating(
	ctx context.Context, _ *emptypb.Empty,
) (*UsersRatingResponse, error) {
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

	result := make([]*UsersRatingUser, len(ratingUsers))
	for idx, u := range ratingUsers {
		result[idx] = &UsersRatingUser{
			UserId: u.ID,
			Volume: u.SpecsVolume,
			Weight: u.SpecsWeight,
		}
	}

	return &UsersRatingResponse{
		Users: result,
	}, nil
}

func (s *RatingGRPCServer) GetUserSpecsRatingBrands(
	ctx context.Context, in *UserRatingDetailsRequest,
) (*UserRatingBrandsResponse, error) {
	brands, err := s.attrsRepository.TopUserBrands(ctx, in.GetUserId(), numOfItemsInDetails, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*UsersRatingUserBrand, len(brands))
	for idx, brand := range brands {
		result[idx] = &UsersRatingUserBrand{
			Name:   brand.Name,
			Route:  frontend.BrandRoute(brand.Catname),
			Volume: brand.Volume,
		}
	}

	return &UserRatingBrandsResponse{
		Brands: result,
	}, nil
}
