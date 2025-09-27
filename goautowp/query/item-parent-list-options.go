package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	ItemParentAlias = "ip"
)

func AppendItemParentAlias(alias string, suffix string) string {
	return alias + "_" + ItemParentAlias + suffix
}

type ItemParentListOptions struct {
	ItemID                             int64
	ParentID                           int64
	ParentIDs                          []int64
	Type                               schema.ItemParentType
	StrictType                         bool
	ParentIDExpr                       exp.Expression
	ItemIDExpr                         exp.Expression
	LinkedInDays                       int
	ParentItems                        *ItemListOptions
	ChildItems                         *ItemListOptions
	ItemParentParentByChildID          *ItemParentListOptions
	ItemParentCacheAncestorByParentID  *ItemParentCacheListOptions
	ItemParentCacheAncestorByChildID   *ItemParentCacheListOptions
	ItemParentCacheDescendantByChildID *ItemParentCacheListOptions
	Language                           string
	Limit                              uint32
	Page                               uint32
	Catname                            string
	NotManualCatname                   bool
}

func (s *ItemParentListOptions) Clone() *ItemParentListOptions {
	if s == nil {
		return nil
	}

	clone := *s

	clone.ParentItems = s.ParentItems.Clone()
	clone.ChildItems = s.ChildItems.Clone()
	clone.ItemParentParentByChildID = s.ItemParentParentByChildID.Clone()
	clone.ItemParentCacheAncestorByParentID = s.ItemParentCacheAncestorByParentID.Clone()
	clone.ItemParentCacheAncestorByChildID = s.ItemParentCacheAncestorByChildID.Clone()
	clone.ItemParentCacheDescendantByChildID = s.ItemParentCacheDescendantByChildID.Clone()

	return &clone
}

func (s *ItemParentListOptions) Select(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, bool, error) {
	return s.apply(
		alias,
		db.Select().From(schema.ItemParentTable.As(alias)),
	)
}

func (s *ItemParentListOptions) CountSelect(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	sqSelect, groupBy, err := s.Select(db, alias)
	if err != nil {
		return nil, err
	}

	if groupBy {
		sqSelect = sqSelect.Select(goqu.COUNT(goqu.DISTINCT(goqu.Star())))
	} else {
		sqSelect = sqSelect.Select(goqu.COUNT(goqu.Star()))
	}

	return sqSelect, nil
}

func (s *ItemParentListOptions) JoinToParentIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, bool, error) {
	if s == nil {
		return sqSelect, false, nil
	}

	return s.apply(
		alias,
		sqSelect.Join(
			schema.ItemParentTable.As(alias),
			goqu.On(srcCol.Eq(goqu.T(alias).Col(schema.ItemParentTableParentIDColName))),
		),
	)
}

func (s *ItemParentListOptions) JoinToItemIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, bool, error) {
	if s == nil {
		return sqSelect, false, nil
	}

	return s.apply(
		alias,
		sqSelect.Join(
			schema.ItemParentTable.As(alias),
			goqu.On(srcCol.Eq(goqu.T(alias).Col(schema.ItemParentTableItemIDColName))),
		),
	)
}

func (s *ItemParentListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, bool, error) {
	var (
		err         error
		groupBy     = false
		subGroupBy  bool
		aliasTable  = goqu.T(alias)
		itemIDCol   = aliasTable.Col(schema.ItemParentTableItemIDColName)
		parentIDCol = aliasTable.Col(schema.ItemParentTableParentIDColName)
	)

	if s.ItemID != 0 {
		sqSelect = sqSelect.Where(itemIDCol.Eq(s.ItemID))
	}

	if s.ParentID != 0 {
		sqSelect = sqSelect.Where(parentIDCol.Eq(s.ParentID))
	}

	if len(s.ParentIDs) > 0 {
		sqSelect = sqSelect.Where(parentIDCol.In(s.ParentIDs))
	}

	if s.ParentIDExpr != nil {
		sqSelect = sqSelect.Where(parentIDCol.Eq(s.ParentIDExpr))
	}

	if s.ItemIDExpr != nil {
		sqSelect = sqSelect.Where(itemIDCol.Eq(s.ItemIDExpr))
	}

	if s.Type != 0 || s.StrictType {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.ItemParentTableTypeColName).Eq(s.Type))
	}

	if s.LinkedInDays > 0 {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.ItemParentTableTimestampColName).Gt(
			goqu.Func("DATE_SUB", goqu.Func("NOW"), goqu.L("INTERVAL ? DAY", s.LinkedInDays)),
		))
	}

	if s.Catname != "" {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.ItemParentTableCatnameColName).Eq(s.Catname),
		)
	}

	if s.NotManualCatname {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.ItemParentTableManualCatnameColName).IsFalse(),
		)
	}

	sqSelect, subGroupBy, err = s.ParentItems.JoinToIDAndApply(
		parentIDCol,
		AppendItemAlias(alias, "p"),
		sqSelect,
	)
	if err != nil {
		return nil, false, err
	}

	if subGroupBy {
		groupBy = true
	}

	sqSelect, subGroupBy, err = s.ChildItems.JoinToIDAndApply(
		itemIDCol,
		AppendItemAlias(alias, "c"),
		sqSelect,
	)
	if err != nil {
		return nil, false, err
	}

	if subGroupBy {
		groupBy = true
	}

	if s.ItemParentCacheAncestorByParentID != nil {
		sqSelect, err = s.ItemParentCacheAncestorByParentID.JoinToItemIDAndApply(
			parentIDCol,
			AppendItemParentCacheAlias(alias, "ap"),
			sqSelect,
		)
		if err != nil {
			return nil, false, err
		}

		groupBy = true
	}

	if s.ItemParentCacheAncestorByChildID != nil {
		sqSelect, err = s.ItemParentCacheAncestorByChildID.JoinToItemIDAndApply(
			itemIDCol,
			AppendItemParentCacheAlias(alias, "ac"),
			sqSelect,
		)
		if err != nil {
			return nil, false, err
		}

		groupBy = true
	}

	if s.ItemParentCacheDescendantByChildID != nil {
		sqSelect, err = s.ItemParentCacheDescendantByChildID.JoinToParentIDAndApply(
			itemIDCol,
			AppendItemParentCacheAlias(alias, "dc"),
			sqSelect,
		)
		if err != nil {
			return nil, false, err
		}

		groupBy = true
	}

	if s.ItemParentParentByChildID != nil {
		sqSelect, _, err = s.ItemParentParentByChildID.JoinToItemIDAndApply(
			itemIDCol,
			AppendItemParentAlias(alias, "p"),
			sqSelect,
		)
		if err != nil {
			return nil, false, err
		}

		groupBy = true
	}

	return sqSelect, groupBy, nil
}
