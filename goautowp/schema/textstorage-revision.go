package schema

import "github.com/doug-martin/goqu/v9"

const (
	TextstorageRevisionTableName             = "textstorage_revision"
	TextstorageRevisionTableTextIDColName    = "text_id"
	TextstorageRevisionTableRevisionColName  = "revision"
	TextstorageRevisionTableTextColName      = "text"
	TextstorageRevisionTableTimestampColName = "timestamp"
	TextstorageRevisionTableUserIDColName    = "user_id"
)

var (
	TextstorageRevisionTable          = goqu.T(TextstorageRevisionTableName)
	TextstorageRevisionTableTextIDCol = TextstorageRevisionTable.Col(
		TextstorageRevisionTableTextIDColName,
	)
	TextstorageRevisionTableRevisionCol = TextstorageRevisionTable.Col(
		TextstorageRevisionTableRevisionColName,
	)
	TextstorageRevisionTableTextCol = TextstorageRevisionTable.Col(
		TextstorageRevisionTableTextColName,
	)
	TextstorageRevisionTableTimestampCol = TextstorageRevisionTable.Col(
		TextstorageRevisionTableTimestampColName,
	)
	TextstorageRevisionTableUserIDCol = TextstorageRevisionTable.Col(
		TextstorageRevisionTableUserIDColName,
	)
)
