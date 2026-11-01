package achievements

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/achievements"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type Server struct {
	pb.UnimplementedAchievementsServer

	repository *achievements.Repository
}

func NewServer(repository *achievements.Repository) *Server {
	return &Server{repository: repository}
}

// GetUserAchievements powers the public profile page — no auth, any visitor may request
// it for any user.
func (s *Server) GetUserAchievements(
	ctx context.Context, in *pb.GetUserAchievementsRequest,
) (*pb.UserAchievementsList, error) {
	userID := in.GetUserId()
	if userID == 0 {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	result, err := s.repository.UserAchievements(ctx, userID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*pb.UserAchievementItem, 0, len(result.Earned))
	for _, row := range result.Earned {
		items = append(items, &pb.UserAchievementItem{
			Code:       row.Code,
			CreateTime: timestamppb.New(row.CreatedAt),
		})
	}

	progress := make([]*pb.UserAchievementProgress, 0, len(result.Progress))
	for _, p := range result.Progress {
		progress = append(progress, &pb.UserAchievementProgress{
			Code:      p.Code,
			Current:   p.Current,
			Threshold: p.Threshold,
		})
	}

	return &pb.UserAchievementsList{Items: items, Progress: progress}, nil
}

// GetAchievementStats powers the /achievements catalog page's "N users have earned this"
// display. Public, no auth — same as GetUserAchievements.
func (s *Server) GetAchievementStats(
	ctx context.Context, _ *emptypb.Empty,
) (*pb.AchievementStatsList, error) {
	rows, err := s.repository.AchievementCounts(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*pb.AchievementStat, 0, len(rows))
	for _, row := range rows {
		items = append(items, &pb.AchievementStat{Code: row.Code, UsersCount: row.Count})
	}

	return &pb.AchievementStatsList{Items: items}, nil
}
