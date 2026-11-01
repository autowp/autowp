package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestAchievementsServiceRegistration(t *testing.T) {
	t.Parallel()

	client := pb.NewAchievementsClient(conn)
	_, err := client.GetUserAchievements(t.Context(), &pb.GetUserAchievementsRequest{})
	require.Equal(t, codes.InvalidArgument, status.Code(err))

	stats, err := client.GetAchievementStats(t.Context(), &emptypb.Empty{})
	require.NoError(t, err)
	require.NotNil(t, stats)
}
