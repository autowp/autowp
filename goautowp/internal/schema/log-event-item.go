package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventItemTableName              = "log_event_item"
	LogEventItemTableLogEventIDColName = "log_event_id"
	LogEventItemTableItemIDColName     = "item_id"
)

var (
	LogEventItemTable              = goqu.T(LogEventItemTableName)
	LogEventItemTableLogEventIDCol = LogEventItemTable.Col(LogEventItemTableLogEventIDColName)
	LogEventItemTableItemIDCol     = LogEventItemTable.Col(LogEventItemTableItemIDColName)
)
