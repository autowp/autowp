package schema

import (
	"database/sql"
	"database/sql/driver"
	"errors"

	"github.com/doug-martin/goqu/v9"
)

var errUnsupportedAttributeTypeID = errors.New("unsupported type for AttributeTypeID")

type NullAttributeTypeID struct {
	AttributeTypeID AttrsAttributeTypeID
	Valid           bool // Valid is true if AttributeTypeID is not NULL
}

// Scan implements the Scanner interface.
func (n *NullAttributeTypeID) Scan(value any) error {
	if value == nil {
		n.AttributeTypeID, n.Valid = AttrsAttributeTypeIDUnknown, false

		return nil
	}

	n.Valid = true

	v, ok := value.(int64)
	if !ok {
		return errUnsupportedAttributeTypeID
	}

	n.AttributeTypeID = AttrsAttributeTypeID(v) //nolint: gosec

	return nil
}

// Value implements the driver Valuer interface.
func (n NullAttributeTypeID) Value() (driver.Value, error) {
	if !n.Valid {
		return nil, nil //nolint: nilnil
	}

	return n.AttributeTypeID, nil
}

const (
	AttrsAttributesTableName = "attrs_attributes"
)

var (
	AttrsAttributesTable               = goqu.T(AttrsAttributesTableName)
	AttrsAttributesTableIDCol          = AttrsAttributesTable.Col("id")
	AttrsAttributesTableNameCol        = AttrsAttributesTable.Col("name")
	AttrsAttributesTableDescriptionCol = AttrsAttributesTable.Col("description")
	AttrsAttributesTableTypeIDCol      = AttrsAttributesTable.Col("type_id")
	AttrsAttributesTableUnitIDCol      = AttrsAttributesTable.Col("unit_id")
	AttrsAttributesTableMultipleCol    = AttrsAttributesTable.Col("multiple")
	AttrsAttributesTablePrecisionCol   = AttrsAttributesTable.Col("precision")
	AttrsAttributesTableParentIDCol    = AttrsAttributesTable.Col("parent_id")
	AttrsAttributesTablePositionCol    = AttrsAttributesTable.Col("position")
)

type AttrsAttributeRow struct {
	ID          int64               `db:"id"`
	Name        string              `db:"name"`
	ParentID    sql.NullInt64       `db:"parent_id"`
	Description sql.NullString      `db:"description"`
	TypeID      NullAttributeTypeID `db:"type_id"`
	UnitID      sql.NullInt64       `db:"unit_id"`
	Multiple    bool                `db:"multiple"`
	Precision   sql.NullInt32       `db:"precision"`
}

const (
	LengthAttr                             int64 = 1
	WidthAttr                              int64 = 2
	HeightAttr                             int64 = 3
	ClearanceAttr                          int64 = 7
	FrontSuspensionTypeAttr                int64 = 8
	RearSuspensionType                     int64 = 9
	TurningDiameterAttr                    int64 = 11
	EnginePlacementAttr                    int64 = 19
	EnginePlacementPlacementAttr           int64 = 20
	EnginePlacementOrientationAttr         int64 = 21
	FuelSupplySystemAttr                   int64 = 23
	EngineConfigurationAttr                int64 = 24
	EngineConfigurationCylindersCountAttr  int64 = 25
	EngineConfigurationCylindersLayoutAttr int64 = 26
	EngineConfigurationValvesCountAttr     int64 = 27
	EngineCylinderDiameter                 int64 = 28
	EngineStrokeAttr                       int64 = 29
	EngineVolumeAttr                       int64 = 31
	EnginePowerAttr                        int64 = 33
	DriveUnitAttr                          int64 = 41
	GearboxAttr                            int64 = 42
	GearboxTypeAttr                        int64 = 43
	GearboxGearsAttr                       int64 = 44
	MaxSpeedAttr                           int64 = 47
	AccelerationTo100KmhAttr               int64 = 48
	SpeedLimiterAttr                       int64 = 53
	FuelTankAttr                           int64 = 57
	FuelTankPrimaryAttr                    int64 = 58
	FuelTankSecondaryAttr                  int64 = 59
	BootVolumeAttr                         int64 = 60
	BootVolumeMinAttr                      int64 = 61
	BootVolumeMaxAttr                      int64 = 62
	AirResistanceFrontal                   int64 = 64
	CurbWeightAttr                         int64 = 72
	ABSAttr                                int64 = 77
	FuelConsumptionMixedAttr               int64 = 81
	EmissionsAttr                          int64 = 82
	FrontWheelAttr                         int64 = 85
	RearWheelAttr                          int64 = 86
	FrontWheelTyreWidthAttr                int64 = 87
	FrontWheelRadiusAttr                   int64 = 88
	FrontWheelRimWidthAttr                 int64 = 89
	FrontWheelTyreSeriesAttr               int64 = 90
	RearWheelTyreWidthAttr                 int64 = 91
	RearWheelRadiusAttr                    int64 = 92
	RearWheelRimWidthAttr                  int64 = 93
	RearWheelTyreSeriesAttr                int64 = 94
	FuelTypeAttr                           int64 = 98
	EngineTurboAttr                        int64 = 99
	EngineNameAttr                         int64 = 100
	GearboxNameAttr                        int64 = 139
	FrontBrakesDiameterAttr                int64 = 146
	RearBrakesDiameterAttr                 int64 = 147
	FrontBrakesThicknessAttr               int64 = 148
	RearBrakesThicknessAttr                int64 = 149
	AccelerationTo60MphAttr                int64 = 175
	EngineTypeAttr                         int64 = 207
)
