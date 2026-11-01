package schema

import "github.com/doug-martin/goqu/v9"

const (
	BrandAliasTableName          = "brand_alias"
	BrandAliasTableItemIDColName = "item_id"
	BrandAliasTableNameColName   = "name"
)

var (
	BrandAliasTable          = goqu.T(BrandAliasTableName)
	BrandAliasTableItemIDCol = BrandAliasTable.Col(BrandAliasTableItemIDColName)
	BrandAliasTableNameCol   = BrandAliasTable.Col(BrandAliasTableNameColName)
)
