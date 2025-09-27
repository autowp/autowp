package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventsArticlesTableName              = "log_events_articles"
	LogEventsArticlesTableLogEventIDColName = "log_event_id"
	LogEventsArticlesTableArticleIDColName  = "article_id"
)

var (
	LogEventsArticlesTable              = goqu.T(LogEventsArticlesTableName)
	LogEventsArticlesTableLogEventIDCol = LogEventsArticlesTable.Col(
		LogEventsArticlesTableLogEventIDColName,
	)
	LogEventsArticlesTableArticleIDCol = LogEventsArticlesTable.Col(
		LogEventsArticlesTableArticleIDColName,
	)
)
