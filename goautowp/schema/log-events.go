package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventsTableName               = "log_events"
	LogEventsTableIDColName          = "id"
	LogEventsTableDescriptionColName = "description"
	LogEventsTableUserIDColName      = "user_id"
	LogEventsTableAddDatetimeColName = "add_datetime"
)

var (
	LogEventsTable               = goqu.T(LogEventsTableName)
	LogEventsTableIDCol          = LogEventsTable.Col(LogEventsTableIDColName)
	LogEventsTableDescriptionCol = LogEventsTable.Col(LogEventsTableDescriptionColName)
	LogEventsTableUserIDCol      = LogEventsTable.Col(LogEventsTableUserIDColName)
	LogEventsTableAddDatetimeCol = LogEventsTable.Col(LogEventsTableAddDatetimeColName)
)
