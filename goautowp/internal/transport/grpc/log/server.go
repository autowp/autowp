package log

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/log"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type Server struct {
	pb.UnimplementedLogServer

	repository *log.Repository
	auth       *authn.Service
}

func NewServer(repository *log.Repository, auth *authn.Service) *Server {
	return &Server{
		repository: repository,
		auth:       auth,
	}
}

func (s *Server) GetEvents(ctx context.Context, in *pb.LogEventsRequest) (*pb.LogEvents, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
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

	is := make([]*pb.LogEvent, 0, len(res))
	for _, item := range res {
		is = append(is, &pb.LogEvent{
			CreateTime:  timestamppb.New(item.CreatedAt),
			Description: item.Description,
			UserId:      item.UserID,
			Items:       item.Items,
			Pictures:    item.Pictures,
		})
	}

	paginator := &pb.Pages{
		PageCount:      pages.PageCount,
		Current:        pages.Current,
		TotalItemCount: pages.TotalItemCount,
	}

	return &pb.LogEvents{
		Items:     is,
		Paginator: paginator,
	}, nil
}
