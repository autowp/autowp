package schema

import "github.com/doug-martin/goqu/v9"

const (
	ItemParentCacheTableName            = "item_parent_cache"
	ItemParentCacheTableItemIDColName   = "item_id"
	ItemParentCacheTableParentIDColName = "parent_id"
	ItemParentCacheTableDiffColName     = "diff"
	ItemParentCacheTableTuningColName   = "tuning"
	ItemParentCacheTableSportColName    = "sport"
	ItemParentCacheTableDesignColName   = "design"
)

var (
	ItemParentCacheTable            = goqu.T(ItemParentCacheTableName)
	ItemParentCacheTableItemIDCol   = ItemParentCacheTable.Col(ItemParentCacheTableItemIDColName)
	ItemParentCacheTableParentIDCol = ItemParentCacheTable.Col(ItemParentCacheTableParentIDColName)
	ItemParentCacheTableDiffCol     = ItemParentCacheTable.Col(ItemParentCacheTableDiffColName)
	ItemParentCacheTableDesignCol   = ItemParentCacheTable.Col(ItemParentCacheTableDesignColName)
	ItemParentCacheTableSportCol    = ItemParentCacheTable.Col(ItemParentCacheTableSportColName)
	ItemParentCacheTableTuningCol   = ItemParentCacheTable.Col(ItemParentCacheTableTuningColName)
)

type ItemParentCacheRow struct {
	ItemID   int64 `db:"item_id"`
	ParentID int64 `db:"parent_id"`
}
