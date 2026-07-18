package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsUserValuesTableName               = "attrs_user_values"
	AttrsUserValuesTableUserIDColName      = "user_id"
	AttrsUserValuesTableAddDateColName     = "add_date"
	AttrsUserValuesTableUpdateDateColName  = "update_date"
	AttrsUserValuesTableItemIDColName      = "item_id"
	AttrsUserValuesTableAttributeIDColName = "attribute_id"
	AttrsUserValuesTableConflictColName    = "conflict"
	AttrsUserValuesTableWeightColName      = "weight"

	AttrsUserValuesTypeTableUserIDColName      = "user_id"
	AttrsUserValuesTypeTableAttributeIDColName = "attribute_id"
	AttrsUserValuesTypeTableItemIDColName      = "item_id"
	AttrsUserValuesTypeTableValueColName       = "value"
)

var (
	AttrsUserValuesTable               = goqu.T(AttrsUserValuesTableName)
	AttrsUserValuesTableUserIDCol      = AttrsUserValuesTable.Col(AttrsUserValuesTableUserIDColName)
	AttrsUserValuesTableItemIDCol      = AttrsUserValuesTable.Col(AttrsUserValuesTableItemIDColName)
	AttrsUserValuesTableWeightCol      = AttrsUserValuesTable.Col(AttrsUserValuesTableWeightColName)
	AttrsUserValuesTableAttributeIDCol = AttrsUserValuesTable.Col(
		AttrsUserValuesTableAttributeIDColName,
	)
	AttrsUserValuesTableConflictCol = AttrsUserValuesTable.Col(
		AttrsUserValuesTableConflictColName,
	)
)

type AttrsUserValueRow struct {
	AttributeID int64        `db:"attribute_id"`
	ItemID      int64        `db:"item_id"`
	UserID      int64        `db:"user_id"`
	Conflict    int16        `db:"conflict"`
	UpdateDate  sql.NullTime `db:"update_date"`
	AddDate     sql.NullTime `db:"add_date"`
}
