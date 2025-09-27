package schema

import "github.com/doug-martin/goqu/v9"

const (
	DfHashTableName             = "df_hash"
	DfHashTableHashColName      = "hash"
	DfHashTablePictureIDColName = "picture_id"
)

var (
	DfHashTable             = goqu.T(DfHashTableName)
	DfHashTableHashCol      = DfHashTable.Col(DfHashTableHashColName)
	DfHashTablePictureIDCol = DfHashTable.Col(DfHashTablePictureIDColName)
)
