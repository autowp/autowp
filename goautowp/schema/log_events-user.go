package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventsUserTableName              = "log_events_user"
	LogEventsUserTableLogEventIDColName = "log_event_id"
	LogEventsUserTableUserIDColName     = "user_id"
)

var LogEventsUserTable = goqu.T(LogEventsUserTableName)
