package schema

import "github.com/doug-martin/goqu/v9"

const (
	PerspectivesPagesTableName = "perspectives_pages"

	PerspectivesPageFivePics = 5
)

var (
	PerspectivesPagesTable        = goqu.T(PerspectivesPagesTableName)
	PerspectivesPagesTableIDCol   = PerspectivesPagesTable.Col("id")
	PerspectivesPagesTableNameCol = PerspectivesPagesTable.Col("name")
)
