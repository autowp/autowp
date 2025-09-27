package schema

import "github.com/doug-martin/goqu/v9"

const (
	AttrsZoneAttributesTableName               = "attrs_zone_attributes"
	AttrsZoneAttributesTableZoneIDColName      = "zone_id"
	AttrsZoneAttributesTableAttributeIDColName = "attribute_id"
	AttrsZoneAttributesTablePositionColName    = "position"
)

var (
	AttrsZoneAttributesTable          = goqu.T(AttrsZoneAttributesTableName)
	AttrsZoneAttributesTableZoneIDCol = AttrsZoneAttributesTable.Col(
		AttrsZoneAttributesTableZoneIDColName,
	)
	AttrsZoneAttributesTableAttributeIDCol = AttrsZoneAttributesTable.Col(
		AttrsZoneAttributesTableAttributeIDColName,
	)
	AttrsZoneAttributesTablePositionCol = AttrsZoneAttributesTable.Col(
		AttrsZoneAttributesTablePositionColName,
	)
)

type AttrsZoneAttributeRow struct {
	ZoneID      int64 `db:"zone_id"`
	AttributeID int64 `db:"attribute_id"`
}
