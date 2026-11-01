package schema

import "github.com/doug-martin/goqu/v9"

const (
	AttrsUnitsTableName = "attrs_units"
)

var (
	AttrsUnitsTable        = goqu.T(AttrsUnitsTableName)
	AttrsUnitsTableIDCol   = AttrsUnitsTable.Col("id")
	AttrsUnitsTableNameCol = AttrsUnitsTable.Col("name")
	AttrsUnitsTableAbbrCol = AttrsUnitsTable.Col("abbr")
)

type AttrsUnitRow struct {
	ID   int64  `db:"id"`
	Name string `db:"name"`
	Abbr string `db:"abbr"`
}
