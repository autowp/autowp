package goautowp

import (
	"context"

	"github.com/autowp/goautowp/achievements"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type AchievementsGRPCServer struct {
	UnimplementedAchievementsServer

	repository *achievements.Repository
}

func NewAchievementsGRPCServer(repository *achievements.Repository) *AchievementsGRPCServer {
	return &AchievementsGRPCServer{repository: repository}
}

// GetUserAchievements powers the public profile page — no auth, any visitor may request
// it for any user.
func (s *AchievementsGRPCServer) GetUserAchievements(
	ctx context.Context, in *GetUserAchievementsRequest,
) (*UserAchievementsList, error) {
	userID := in.GetUserId()
	if userID == 0 {
		return nil, status.Errorf(codes.InvalidArgument, "InvalidArgument")
	}

	result, err := s.repository.UserAchievements(ctx, userID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*UserAchievementItem, 0, len(result.Earned))
	for _, row := range result.Earned {
		items = append(items, &UserAchievementItem{
			Code:      row.Code,
			CreatedAt: timestamppb.New(row.CreatedAt),
		})
	}

	progress := make([]*UserAchievementProgress, 0, len(result.Progress))
	for _, p := range result.Progress {
		progress = append(progress, &UserAchievementProgress{
			Code:      p.Code,
			Current:   p.Current,
			Threshold: p.Threshold,
		})
	}

	return &UserAchievementsList{Items: items, Progress: progress}, nil
}

// GetAchievementStats powers the /achievements catalog page's "N users have earned this"
// display. Public, no auth — same as GetUserAchievements.
func (s *AchievementsGRPCServer) GetAchievementStats(
	ctx context.Context, _ *emptypb.Empty,
) (*AchievementStatsList, error) {
	rows, err := s.repository.AchievementCounts(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	items := make([]*AchievementStat, 0, len(rows))
	for _, row := range rows {
		items = append(items, &AchievementStat{Code: row.Code, UsersCount: row.Count})
	}

	return &AchievementStatsList{Items: items}, nil
}
