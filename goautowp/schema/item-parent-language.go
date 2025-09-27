package schema

import "github.com/doug-martin/goqu/v9"

const (
	ItemParentLanguageTableName            = "item_parent_language"
	ItemParentLanguageTableItemIDColName   = "item_id"
	ItemParentLanguageTableParentIDColName = "parent_id"
	ItemParentLanguageTableLanguageColName = "language"
	ItemParentLanguageTableNameColName     = "name"
	ItemParentLanguageTableIsAutoColName   = "is_auto"
)

var (
	ItemParentLanguageTable          = goqu.T(ItemParentLanguageTableName)
	ItemParentLanguageTableItemIDCol = ItemParentLanguageTable.Col(
		ItemParentLanguageTableItemIDColName,
	)
	ItemParentLanguageTableParentIDCol = ItemParentLanguageTable.Col(
		ItemParentLanguageTableParentIDColName,
	)
	ItemParentLanguageTableLanguageCol = ItemParentLanguageTable.Col(
		ItemParentLanguageTableLanguageColName,
	)
	ItemParentLanguageTableNameCol = ItemParentLanguageTable.Col(
		ItemParentLanguageTableNameColName,
	)
	ItemParentLanguageTableIsAutoCol = ItemParentLanguageTable.Col(
		ItemParentLanguageTableIsAutoColName,
	)
)

type ItemParentLanguageRow struct {
	Name     string `db:"name"`
	IsAuto   bool   `db:"is_auto"`
	Language string `db:"language"`
}
