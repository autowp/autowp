package items

import (
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

type AliaseableExpression interface {
	exp.Expression
	exp.Aliaseable
	exp.Orderable
}

type Column interface {
	SelectExpr(alias string, lang string) (AliaseableExpression, error)
}

type DescendantsCountColumn struct {
	db *goqu.Database
}

func (s DescendantsCountColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	options := query.ItemParentCacheListOptions{
		ParentIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
		ExcludeSelf:  true,
	}

	sqSelect, err := options.CountSelect(s.db, query.ItemParentCacheAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", sqSelect), nil
}

type NewDescendantsCountColumn struct {
	db *goqu.Database
}

func (s NewDescendantsCountColumn) SelectExpr(
	alias string,
	_ string,
) (AliaseableExpression, error) {
	options := query.ItemListOptions{
		Alias: alias + "product2",
		ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
			ParentIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
			ExcludeSelf:  true,
		},
		CreatedInDays: NewDays,
	}

	sqSelect, err := options.CountDistinctSelect(s.db, query.ItemAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", sqSelect), nil
}

type DescendantTwinsGroupsCountColumn struct {
	db *goqu.Database
}

func (s DescendantTwinsGroupsCountColumn) SelectExpr(
	alias string,
	_ string,
) (AliaseableExpression, error) {
	options := query.ItemListOptions{
		Alias:  alias + "dtgc",
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDTwins},
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			ItemParentCacheAncestorByItemID: &query.ItemParentCacheListOptions{
				ItemsByParentID: &query.ItemListOptions{
					ItemIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
				},
			},
		},
	}

	sqSelect, err := options.CountSelect(s.db, query.ItemAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", sqSelect), nil
}

type DescendantPicturesCountColumn struct{}

func (s DescendantPicturesCountColumn) SelectExpr(
	alias string,
	_ string,
) (AliaseableExpression, error) {
	piTableAlias := query.AppendPictureItemAlias(
		query.AppendItemParentCacheAlias(alias, "d"), "i",
	)

	return goqu.COUNT(
		goqu.DISTINCT(goqu.T(piTableAlias).Col(schema.PictureItemTablePictureIDColName)),
	), nil
}

type ChildsCountColumn struct {
	db *goqu.Database
}

func (s ChildsCountColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	options := query.ItemParentListOptions{
		ParentIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
	}

	sqSelect, err := options.CountSelect(s.db, query.ItemParentAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", sqSelect), nil
}

type ParentsCountColumn struct {
	db *goqu.Database
}

func (s ParentsCountColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	options := query.ItemParentListOptions{
		ItemIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
	}

	sqSelect, err := options.CountSelect(s.db, query.ItemParentAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", sqSelect), nil
}

type TextstorageRefColumn struct {
	db  *goqu.Database
	col string
}

func (s TextstorageRefColumn) SelectExpr(alias string, lang string) (AliaseableExpression, error) {
	ilAlias := alias + "_" + s.col

	orderExpr, err := langPriorityOrderExpr(
		goqu.T(ilAlias).Col(schema.ItemLanguageTableLanguageColName),
		lang,
	)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", s.db.Select(schema.TextstorageTextTableTextCol).
			From(schema.ItemLanguageTable.As(ilAlias)).
			Join(
				schema.TextstorageTextTable,
				goqu.On(goqu.T(ilAlias).Col(s.col).Eq(schema.TextstorageTextTableIDCol)),
			).
			Where(
				goqu.T(ilAlias).
					Col(schema.ItemLanguageTableItemIDColName).
					Eq(goqu.T(alias).Col(schema.ItemTableIDColName)),
				goqu.Func("length", schema.TextstorageTextTableTextCol).Gt(0),
			).
			Order(orderExpr).
			Limit(1)),
		nil
}

type NameDefaultColumn struct {
	db *goqu.Database
}

func (s NameDefaultColumn) SelectExpr(alias string, lang string) (AliaseableExpression, error) {
	il1Alias := alias + "il1"
	il1AliasTable := goqu.T(il1Alias)
	il2Alias := alias + "il2"
	il2AliasTable := goqu.T(il2Alias)

	orderExpr, err := langPriorityOrderExpr(
		il2AliasTable.Col(schema.ItemLanguageTableLanguageColName),
		lang,
	)
	if err != nil {
		return nil, err
	}

	subQuery := s.db.Select(il2AliasTable.Col(schema.ItemLanguageTableNameColName)).
		From(schema.ItemLanguageTable.As(il2Alias)).
		Where(
			il2AliasTable.Col(schema.ItemLanguageTableItemIDColName).
				Eq(goqu.T(alias).Col(schema.ItemTableIDColName)),
			goqu.Func("LENGTH", il2AliasTable.Col(schema.ItemLanguageTableNameColName)).Gt(0),
		).
		Order(orderExpr).
		Limit(1)
	subQueryAlias := alias + "subquery"

	return goqu.Func(
			"IFNULL",
			s.db.Select(il1AliasTable.Col(schema.ItemLanguageTableNameColName)).
				From(schema.ItemLanguageTable.As(il1Alias)).
				Join(subQuery.As(subQueryAlias), goqu.On(
					il1AliasTable.Col(schema.ItemLanguageTableNameColName).Neq(
						goqu.T(subQueryAlias).Col(schema.ItemLanguageTableNameColName),
					),
				)).
				Where(
					il1AliasTable.Col(schema.ItemLanguageTableItemIDColName).
						Eq(goqu.T(alias).Col(schema.ItemTableIDColName)),
					il1AliasTable.Col(schema.ItemLanguageTableLanguageColName).
						Eq(DefaultLanguageCode),
				).
				Limit(1),
			goqu.V(""),
		),
		nil
}

type NameOnlyColumn struct {
	DB *goqu.Database
}

func (s NameOnlyColumn) SelectExpr(alias string, lang string) (AliaseableExpression, error) {
	orderExpr, err := langPriorityOrderExpr(schema.ItemLanguageTableLanguageCol, lang)
	if err != nil {
		return nil, err
	}

	return goqu.Func(
			"IFNULL",
			s.DB.Select(schema.ItemLanguageTableNameCol).
				From(schema.ItemLanguageTable).
				Where(
					schema.ItemLanguageTableItemIDCol.Eq(goqu.T(alias).Col(schema.ItemTableIDColName)),
					goqu.Func("LENGTH", schema.ItemLanguageTableNameCol).Gt(0),
				).
				Order(orderExpr).
				Limit(1),
			goqu.T(alias).Col(schema.ItemTableNameColName),
		),
		nil
}

type CommentsAttentionsCountColumn struct {
	db *goqu.Database
}

func (s CommentsAttentionsCountColumn) SelectExpr(
	alias string,
	_ string,
) (AliaseableExpression, error) {
	opts := query.CommentMessageListOptions{
		Attention:   schema.CommentMessageModeratorAttentionRequired,
		CommentType: schema.CommentMessageTypeIDPictures,
		PictureItems: &query.PictureItemListOptions{
			ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
				ParentIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
			},
		},
	}

	sqSelect, err := opts.CountSelect(s.db, query.CommentMessageAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", sqSelect), nil
}

type StatusPicturesCountColumn struct {
	status schema.PictureStatus
	db     *goqu.Database
}

func (s StatusPicturesCountColumn) SelectExpr(
	alias string,
	_ string,
) (AliaseableExpression, error) {
	opts := query.PictureListOptions{
		Status: s.status,
		PictureItem: &query.PictureItemListOptions{
			ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
				ParentIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
			},
		},
	}

	sqSelect, err := opts.CountSelect(s.db, query.PictureAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", sqSelect), nil
}

type ExactPicturesCountColumn struct {
	db *goqu.Database
}

func (s ExactPicturesCountColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	opts := query.PictureListOptions{
		PictureItem: &query.PictureItemListOptions{
			ItemIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
		},
	}

	sqSelect, err := opts.CountSelect(s.db, query.PictureAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("?", sqSelect), nil
}

type MostsActiveColumn struct {
	mostsMinCarsCount int
	db                *goqu.Database
}

func (s MostsActiveColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	opts := query.ItemParentCacheListOptions{
		ItemsByParentID: &query.ItemListOptions{
			ItemIDExpr: goqu.T(alias).Col(schema.ItemTableIDColName),
		},
	}

	sqSelect, err := opts.CountSelect(s.db, query.ItemParentCacheAlias)
	if err != nil {
		return nil, err
	}

	return goqu.L("? >= ?", sqSelect, s.mostsMinCarsCount), nil
}

type DescendantsParentsCountColumn struct{}

func (s DescendantsParentsCountColumn) SelectExpr(
	alias string,
	_ string,
) (AliaseableExpression, error) {
	cAlias := query.AppendItemParentAlias(
		query.AppendItemParentCacheAlias(alias, "d"), "p",
	)

	return goqu.COUNT(goqu.DISTINCT(goqu.T(cAlias).Col(schema.ItemParentTableParentIDColName))), nil
}

type NewDescendantsParentsCountColumn struct{}

func (s NewDescendantsParentsCountColumn) SelectExpr(
	alias string,
	_ string,
) (AliaseableExpression, error) {
	cAlias := query.AppendItemAlias(
		query.AppendItemParentAlias(
			query.AppendItemParentCacheAlias(alias, "d"), "p",
		),
		"p",
	)
	cAliasTable := goqu.T(cAlias)

	return goqu.COUNT(goqu.DISTINCT(goqu.Func("IF",
		cAliasTable.Col(schema.ItemTableAddDatetimeColName).Gt(
			goqu.Func("DATE_SUB", goqu.Func("NOW"), goqu.L("INTERVAL ? DAY", NewDays)),
		),
		cAliasTable.Col(schema.ItemTableIDColName),
		nil,
	))), nil
}

type ChildItemsCountColumn struct{}

func (s ChildItemsCountColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	ipcAlias := query.AppendItemParentAlias(alias, "c")
	ipcAliasTable := goqu.T(ipcAlias)

	return goqu.COUNT(goqu.DISTINCT(ipcAliasTable.Col(schema.ItemParentTableItemIDColName))), nil
}

type NewChildItemsCountColumn struct{}

func (s NewChildItemsCountColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	ipcAlias := query.AppendItemParentAlias(alias, "c")
	ipcAliasTable := goqu.T(ipcAlias)

	return goqu.COUNT(goqu.DISTINCT(
		goqu.Func("IF",
			ipcAliasTable.Col(schema.ItemParentTableTimestampColName).Gt(
				goqu.Func("DATE_SUB", goqu.Func("NOW"), goqu.L("INTERVAL ? DAY", NewDays)),
			),
			ipcAliasTable.Col(schema.ItemParentTableItemIDColName),
			nil,
		),
	)), nil
}

type SimpleColumn struct {
	col string
}

func (s SimpleColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	return goqu.T(alias).Col(s.col), nil
}

type SpecNameColumn struct{}

func (s SpecNameColumn) SelectExpr(_ string, _ string) (AliaseableExpression, error) {
	return schema.SpecTableNameCol, nil
}

type SpecShortNameColumn struct{}

func (s SpecShortNameColumn) SelectExpr(_ string, _ string) (AliaseableExpression, error) {
	return schema.SpecTableShortNameCol, nil
}

type StarCountColumn struct{}

func (s StarCountColumn) SelectExpr(_ string, _ string) (AliaseableExpression, error) {
	return goqu.COUNT(goqu.Star()), nil
}

type ItemParentParentTimestampColumn struct{}

func (s ItemParentParentTimestampColumn) SelectExpr(
	_ string,
	_ string,
) (AliaseableExpression, error) {
	return goqu.MAX(
			goqu.T(query.AppendItemParentAlias(query.ItemAlias, "p")).
				Col(schema.ItemParentTableTimestampColName),
		),
		nil
}

type AttrsUserValuesUpdateDateColumn struct{}

func (s AttrsUserValuesUpdateDateColumn) SelectExpr(
	_ string,
	_ string,
) (AliaseableExpression, error) {
	return goqu.MAX(
			goqu.T(query.AppendAttrsUserValuesAlias(query.ItemAlias)).
				Col(schema.AttrsUserValuesTableUpdateDateColName),
		),
		nil
}

type HasChildSpecsColumn struct {
	db *goqu.Database
}

func (s HasChildSpecsColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	return goqu.Func("EXISTS",
		s.db.Select(goqu.V(true)).
			From(schema.ItemParentTable).
			Join(schema.AttrsValuesTable, goqu.On(
				schema.ItemParentTableItemIDCol.Eq(schema.AttrsValuesTableItemIDCol),
			)).
			Where(schema.ItemParentTableParentIDCol.Eq(goqu.T(alias).Col(schema.ItemTableIDColName))).
			Limit(1),
	), nil
}

type HasSpecsColumn struct {
	db *goqu.Database
}

func (s HasSpecsColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	return goqu.Func("EXISTS",
		s.db.Select(goqu.V(true)).
			From(schema.AttrsValuesTable).
			Where(schema.AttrsValuesTableItemIDCol.Eq(goqu.T(alias).Col(schema.ItemTableIDColName))).
			Limit(1),
	), nil
}
