package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventUserTableName              = "log_event_user"
	LogEventUserTableLogEventIDColName = "log_event_id"
	LogEventUserTableUserIDColName     = "user_id"
)

var LogEventUserTable = goqu.T(LogEventUserTableName)
