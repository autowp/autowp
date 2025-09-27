package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	ItemLanguageTableName              = "item_language"
	ItemLanguageTableItemIDColName     = "item_id"
	ItemLanguageTableLanguageColName   = "language"
	ItemLanguageTableNameColName       = "name"
	ItemLanguageTableTextIDColName     = "text_id"
	ItemLanguageTableFullTextIDColName = "full_text_id"

	ItemLanguageNameMaxLength = 255
)

var (
	ItemLanguageTable              = goqu.T(ItemLanguageTableName)
	ItemLanguageTableItemIDCol     = ItemLanguageTable.Col(ItemLanguageTableItemIDColName)
	ItemLanguageTableLanguageCol   = ItemLanguageTable.Col(ItemLanguageTableLanguageColName)
	ItemLanguageTableNameCol       = ItemLanguageTable.Col(ItemLanguageTableNameColName)
	ItemLanguageTableTextIDCol     = ItemLanguageTable.Col(ItemLanguageTableTextIDColName)
	ItemLanguageTableFullTextIDCol = ItemLanguageTable.Col(ItemLanguageTableFullTextIDColName)
)

type ItemLanguageRow struct {
	ItemID     int64          `db:"item_id"`
	Language   string         `db:"language"`
	Name       sql.NullString `db:"name"`
	TextID     sql.NullInt32  `db:"text_id"`
	FullTextID sql.NullInt32  `db:"full_text_id"`
}
