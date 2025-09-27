package schema

import "github.com/doug-martin/goqu/v9"

type ItemParentType int8

const (
	ItemParentTypeDefault ItemParentType = 0
	ItemParentTypeTuning  ItemParentType = 1
	ItemParentTypeSport   ItemParentType = 2
	ItemParentTypeDesign  ItemParentType = 3

	ItemParentTableName                 = "item_parent"
	ItemParentTableParentIDColName      = "parent_id"
	ItemParentTableItemIDColName        = "item_id"
	ItemParentTableTypeColName          = "type"
	ItemParentTableCatnameColName       = "catname"
	ItemParentTableManualCatnameColName = "manual_catname"
	ItemParentTableTimestampColName     = "timestamp"

	ItemParentMaxCatname = 150
)

var (
	ItemParentTable                 = goqu.T(ItemParentTableName)
	ItemParentTableParentIDCol      = ItemParentTable.Col(ItemParentTableParentIDColName)
	ItemParentTableItemIDCol        = ItemParentTable.Col(ItemParentTableItemIDColName)
	ItemParentTableTypeCol          = ItemParentTable.Col(ItemParentTableTypeColName)
	ItemParentTableCatnameCol       = ItemParentTable.Col(ItemParentTableCatnameColName)
	ItemParentTableManualCatnameCol = ItemParentTable.Col(ItemParentTableManualCatnameColName)
)

type ItemParentRow struct {
	ItemID        int64          `db:"item_id"`
	ParentID      int64          `db:"parent_id"`
	Catname       string         `db:"catname"`
	Type          ItemParentType `db:"type"`
	ManualCatname bool           `db:"manual_catname"`
}
