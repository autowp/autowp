package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsValuesStringTableName               = "attrs_values_string"
	AttrsValuesStringTableAttributeIDColName = "attribute_id"
	AttrsValuesStringTableItemIDColName      = "item_id"
	AttrsValuesStringTableValueColName       = "value"
)

var (
	AttrsValuesStringTable               = goqu.T(AttrsValuesStringTableName)
	AttrsValuesStringTableAttributeIDCol = AttrsValuesStringTable.Col(
		AttrsValuesStringTableAttributeIDColName,
	)
	AttrsValuesStringTableItemIDCol = AttrsValuesStringTable.Col(
		AttrsValuesStringTableItemIDColName,
	)
	AttrsValuesStringTableValueCol = AttrsValuesStringTable.Col(
		AttrsValuesStringTableValueColName,
	)
)

type AttrsValuesStringRow struct {
	AttributeID int64          `db:"attribute_id"`
	ItemID      int64          `db:"item_id"`
	Value       sql.NullString `db:"value"`
}
