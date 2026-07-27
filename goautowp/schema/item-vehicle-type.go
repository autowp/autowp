package schema

import "github.com/doug-martin/goqu/v9"

const (
	ItemVehicleTypeTableName                 = "item_vehicle_type"
	ItemVehicleTypeTableVehicleTypeIDColName = "vehicle_type_id"
	ItemVehicleTypeTableItemIDColName        = "item_id"
	ItemVehicleTypeTableInheritedColName     = "inherited"
)

var (
	ItemVehicleTypeTable                 = goqu.T(ItemVehicleTypeTableName)
	ItemVehicleTypeTableVehicleTypeIDCol = ItemVehicleTypeTable.Col(
		ItemVehicleTypeTableVehicleTypeIDColName,
	)
	ItemVehicleTypeTableItemIDCol = ItemVehicleTypeTable.Col(
		ItemVehicleTypeTableItemIDColName,
	)
	ItemVehicleTypeTableInheritedCol = ItemVehicleTypeTable.Col(
		ItemVehicleTypeTableInheritedColName,
	)
)

type ItemVehicleTypeRow struct {
	ItemID        int64 `db:"item_id"`
	VehicleTypeID int64 `db:"vehicle_type_id"`
}
