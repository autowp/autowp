package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventsItemTableName              = "log_events_item"
	LogEventsItemTableLogEventIDColName = "log_event_id"
	LogEventsItemTableItemIDColName     = "item_id"
)

var (
	LogEventsItemTable              = goqu.T(LogEventsItemTableName)
	LogEventsItemTableLogEventIDCol = LogEventsItemTable.Col(LogEventsItemTableLogEventIDColName)
	LogEventsItemTableItemIDCol     = LogEventsItemTable.Col(LogEventsItemTableItemIDColName)
)
