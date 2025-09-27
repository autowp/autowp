package schema

import (
	"database/sql"
	"net"
	"time"

	"github.com/doug-martin/goqu/v9"
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
	CommentMessageTableRepliesCountColName       = "replies_count"
	CommentMessageTableVoteColName               = "vote"
)

type CommentMessageRow struct {
	ID                 int64                            `db:"id"`
	TypeID             CommentMessageType               `db:"type_id"`
	ItemID             int64                            `db:"item_id"`
	ParentID           sql.NullInt64                    `db:"parent_id"`
	CreatedAt          time.Time                        `db:"datetime"`
	Deleted            bool                             `db:"deleted"`
	ModeratorAttention CommentMessageModeratorAttention `db:"moderator_attention"`
	AuthorID           sql.NullInt64                    `db:"author_id"`
	IP                 net.IP                           `db:"ip"`
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
	CommentMessageTableDatetimeCol = CommentMessageTable.Col("datetime")
	CommentMessageTableVoteCol     = CommentMessageTable.Col(
		CommentMessageTableVoteColName,
	)
	CommentMessageTableParentIDCol = CommentMessageTable.Col(
		CommentMessageTableParentIDColName,
	)
	CommentMessageTableMessageCol = CommentMessageTable.Col("message")
	CommentMessageTableIPCol      = CommentMessageTable.Col("ip")
	CommentMessageTableDeletedCol = CommentMessageTable.Col(
		CommentMessageTableDeletedColName,
	)
	CommentMessageTableModeratorAttentionCol = CommentMessageTable.Col(
		CommentMessageTableModeratorAttentionColName,
	)
)
