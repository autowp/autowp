package schema

import "github.com/doug-martin/goqu/v9"

const (
	ItemLinkTableName          = "item_link"
	ItemLinkTableIDColName     = "id"
	ItemLinkTableNameColName   = "name"
	ItemLinkTableURLColName    = "url"
	ItemLinkTableTypeColName   = "type"
	ItemLinkTableItemIDColName = "item_id"
)

var (
	ItemLinkTable          = goqu.T(ItemLinkTableName)
	ItemLinkTableIDCol     = ItemLinkTable.Col(ItemLinkTableIDColName)
	ItemLinkTableNameCol   = ItemLinkTable.Col(ItemLinkTableNameColName)
	ItemLinkTableURLCol    = ItemLinkTable.Col(ItemLinkTableURLColName)
	ItemLinkTableTypeCol   = ItemLinkTable.Col(ItemLinkTableTypeColName)
	ItemLinkTableItemIDCol = ItemLinkTable.Col(ItemLinkTableItemIDColName)
)

type LinkRow struct {
	ID     int64  `db:"id"`
	Name   string `db:"name"`
	URL    string `db:"url"`
	Type   string `db:"type"`
	ItemID int64  `db:"item_id"`
}
