package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsUserValuesFloatTableName               = "attrs_user_values_float"
	AttrsUserValuesFloatTableUserIDColName      = AttrsUserValuesTypeTableUserIDColName
	AttrsUserValuesFloatTableAttributeIDColName = AttrsUserValuesTypeTableAttributeIDColName
	AttrsUserValuesFloatTableItemIDColName      = AttrsUserValuesTypeTableItemIDColName
	AttrsUserValuesFloatTableValueColName       = AttrsUserValuesTypeTableValueColName
)

var (
	AttrsUserValuesFloatTable          = goqu.T(AttrsUserValuesFloatTableName)
	AttrsUserValuesFloatTableUserIDCol = AttrsUserValuesFloatTable.Col(
		AttrsUserValuesFloatTableUserIDColName,
	)
	AttrsUserValuesFloatTableAttributeIDCol = AttrsUserValuesFloatTable.Col(
		AttrsUserValuesFloatTableAttributeIDColName,
	)
	AttrsUserValuesFloatTableItemIDCol = AttrsUserValuesFloatTable.Col(
		AttrsUserValuesFloatTableItemIDColName,
	)
	AttrsUserValuesFloatTableValueCol = AttrsUserValuesFloatTable.Col(
		AttrsUserValuesFloatTableValueColName,
	)
)

type AttrsUserValuesFloatRow struct {
	AttributeID int64           `db:"attribute_id"`
	ItemID      int64           `db:"item_id"`
	UserID      int64           `db:"user_id"`
	Value       sql.NullFloat64 `db:"value"`
}
