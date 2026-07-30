package goautowp

import (
	"context"

	"github.com/autowp/goautowp/log"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type LogGRPCServer struct {
	UnimplementedLogServer

	repository *log.Repository
	auth       *Auth
}

func NewLogGRPCServer(repository *log.Repository, auth *Auth) *LogGRPCServer {
	return &LogGRPCServer{
		repository: repository,
		auth:       auth,
	}
}

func (s *LogGRPCServer) GetEvents(ctx context.Context, in *LogEventsRequest) (*LogEvents, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	res, pages, err := s.repository.Events(ctx, log.ListOptions{
		ArticleID: in.GetArticleId(),
		ItemID:    in.GetItemId(),
		PictureID: in.GetPictureId(),
		UserID:    in.GetUserId(),
		Page:      in.GetPage(),
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	is := make([]*LogEvent, 0, len(res))
	for _, item := range res {
		is = append(is, &LogEvent{
			CreateTime:  timestamppb.New(item.CreatedAt),
			Description: item.Description,
			UserId:      item.UserID,
			Items:       item.Items,
			Pictures:    item.Pictures,
		})
	}

	paginator := &Pages{
		PageCount:        pages.PageCount,
		First:            pages.First,
		Last:             pages.Last,
		Current:          pages.Current,
		FirstPageInRange: pages.FirstPageInRange,
		LastPageInRange:  pages.LastPageInRange,
		PagesInRange:     pages.PagesInRange,
		TotalItemCount:   pages.TotalItemCount,
		Next:             pages.Next,
		Previous:         pages.Previous,
	}

	return &LogEvents{
		Items:     is,
		Paginator: paginator,
	}, nil
}
