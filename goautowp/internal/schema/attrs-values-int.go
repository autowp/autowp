package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsValuesIntTableName               = "attrs_values_int"
	AttrsValuesIntTableAttributeIDColName = "attribute_id"
	AttrsValuesIntTableItemIDColName      = "item_id"
	AttrsValuesIntTableValueColName       = "value"
)

var (
	AttrsValuesIntTable               = goqu.T(AttrsValuesIntTableName)
	AttrsValuesIntTableAttributeIDCol = AttrsValuesIntTable.Col(
		AttrsValuesIntTableAttributeIDColName,
	)
	AttrsValuesIntTableItemIDCol = AttrsValuesIntTable.Col(AttrsValuesIntTableItemIDColName)
	AttrsValuesIntTableValueCol  = AttrsValuesIntTable.Col(AttrsValuesIntTableValueColName)
)

type AttrsValuesIntRow struct {
	AttributeID int64         `db:"attribute_id"`
	ItemID      int64         `db:"item_id"`
	Value       sql.NullInt32 `db:"value"`
}
