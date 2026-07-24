package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventTableName               = "log_event"
	LogEventTableIDColName          = "id"
	LogEventTableDescriptionColName = "description"
	LogEventTableUserIDColName      = "user_id"
	LogEventTableCreatedAtColName   = "created_at"
)

var (
	LogEventTable               = goqu.T(LogEventTableName)
	LogEventTableIDCol          = LogEventTable.Col(LogEventTableIDColName)
	LogEventTableDescriptionCol = LogEventTable.Col(LogEventTableDescriptionColName)
	LogEventTableUserIDCol      = LogEventTable.Col(LogEventTableUserIDColName)
	LogEventTableCreatedAtCol   = LogEventTable.Col(LogEventTableCreatedAtColName)
)
