package schema

import "github.com/doug-martin/goqu/v9"

const (
	SpecTableName             = "spec"
	SpecTableIDColName        = "id"
	SpecTableNameColName      = "name"
	SpecTableShortNameColName = "short_name"
	SpecTableParentIDColName  = "parent_id"

	SpecIDNorthAmerica = 1
	SpecIDWorldwide    = 29
)

var (
	SpecTable             = goqu.T(SpecTableName)
	SpecTableIDCol        = SpecTable.Col(SpecTableIDColName)
	SpecTableNameCol      = SpecTable.Col(SpecTableNameColName)
	SpecTableShortNameCol = SpecTable.Col(SpecTableShortNameColName)
	SpecTableParentIDCol  = SpecTable.Col(SpecTableParentIDColName)
)

type SpecRow struct {
	ID        int32  `db:"id"`
	ShortName string `db:"short_name"`
	Name      string `db:"name"`
}
