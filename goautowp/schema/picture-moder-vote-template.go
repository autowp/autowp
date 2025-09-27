package schema

import (
	"github.com/doug-martin/goqu/v9"
)

const (
	PictureModerVoteTemplateTableName          = "picture_moder_vote_template"
	PictureModerVoteTemplateTableIDColName     = "id"
	PictureModerVoteTemplateTableReasonColName = "reason"
	PictureModerVoteTemplateTableVoteColName   = "vote"
	PictureModerVoteTemplateTableUserIDColName = "user_id"

	ModerVoteTemplateMessageMaxLength = 80
)

var (
	PictureModerVoteTemplateTable      = goqu.T(PictureModerVoteTemplateTableName)
	PictureModerVoteTemplateTableIDCol = PictureModerVoteTemplateTable.Col(
		PictureModerVoteTemplateTableIDColName,
	)
	PictureModerVoteTemplateTableReasonCol = PictureModerVoteTemplateTable.Col(
		PictureModerVoteTemplateTableReasonColName,
	)
	PictureModerVoteTemplateTableVoteCol = PictureModerVoteTemplateTable.Col(
		PictureModerVoteTemplateTableVoteColName,
	)
	PictureModerVoteTemplateTableUserIDCol = PictureModerVoteTemplateTable.Col(
		PictureModerVoteTemplateTableUserIDColName,
	)
)

type PictureModerVoteTemplateRow struct {
	ID      int64  `db:"id"      goqu:"skipinsert"`
	UserID  int64  `db:"user_id"`
	Message string `db:"reason"`
	Vote    int8   `db:"vote"`
}
