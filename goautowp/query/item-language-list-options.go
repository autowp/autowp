package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const ItemLanguageAlias = "il"

func AppendItemLanguageAlias(alias string) string {
	return alias + "_" + ItemLanguageAlias
}

type ItemLanguageListOptions struct {
	ItemID          int64
	ExcludeLanguage string
}

func (s *ItemLanguageListOptions) Select(db *goqu.Database, alias string) *goqu.SelectDataset {
	return s.apply(alias, db.Select().From(schema.ItemLanguageTable.As(alias)))
}

func (s *ItemLanguageListOptions) CountSelect(db *goqu.Database, alias string) *goqu.SelectDataset {
	return s.Select(db, alias).Select(goqu.COUNT(goqu.Star()))
}

func (s *ItemLanguageListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	aliasTable := goqu.T(alias)

	if s.ItemID > 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.ItemLanguageTableItemIDColName).Eq(s.ItemID),
		)
	}

	if len(s.ExcludeLanguage) > 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.ItemLanguageTableLanguageColName).Neq(s.ExcludeLanguage),
		)
	}

	return sqSelect
}
