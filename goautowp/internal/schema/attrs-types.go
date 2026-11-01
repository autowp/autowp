package schema

import "github.com/doug-martin/goqu/v9"

type AttrsAttributeTypeID int32

const (
	AttrsTypesTableName = "attrs_types"

	AttrsAttributeTypeIDUnknown AttrsAttributeTypeID = 0
	AttrsAttributeTypeIDString  AttrsAttributeTypeID = 1
	AttrsAttributeTypeIDInteger AttrsAttributeTypeID = 2
	AttrsAttributeTypeIDFloat   AttrsAttributeTypeID = 3
	AttrsAttributeTypeIDText    AttrsAttributeTypeID = 4
	AttrsAttributeTypeIDBoolean AttrsAttributeTypeID = 5
	AttrsAttributeTypeIDList    AttrsAttributeTypeID = 6
	AttrsAttributeTypeIDTree    AttrsAttributeTypeID = 7
)

var (
	AttrsTypesTable        = goqu.T(AttrsTypesTableName)
	AttrsTypesTableIDCol   = AttrsTypesTable.Col("id")
	AttrsTypesTableNameCol = AttrsTypesTable.Col("name")
)

type AttrsAttributeTypeRow struct {
	ID   AttrsAttributeTypeID `db:"id"`
	Name string               `db:"name"`
}
