package goautowp

import (
	"context"
	"database/sql"
	"errors"

	"github.com/autowp/goautowp/votings"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type VotingsGRPCServer struct {
	UnimplementedVotingsServer

	repository *votings.Repository
	auth       *Auth
}

func NewVotingsGRPCServer(
	repository *votings.Repository,
	auth *Auth,
) *VotingsGRPCServer {
	return &VotingsGRPCServer{
		repository: repository,
		auth:       auth,
	}
}

func (s *VotingsGRPCServer) GetVoting(ctx context.Context, in *VotingRequest) (*Voting, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	id := in.GetId()

	voting, variants, err := s.repository.Voting(ctx, id, userCtx.UserID)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, status.Error(codes.NotFound, "Voting not found")
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	res := make([]*VotingVariant, 0, len(variants))
	for _, variant := range variants {
		res = append(res, &VotingVariant{
			Id:      variant.ID,
			IsMax:   variant.IsMax,
			IsMin:   variant.IsMin,
			Name:    variant.Name,
			Percent: variant.Percent,
			Text:    variant.Text,
			Votes:   variant.Votes,
		})
	}

	return &Voting{
		Id:           voting.ID,
		BeginDate:    timestamppb.New(voting.BeginDate),
		EndDate:      timestamppb.New(voting.EndDate),
		CanVote:      voting.CanVote,
		Multivariant: voting.Multivariant,
		Name:         voting.Name,
		Variants:     res,
	}, nil
}

func (s *VotingsGRPCServer) GetVotingVariantVotes(
	ctx context.Context,
	in *VotingRequest,
) (*VotingVariantVotes, error) {
	ids, err := s.repository.Votes(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &VotingVariantVotes{
		UserIds: ids,
	}, nil
}

func (s *VotingsGRPCServer) Vote(ctx context.Context, in *VoteRequest) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	id := in.GetId()

	success, err := s.repository.Vote(ctx, id, in.GetVotingVariantVoteIds(), userCtx.UserID)
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
