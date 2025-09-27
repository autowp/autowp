package schema

import (
	"time"

	"github.com/doug-martin/goqu/v9"
)

const (
	AttrsValuesTableName               = "attrs_values"
	AttrsValuesTableAttributeIDColName = "attribute_id"
	AttrsValuesTableItemIDColName      = "item_id"
	AttrsValuesTableConflictColName    = "conflict"
	AttrsValuesTableUpdateDateColName  = "update_date"
)

var (
	AttrsValuesTable               = goqu.T(AttrsValuesTableName)
	AttrsValuesTableAttributeIDCol = AttrsValuesTable.Col(AttrsValuesTableAttributeIDColName)
	AttrsValuesTableItemIDCol      = AttrsValuesTable.Col(AttrsValuesTableItemIDColName)
	AttrsValuesTableConflictCol    = AttrsValuesTable.Col(AttrsValuesTableConflictColName)
	AttrsValuesTableUpdateDateCole = AttrsValuesTable.Col(AttrsValuesTableUpdateDateColName)
)

type AttrsValueRow struct {
	AttributeID int64     `db:"attribute_id"`
	ItemID      int64     `db:"item_id"`
	Conflict    bool      `db:"conflict"`
	UpdateDate  time.Time `db:"update_date"`
}
