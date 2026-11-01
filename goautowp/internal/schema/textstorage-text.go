package schema

import "github.com/doug-martin/goqu/v9"

const (
	TextstorageTextTableName               = "textstorage_text"
	TextstorageTextTableIDColName          = "id"
	TextstorageTextTableTextColName        = "text"
	TextstorageTextTableLastUpdatedColName = "last_updated"
	TextstorageTextTableRevisionColName    = "revision"
)

var (
	TextstorageTextTable            = goqu.T(TextstorageTextTableName)
	TextstorageTextTableIDCol       = TextstorageTextTable.Col(TextstorageTextTableIDColName)
	TextstorageTextTableTextCol     = TextstorageTextTable.Col(TextstorageTextTableTextColName)
	TextstorageTextTableRevisionCol = TextstorageTextTable.Col(
		TextstorageTextTableRevisionColName,
	)
	TextstorageTextTableLastUpdatedCol = TextstorageTextTable.Col(
		TextstorageTextTableLastUpdatedColName,
	)
)
