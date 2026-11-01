package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

func TestVotingsServiceRegistration(t *testing.T) {
	t.Parallel()

	client := pb.NewVotingsClient(conn)
	_, err := client.GetVoting(t.Context(), &pb.VotingRequest{Id: -1})
	require.Equal(t, codes.NotFound, status.Code(err))

	_, err = client.Vote(t.Context(), &pb.CreateVoteRequest{
		Vote: &pb.Vote{Id: -1, VotingVariantVoteIds: []int32{-1}},
	})
	require.Equal(t, codes.NotFound, status.Code(err))

	votes, err := client.GetVotingVariantVotes(t.Context(), &pb.VotingRequest{Id: -1})
	require.NoError(t, err)
	require.Empty(t, votes.GetUserIds())
}
