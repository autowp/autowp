package schema

import "github.com/doug-martin/goqu/v9"

const (
	PerspectivePageTableName = "perspective_page"

	PerspectivePageFivePics = 5
)

var (
	PerspectivePageTable        = goqu.T(PerspectivePageTableName)
	PerspectivePageTableIDCol   = PerspectivePageTable.Col("id")
	PerspectivePageTableNameCol = PerspectivePageTable.Col("name")
)
