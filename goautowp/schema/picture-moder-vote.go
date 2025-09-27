package schema

import (
	"github.com/doug-martin/goqu/v9"
)

const (
	PictureModerVoteTableName             = "picture_moder_vote"
	PictureModerVoteTableUserIDColName    = "user_id"
	PictureModerVoteTablePictureIDColName = "picture_id"
	PictureModerVoteTableVoteColName      = "vote"
	PictureModerVoteTableReasonColName    = "reason"
	PictureModerVoteTableDayDateColName   = "day_date"
)

var (
	PictureModerVoteTable          = goqu.T(PictureModerVoteTableName)
	PictureModerVoteTableUserIDCol = PictureModerVoteTable.Col(
		PictureModerVoteTableUserIDColName,
	)
	PictureModerVoteTablePictureIDCol = PictureModerVoteTable.Col(
		PictureModerVoteTablePictureIDColName,
	)
	PictureModerVoteTableVoteCol   = PictureModerVoteTable.Col(PictureModerVoteTableVoteColName)
	PictureModerVoteTableReasonCol = PictureModerVoteTable.Col(
		PictureModerVoteTableReasonColName,
	)
)

type PictureModerVoteRow struct {
	PictureID int64  `db:"picture_id"`
	UserID    int64  `db:"user_id"`
	Reason    string `db:"reason"`
	Vote      uint8  `db:"vote"`
}
