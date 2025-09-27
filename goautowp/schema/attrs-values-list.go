package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsValuesListTableName               = "attrs_values_list"
	AttrsValuesListTableAttributeIDColName = "attribute_id"
	AttrsValuesListTableItemIDColName      = "item_id"
	AttrsValuesListTableValueColName       = "value"
	AttrsValuesListTableOrderingColName    = "ordering"
)

var (
	AttrsValuesListTable               = goqu.T(AttrsValuesListTableName)
	AttrsValuesListTableAttributeIDCol = AttrsValuesListTable.Col(
		AttrsValuesListTableAttributeIDColName,
	)
	AttrsValuesListTableItemIDCol   = AttrsValuesListTable.Col(AttrsValuesListTableItemIDColName)
	AttrsValuesListTableValueCol    = AttrsValuesListTable.Col(AttrsValuesListTableValueColName)
	AttrsValuesListTableOrderingCol = AttrsValuesListTable.Col(
		AttrsValuesListTableOrderingColName,
	)
)

type AttrsValuesListRow struct {
	AttributeID int64         `db:"attribute_id"`
	ItemID      int64         `db:"item_id"`
	Value       sql.NullInt64 `db:"value"`
	Ordering    int64         `db:"ordering"`
}
