package schema

import "github.com/doug-martin/goqu/v9"

const (
	AttrsZonesTableName = "attrs_zones"
)

var (
	AttrsZonesTable        = goqu.T(AttrsZonesTableName)
	AttrsZonesTableIDCol   = AttrsZonesTable.Col("id")
	AttrsZonesTableNameCol = AttrsZonesTable.Col("name")
)

type AttrsZoneRow struct {
	ID   int64  `db:"id"`
	Name string `db:"name"`
}
