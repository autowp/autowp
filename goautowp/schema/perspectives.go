package schema

import "github.com/doug-martin/goqu/v9"

const (
	PerspectivesTableName = "perspectives"

	PerspectiveFront          = 1
	PerspectiveBack           = 2
	PerspectiveLeft           = 3
	PerspectiveRight          = 4
	PerspectiveInterior       = 5
	PerspectiveFrontPanel     = 6
	Perspective3Div4Left      = 7
	Perspective3Div4Right     = 8
	PerspectiveCutaway        = 9
	PerspectiveFrontStrict    = 10
	PerspectiveLeftStrict     = 11
	PerspectiveRightStrict    = 12
	PerspectiveBackStrict     = 13
	PerspectiveIDUnderTheHood = 17
	PerspectiveDashboard      = 20
	PerspectiveBoot           = 21
	PerspectiveLogo           = 22
	PerspectiveMascot         = 23
	PerspectiveSketch         = 24
	PerspectiveMixed          = 25
	PerspectiveChassis        = 28
)

var (
	PerspectivesTable            = goqu.T(PerspectivesTableName)
	PerspectivesTableIDCol       = PerspectivesTable.Col("id")
	PerspectivesTablePositionCol = PerspectivesTable.Col("position")
	PerspectivesTableNameCol     = PerspectivesTable.Col("name")
)

type PerspectiveRow struct {
	ID   int32  `db:"id"`
	Name string `db:"name"`
}
