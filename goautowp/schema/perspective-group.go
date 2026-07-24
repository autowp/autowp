package schema

import "github.com/doug-martin/goqu/v9"

const (
	PerspectiveGroupTableName = "perspective_group"

	PerspectiveGroupAPI = 31
)

var (
	PerspectiveGroupTable            = goqu.T(PerspectiveGroupTableName)
	PerspectiveGroupTableIDCol       = PerspectiveGroupTable.Col("id")
	PerspectiveGroupTableNameCol     = PerspectiveGroupTable.Col("name")
	PerspectiveGroupTablePageIDCol   = PerspectiveGroupTable.Col("page_id")
	PerspectiveGroupTablePositionCol = PerspectiveGroupTable.Col("position")
)
