package schema

import "github.com/doug-martin/goqu/v9"

const (
	VehicleTypeTableName = "vehicle_type"

	VehicleTypeTableIDColName       = "id"
	VehicleTypeTableCatnameColName  = "catname"
	VehicleTypeTableParentIDColName = "parent_id"
	VehicleTypeTableNameRpColName   = "name_rp"
	VehicleTypeTablePositionColName = "position"

	VehicleTypeCarID = 29
)

var (
	VehicleTypeTable            = goqu.T(VehicleTypeTableName)
	VehicleTypeTableIDCol       = VehicleTypeTable.Col(VehicleTypeTableIDColName)
	VehicleTypeTableNameCol     = VehicleTypeTable.Col("name")
	VehicleTypeTableCatnameCol  = VehicleTypeTable.Col(VehicleTypeTableCatnameColName)
	VehicleTypeTablePositionCol = VehicleTypeTable.Col(VehicleTypeTablePositionColName)
	VehicleTypeTableParentIDCol = VehicleTypeTable.Col(VehicleTypeTableParentIDColName)
)

type VehicleTypeRow struct {
	ID      int64  `db:"id"`
	Catname string `db:"catname"`
	NameRp  string `db:"name_rp"`
}
