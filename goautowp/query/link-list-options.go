package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const (
	LinkAlias = "l"
)

type LinkListOptions struct {
	ID                        int64
	ItemID                    int64
	Type                      string
	ItemParentCacheDescendant *ItemParentCacheListOptions
}

func (s *LinkListOptions) IsIDUnique() bool {
	return s == nil || s.ItemParentCacheDescendant == nil
}

func (s *LinkListOptions) Select(db *goqu.Database, alias string) (*goqu.SelectDataset, error) {
	return s.apply(
		alias,
		db.Select().From(schema.ItemLinkTable.As(alias)),
	)
}

func (s *LinkListOptions) CountSelect(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	sqSelect, err := s.Select(db, alias)
	if err != nil {
		return nil, err
	}

	return sqSelect.Select(goqu.COUNT(goqu.Star())), nil
}

func (s *LinkListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s == nil {
		return sqSelect, nil
	}

	var (
		aliasTable = goqu.T(alias)
		err        error
		itemIDCol  = aliasTable.Col(schema.ItemLinkTableItemIDColName)
	)

	if s.ID != 0 {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.ItemLinkTableIDColName).Eq(s.ID))
	}

	if s.ItemID != 0 {
		sqSelect = sqSelect.Where(itemIDCol.Eq(s.ItemID))
	}

	if len(s.Type) > 0 {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.ItemLinkTableTypeColName).Eq(s.Type))
	}

	sqSelect, err = s.ItemParentCacheDescendant.JoinToParentIDAndApply(
		itemIDCol,
		AppendItemParentCacheAlias(alias, "d"),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	return sqSelect, nil
}
