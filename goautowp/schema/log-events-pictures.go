package schema

import "github.com/doug-martin/goqu/v9"

const (
	LogEventsPicturesTableName              = "log_events_pictures"
	LogEventsPicturesTableLogEventIDColName = "log_event_id"
	LogEventsPicturesTablePictureIDColName  = "picture_id"
)

var (
	LogEventsPicturesTable              = goqu.T(LogEventsPicturesTableName)
	LogEventsPicturesTableLogEventIDCol = LogEventsPicturesTable.Col(
		LogEventsPicturesTableLogEventIDColName,
	)
	LogEventsPicturesTablePictureIDCol = LogEventsPicturesTable.Col(
		LogEventsPicturesTablePictureIDColName,
	)
)
