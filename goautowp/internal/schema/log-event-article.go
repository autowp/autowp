package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventArticleTableName              = "log_event_article"
	LogEventArticleTableLogEventIDColName = "log_event_id"
	LogEventArticleTableArticleIDColName  = "article_id"
)

var (
	LogEventArticleTable              = goqu.T(LogEventArticleTableName)
	LogEventArticleTableLogEventIDCol = LogEventArticleTable.Col(
		LogEventArticleTableLogEventIDColName,
	)
	LogEventArticleTableArticleIDCol = LogEventArticleTable.Col(
		LogEventArticleTableArticleIDColName,
	)
)
