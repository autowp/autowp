package schema

import "github.com/doug-martin/goqu/v9"

const (
	VehicleTypeParentTableName            = "vehicle_type_parent"
	VehicleTypeParentTableIDColName       = "id"
	VehicleTypeParentTableParentIDColName = "parent_id"
)

var (
	VehicleTypeParentTable            = goqu.T(VehicleTypeParentTableName)
	VehicleTypeParentTableIDCol       = VehicleTypeParentTable.Col(VehicleTypeParentTableIDColName)
	VehicleTypeParentTableParentIDCol = VehicleTypeParentTable.Col(
		VehicleTypeParentTableParentIDColName,
	)
)
