package schema

import "github.com/doug-martin/goqu/v9"

const (
	CommentVoteTableName             = "comment_vote"
	CommentVoteTableUserIDColName    = "user_id"
	CommentVoteTableCommentIDColName = "comment_id"
	CommentVoteTableVoteColName      = "vote"
)

var (
	CommentVoteTable             = goqu.T(CommentVoteTableName)
	CommentVoteTableUserIDCol    = CommentVoteTable.Col(CommentVoteTableUserIDColName)
	CommentVoteTableCommentIDCol = CommentVoteTable.Col(CommentVoteTableCommentIDColName)
	CommentVoteTableVoteCol      = CommentVoteTable.Col(CommentVoteTableVoteColName)
)
