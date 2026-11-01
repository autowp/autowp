package picturemapper

import (
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/schema"
)

func ModerVotesToProto(
	rows []*schema.PictureModerVoteRow,
) []*pb.PictureModerVote {
	result := make([]*pb.PictureModerVote, 0, len(rows))

	for _, row := range rows {
		result = append(result, &pb.PictureModerVote{
			PictureId: row.PictureID,
			UserId:    row.UserID,
			Vote:      int32(row.Vote),
			Reason:    row.Reason,
		})
	}

	return result
}
