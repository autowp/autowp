package schema

import "github.com/doug-martin/goqu/v9"

const (
	CommentTopicSubscribeTableName          = "comment_topic_subscribe"
	CommentTopicSubscribeTableItemIDColName = "item_id"
	CommentTopicSubscribeTableTypeIDColName = "type_id"
	CommentTopicSubscribeTableUserIDColName = "user_id"
	CommentTopicSubscribeTableSentColName   = "sent"
)

var (
	CommentTopicSubscribeTable          = goqu.T(CommentTopicSubscribeTableName)
	CommentTopicSubscribeTableItemIDCol = CommentTopicSubscribeTable.Col(
		CommentTopicSubscribeTableItemIDColName,
	)
	CommentTopicSubscribeTableTypeIDCol = CommentTopicSubscribeTable.Col(
		CommentTopicSubscribeTableTypeIDColName,
	)
	CommentTopicSubscribeTableUserIDCol = CommentTopicSubscribeTable.Col(
		CommentTopicSubscribeTableUserIDColName,
	)
)
