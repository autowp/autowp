package votings

import (
	"context"
	"database/sql"
	"errors"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/util"
	"github.com/autowp/goautowp/internal/votings"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type Server struct {
	pb.UnimplementedVotingsServer

	repository *votings.Repository
	auth       *authn.Service
}

func NewServer(
	repository *votings.Repository,
	auth *authn.Service,
) *Server {
	return &Server{
		repository: repository,
		auth:       auth,
	}
}

func (s *Server) GetVoting(ctx context.Context, in *pb.VotingRequest) (*pb.Voting, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	id := in.GetId()

	voting, variants, err := s.repository.Voting(ctx, id, userCtx.UserID)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, status.Error(codes.NotFound, "Voting not found")
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*pb.VotingVariant, 0, len(variants))
	for _, variant := range variants {
		res = append(res, &pb.VotingVariant{
			Id:      variant.ID,
			IsMax:   variant.IsMax,
			IsMin:   variant.IsMin,
			Name:    variant.Name,
			Percent: variant.Percent,
			Text:    util.NullStringToString(variant.Text),
			Votes:   variant.Votes,
		})
	}

	return &pb.Voting{
		Id:           voting.ID,
		BeginDate:    timestamppb.New(voting.BeginDate),
		EndDate:      timestamppb.New(voting.EndDate),
		CanVote:      voting.CanVote,
		Multivariant: voting.Multivariant,
		Name:         voting.Name,
		Variants:     res,
	}, nil
}

func (s *Server) GetVotingVariantVotes(
	ctx context.Context,
	in *pb.VotingRequest,
) (*pb.VotingVariantVotes, error) {
	ids, err := s.repository.Votes(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.VotingVariantVotes{
		UserIds: ids,
	}, nil
}

func (s *Server) Vote(ctx context.Context, in *pb.CreateVoteRequest) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	vote := in.GetVote()
	id := vote.GetId()

	success, err := s.repository.Vote(ctx, id, vote.GetVotingVariantVoteIds(), userCtx.UserID)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, status.Error(codes.NotFound, "Voting not found")
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	if !success {
		return nil, status.Error(codes.NotFound, "Voting not found")
	}

	return &emptypb.Empty{}, nil
}
