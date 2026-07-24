package schema

import "github.com/doug-martin/goqu/v9"

const (
	PerspectiveGroupPerspectiveTableName = "perspective_group_perspective"

	PerspectiveGroupPerspectiveTablePerspectiveIDColName = "perspective_id"
	PerspectiveGroupPerspectiveTableGroupIDColName       = "group_id"
	PerspectiveGroupPerspectiveTablePositionColName      = "position"
)

var (
	PerspectiveGroupPerspectiveTable = goqu.T(
		PerspectiveGroupPerspectiveTableName,
	)
	PerspectiveGroupPerspectiveTablePerspectiveIDCol = PerspectiveGroupPerspectiveTable.Col(
		PerspectiveGroupPerspectiveTablePerspectiveIDColName,
	)
	PerspectiveGroupPerspectiveTableGroupIDCol = PerspectiveGroupPerspectiveTable.Col(
		PerspectiveGroupPerspectiveTableGroupIDColName,
	)
	PerspectiveGroupPerspectiveTablePositionCol = PerspectiveGroupPerspectiveTable.Col(
		PerspectiveGroupPerspectiveTablePositionColName,
	)
)

type PerspectiveGroupPerspectiveRow struct {
	GroupID       int `db:"group_id"`
	PerspectiveID int `db:"perspective_id"`
	Position      int `db:"position"`
}
