package text

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/textstorage"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

type Server struct {
	pb.UnimplementedTextServer

	auth       *authn.Service
	repository *textstorage.Repository
}

func NewServer(auth *authn.Service, repository *textstorage.Repository) *Server {
	return &Server{auth: auth, repository: repository}
}

func (s *Server) GetText(ctx context.Context, in *pb.GetTextRequest) (*pb.GetTextResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	lastRevision, err := s.repository.LatestRevision(ctx, in.GetId())
	if err != nil {
		return nil, textError(err)
	}

	currentRevision := in.GetRevision()
	if currentRevision == 0 {
		currentRevision = lastRevision
	}

	if currentRevision != lastRevision && !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	current, err := s.repository.Revision(ctx, in.GetId(), currentRevision)
	if err != nil {
		return nil, textError(err)
	}

	var (
		previous         schema.TextstorageRevisionRow
		previousRevision int64
		nextRevision     int64
	)

	if currentRevision > 1 {
		previousRevision = currentRevision - 1

		previous, err = s.repository.Revision(ctx, in.GetId(), previousRevision)
		if err != nil {
			return nil, textError(err)
		}
	}

	if currentRevision+1 <= lastRevision {
		nextRevision = currentRevision + 1
	}

	return &pb.GetTextResponse{
		Current: &pb.TextRevision{Text: current.Text, Revision: currentRevision, UserId: current.UserID},
		Prev:    &pb.TextRevision{Text: previous.Text, Revision: previousRevision, UserId: previous.UserID},
		Next:    &pb.TextRevision{Revision: nextRevision},
	}, nil
}

func textError(err error) error {
	if errors.Is(err, textstorage.ErrTextNotFound) || errors.Is(err, textstorage.ErrRevisionNotFound) {
		return status.Error(codes.NotFound, "not found")
	}

	return status.Error(codes.Internal, err.Error())
}
