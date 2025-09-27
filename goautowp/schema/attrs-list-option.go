package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsListOptionsTableName            = "attrs_list_options"
	AttrsListOptionsTablePositionColName = "position"
)

var (
	AttrsListOptionsTable               = goqu.T(AttrsListOptionsTableName)
	AttrsListOptionsTableIDCol          = AttrsListOptionsTable.Col("id")
	AttrsListOptionsTableNameCol        = AttrsListOptionsTable.Col("name")
	AttrsListOptionsTableAttributeIDCol = AttrsListOptionsTable.Col("attribute_id")
	AttrsListOptionsTableParentIDCol    = AttrsListOptionsTable.Col("parent_id")
	AttrsListOptionsTablePositionCol    = AttrsListOptionsTable.Col(
		AttrsListOptionsTablePositionColName,
	)
)

type AttrsListOptionRow struct {
	ID          int64         `db:"id"`
	Name        string        `db:"name"`
	AttributeID int64         `db:"attribute_id"`
	ParentID    sql.NullInt64 `db:"parent_id"`
}

const (
	EngineTurboNone int64 = 46
	EngineTurboYes  int64 = 47
	EngineTurboX2   int64 = 48
	EngineTurboX3   int64 = 64
	EngineTurboX4   int64 = 49
)
