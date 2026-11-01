package schema

import "github.com/doug-martin/goqu/v9"

const (
	CommentTopicTableName              = "comment_topic"
	CommentTopicTableItemIDColName     = "item_id"
	CommentTopicTableTypeIDColName     = "type_id"
	CommentTopicTableLastUpdateColName = "last_update"
	CommentTopicTableMessagesColName   = "messages"
)

var (
	CommentTopicTable              = goqu.T(CommentTopicTableName)
	CommentTopicTableItemIDCol     = CommentTopicTable.Col("item_id")
	CommentTopicTableTypeIDCol     = CommentTopicTable.Col("type_id")
	CommentTopicTableLastUpdateCol = CommentTopicTable.Col("last_update")
	CommentTopicTableMessagesCol   = CommentTopicTable.Col("messages")
)
