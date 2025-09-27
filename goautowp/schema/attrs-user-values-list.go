package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsUserValuesListTableName               = "attrs_user_values_list"
	AttrsUserValuesListTableUserIDColName      = AttrsUserValuesTypeTableUserIDColName
	AttrsUserValuesListTableAttributeIDColName = AttrsUserValuesTypeTableAttributeIDColName
	AttrsUserValuesListTableItemIDColName      = AttrsUserValuesTypeTableItemIDColName
	AttrsUserValuesListTableValueColName       = AttrsUserValuesTypeTableValueColName
	AttrsUserValuesListTableOrderingColName    = "ordering"
)

var (
	AttrsUserValuesListTable          = goqu.T(AttrsUserValuesListTableName)
	AttrsUserValuesListTableUserIDCol = AttrsUserValuesListTable.Col(
		AttrsUserValuesListTableUserIDColName,
	)
	AttrsUserValuesListTableAttributeIDCol = AttrsUserValuesListTable.Col(
		AttrsUserValuesListTableAttributeIDColName,
	)
	AttrsUserValuesListTableItemIDCol = AttrsUserValuesListTable.Col(
		AttrsUserValuesListTableItemIDColName,
	)
	AttrsUserValuesListTableValueCol = AttrsUserValuesListTable.Col(
		AttrsUserValuesListTableValueColName,
	)
	AttrsUserValuesListTableOrderingCol = AttrsUserValuesListTable.Col(
		AttrsUserValuesListTableOrderingColName,
	)
)

type AttrsUserValuesListRow struct {
	AttributeID int64         `db:"attribute_id"`
	ItemID      int64         `db:"item_id"`
	UserID      int64         `db:"user_id"`
	Value       sql.NullInt64 `db:"value"`
	Ordering    int64         `db:"ordering"`
}
