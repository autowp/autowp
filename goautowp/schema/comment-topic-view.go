package schema

import "github.com/doug-martin/goqu/v9"

const (
	CommentTopicViewTableName             = "comment_topic_view"
	CommentTopicViewTableUserIDColName    = "user_id"
	CommentTopicViewTableTypeIDColName    = "type_id"
	CommentTopicViewTableItemIDColName    = "item_id"
	CommentTopicViewTableTimestampColName = "timestamp"
)

var (
	CommentTopicViewTable          = goqu.T(CommentTopicViewTableName)
	CommentTopicViewTableUserIDCol = CommentTopicViewTable.Col(
		CommentTopicViewTableUserIDColName,
	)
	CommentTopicViewTableTypeIDCol = CommentTopicViewTable.Col(
		CommentTopicViewTableTypeIDColName,
	)
	CommentTopicViewTableItemIDCol = CommentTopicViewTable.Col(
		CommentTopicViewTableItemIDColName,
	)
	CommentTopicViewTableTimestampCol = CommentTopicViewTable.Col(
		CommentTopicViewTableTimestampColName,
	)
)
