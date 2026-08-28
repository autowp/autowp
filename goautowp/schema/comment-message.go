package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
	"github.com/jackc/pgtype"
)

type (
	CommentMessageModeratorAttention int32
	CommentMessageType               int32
)

const (
	CommentMessageModeratorAttentionNone      CommentMessageModeratorAttention = 0
	CommentMessageModeratorAttentionRequired  CommentMessageModeratorAttention = 1
	CommentMessageModeratorAttentionCompleted CommentMessageModeratorAttention = 2

	CommentMessageTypeIDPictures CommentMessageType = 1
	CommentMessageTypeIDItems    CommentMessageType = 2
	CommentMessageTypeIDVotings  CommentMessageType = 3
	CommentMessageTypeIDArticles CommentMessageType = 4
	CommentMessageTypeIDForums   CommentMessageType = 5

	CommentMessageTableName                      = "comment_message"
	CommentMessageTableIDColName                 = "id"
	CommentMessageTableParentIDColName           = "parent_id"
	CommentMessageTableTypeIDColName             = "type_id"
	CommentMessageTableItemIDColName             = "item_id"
	CommentMessageTableAuthorIDColName           = "author_id"
	CommentMessageTableModeratorAttentionColName = "moderator_attention"
	CommentMessageTableDeletedColName            = "deleted"
	CommentMessageTableDeletedByColName          = "deleted_by"
	CommentMessageTableDeleteDateColName         = "delete_date"
	CommentMessageTableDeleteReasonColName       = "delete_reason"
	CommentMessageTableRepliesCountColName       = "replies_count"
	CommentMessageTableVoteColName               = "vote"
	CommentMessageTableDatetimeColName           = "datetime"
	CommentMessageTableUpdatedAtColName          = "updated_at"
	CommentMessageTableMessageColName            = "message"
	CommentMessageTableIPColName                 = "ip"
)

type CommentMessageRow struct {
	ID                 int64                            `db:"id"                  goqu:"pk,skipinsert"`
	TypeID             CommentMessageType               `db:"type_id"`
	ItemID             int64                            `db:"item_id"`
	ParentID           sql.NullInt64                    `db:"parent_id"`
	CreatedAt          time.Time                        `db:"datetime"`
	UpdatedAt          sql.NullTime                     `db:"updated_at"`
	Deleted            bool                             `db:"deleted"`
	ModeratorAttention CommentMessageModeratorAttention `db:"moderator_attention"`
	AuthorID           sql.NullInt64                    `db:"author_id"`
	IP                 pgtype.Inet                      `db:"ip"`
	Message            string                           `db:"message"`
	Vote               int32                            `db:"vote"`
}

var (
	CommentMessageTable          = goqu.T(CommentMessageTableName)
	CommentMessageTableIDCol     = CommentMessageTable.Col(CommentMessageTableIDColName)
	CommentMessageTableTypeIDCol = CommentMessageTable.Col(
		CommentMessageTableTypeIDColName,
	)
	CommentMessageTableItemIDCol = CommentMessageTable.Col(
		CommentMessageTableItemIDColName,
	)
	CommentMessageTableAuthorIDCol = CommentMessageTable.Col(
		CommentMessageTableAuthorIDColName,
	)
	CommentMessageTableDatetimeCol  = CommentMessageTable.Col(CommentMessageTableDatetimeColName)
	CommentMessageTableUpdatedAtCol = CommentMessageTable.Col(CommentMessageTableUpdatedAtColName)
	CommentMessageTableVoteCol      = CommentMessageTable.Col(
		CommentMessageTableVoteColName,
	)
	CommentMessageTableParentIDCol = CommentMessageTable.Col(
		CommentMessageTableParentIDColName,
	)
	CommentMessageTableMessageCol      = CommentMessageTable.Col(CommentMessageTableMessageColName)
	CommentMessageTableIPCol           = CommentMessageTable.Col(CommentMessageTableIPColName)
	CommentMessageTableRepliesCountCol = CommentMessageTable.Col(CommentMessageTableRepliesCountColName)
	CommentMessageTableDeletedCol      = CommentMessageTable.Col(
		CommentMessageTableDeletedColName,
	)
	CommentMessageTableModeratorAttentionCol = CommentMessageTable.Col(
		CommentMessageTableModeratorAttentionColName,
	)
)
