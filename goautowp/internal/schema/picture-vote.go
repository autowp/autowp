package schema

import "github.com/doug-martin/goqu/v9"

const (
	PictureVoteTableName             = "picture_vote"
	PictureVoteTablePictureIDColName = "picture_id"
	PictureVoteTableUserIDColName    = "user_id"
	PictureVoteTableValueColName     = "value"
	PictureVoteTableTimestampColName = "timestamp"
)

var (
	PictureVoteTable             = goqu.T(PictureVoteTableName)
	PictureVoteTablePictureIDCol = PictureVoteTable.Col(PictureVoteTablePictureIDColName)
	PictureVoteTableUserIDCol    = PictureVoteTable.Col(PictureVoteTableUserIDColName)
	PictureVoteTableValueCol     = PictureVoteTable.Col(PictureVoteTableValueColName)
)
