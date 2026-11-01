package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventPictureTableName              = "log_event_picture"
	LogEventPictureTableLogEventIDColName = "log_event_id"
	LogEventPictureTablePictureIDColName  = "picture_id"
)

var (
	LogEventPictureTable              = goqu.T(LogEventPictureTableName)
	LogEventPictureTableLogEventIDCol = LogEventPictureTable.Col(
		LogEventPictureTableLogEventIDColName,
	)
	LogEventPictureTablePictureIDCol = LogEventPictureTable.Col(
		LogEventPictureTablePictureIDColName,
	)
)
