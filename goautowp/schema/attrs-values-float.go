package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsValuesFloatTableName               = "attrs_values_float"
	AttrsValuesFloatTableAttributeIDColName = "attribute_id"
	AttrsValuesFloatTableItemIDColName      = "item_id"
	AttrsValuesFloatTableValueColName       = "value"
)

var (
	AttrsValuesFloatTable               = goqu.T(AttrsValuesFloatTableName)
	AttrsValuesFloatTableAttributeIDCol = AttrsValuesFloatTable.Col(
		AttrsValuesFloatTableAttributeIDColName,
	)
	AttrsValuesFloatTableItemIDCol = AttrsValuesFloatTable.Col(
		AttrsValuesFloatTableItemIDColName,
	)
	AttrsValuesFloatTableValueCol = AttrsValuesFloatTable.Col(
		AttrsValuesFloatTableValueColName,
	)
)

type AttrsValuesFloatRow struct {
	AttributeID int64           `db:"attribute_id"`
	ItemID      int64           `db:"item_id"`
	Value       sql.NullFloat64 `db:"value"`
}
