package schema

import "github.com/doug-martin/goqu/v9"

const (
	PerspectivesGroupsPerspectivesTableName = "perspectives_groups_perspectives"

	PerspectivesGroupsPerspectivesTablePerspectiveIDColName = "perspective_id"
	PerspectivesGroupsPerspectivesTableGroupIDColName       = "group_id"
	PerspectivesGroupsPerspectivesTablePositionColName      = "position"
)

var (
	PerspectivesGroupsPerspectivesTable = goqu.T(
		PerspectivesGroupsPerspectivesTableName,
	)
	PerspectivesGroupsPerspectivesTablePerspectiveIDCol = PerspectivesGroupsPerspectivesTable.Col(
		PerspectivesGroupsPerspectivesTablePerspectiveIDColName,
	)
	PerspectivesGroupsPerspectivesTableGroupIDCol = PerspectivesGroupsPerspectivesTable.Col(
		PerspectivesGroupsPerspectivesTableGroupIDColName,
	)
	PerspectivesGroupsPerspectivesTablePositionCol = PerspectivesGroupsPerspectivesTable.Col(
		PerspectivesGroupsPerspectivesTablePositionColName,
	)
)

type PerspectivesGroupsPerspectiveRow struct {
	GroupID       int `db:"group_id"`
	PerspectiveID int `db:"perspective_id"`
	Position      int `db:"position"`
}
