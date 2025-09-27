package goautowp

import (
	"github.com/autowp/goautowp/schema"
)

type PictureModerVoteExtractor struct{}

func NewPictureModerVoteExtractor() *PictureModerVoteExtractor {
	return &PictureModerVoteExtractor{}
}

func (s *PictureModerVoteExtractor) ExtractRows(
	rows []*schema.PictureModerVoteRow,
) ([]*PictureModerVote, error) {
	result := make([]*PictureModerVote, 0, len(rows))

	for _, row := range rows {
		result = append(result, &PictureModerVote{
			PictureId: row.PictureID,
			UserId:    row.UserID,
			Vote:      int32(row.Vote),
			Reason:    row.Reason,
		})
	}

	return result, nil
}
