package schema

import "github.com/doug-martin/goqu/v9"

const (
	ItemPointTableName          = "item_point"
	ItemPointTableItemIDColName = "item_id"
	ItemPointTablePointColName  = "point"
)

var (
	ItemPointTable          = goqu.T(ItemPointTableName)
	ItemPointTablePointCol  = ItemPointTable.Col(ItemPointTablePointColName)
	ItemPointTableItemIDCol = ItemPointTable.Col(ItemPointTableItemIDColName)
)
