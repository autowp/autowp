package schema

import "github.com/doug-martin/goqu/v9"

// ItemLanguageCacheTable holds, per item and per UI/viewer language, the name already resolved
// through the language-priority fallback chain (see items.languagePriority). Unlike item_language
// (keyed by the language of the translation itself), this table is keyed by the viewer's requested
// language, so reads are a plain indexed lookup instead of a per-row sort over item_language.
const (
	ItemLanguageCacheTableName            = "item_language_cache"
	ItemLanguageCacheTableItemIDColName   = "item_id"
	ItemLanguageCacheTableLanguageColName = "language"
	ItemLanguageCacheTableNameColName     = "name"
)

var (
	ItemLanguageCacheTable            = goqu.T(ItemLanguageCacheTableName)
	ItemLanguageCacheTableItemIDCol   = ItemLanguageCacheTable.Col(ItemLanguageCacheTableItemIDColName)
	ItemLanguageCacheTableLanguageCol = ItemLanguageCacheTable.Col(ItemLanguageCacheTableLanguageColName)
	ItemLanguageCacheTableNameCol     = ItemLanguageCacheTable.Col(ItemLanguageCacheTableNameColName)
)
