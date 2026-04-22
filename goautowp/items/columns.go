package items

import (
	"fmt"

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
	GroupByExpr() interface{}
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

func (s DescendantsCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s NewDescendantsCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s DescendantTwinsGroupsCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s DescendantPicturesCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s ChildsCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s ParentsCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s TextstorageRefColumn) GroupByExpr() interface{} {
	return nil
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
			"COALESCE",
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
						Eq(schema.DefaultLanguageCode),
				).
				Limit(1),
			goqu.V(""),
		),
		nil
}

func (s NameDefaultColumn) GroupByExpr() interface{} {
	return nil
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
			"COALESCE",
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

func (s NameOnlyColumn) GroupByExpr() interface{} {
	return nil
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

func (s CommentsAttentionsCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s StatusPicturesCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s ExactPicturesCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s MostsActiveColumn) GroupByExpr() interface{} {
	return nil
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

func (s DescendantsParentsCountColumn) GroupByExpr() interface{} {
	return nil
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

	return goqu.L(
		"COUNT(DISTINCT ?) FILTER (WHERE ?)",
		cAliasTable.Col(schema.ItemTableIDColName),
		cAliasTable.Col(schema.ItemTableAddDatetimeColName).Gt(
			goqu.L("NOW() - INTERVAL ?", fmt.Sprintf("%d DAY", NewDays)),
		),
	), nil
}

func (s NewDescendantsParentsCountColumn) GroupByExpr() interface{} {
	return nil
}

type ChildItemsCountColumn struct{}

func (s ChildItemsCountColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	ipcAlias := query.AppendItemParentAlias(alias, "c")
	ipcAliasTable := goqu.T(ipcAlias)

	return goqu.COUNT(goqu.DISTINCT(ipcAliasTable.Col(schema.ItemParentTableItemIDColName))), nil
}

func (s ChildItemsCountColumn) GroupByExpr() interface{} {
	return nil
}

type NewChildItemsCountColumn struct{}

func (s NewChildItemsCountColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	ipcAlias := query.AppendItemParentAlias(alias, "c")
	ipcAliasTable := goqu.T(ipcAlias)

	return goqu.L(
		"COUNT(DISTINCT ?) FILTER (WHERE ?)",
		ipcAliasTable.Col(schema.ItemParentTableItemIDColName),
		ipcAliasTable.Col(schema.ItemParentTableTimestampColName).Gt(
			goqu.L("NOW() - INTERVAL ?", fmt.Sprintf("%d DAY", NewDays)),
		),
	), nil
}

func (s NewChildItemsCountColumn) GroupByExpr() interface{} {
	return nil
}

type SimpleColumn struct {
	col string
}

func (s SimpleColumn) SelectExpr(alias string, _ string) (AliaseableExpression, error) {
	return goqu.T(alias).Col(s.col), nil
}

func (s SimpleColumn) GroupByExpr() interface{} {
	return nil
}

type SpecNameColumn struct{}

func (s SpecNameColumn) SelectExpr(_ string, _ string) (AliaseableExpression, error) {
	return schema.SpecTableNameCol, nil
}

func (s SpecNameColumn) GroupByExpr() interface{} {
	return schema.SpecTableNameCol
}

type SpecShortNameColumn struct{}

func (s SpecShortNameColumn) SelectExpr(_ string, _ string) (AliaseableExpression, error) {
	return schema.SpecTableShortNameCol, nil
}

func (s SpecShortNameColumn) GroupByExpr() interface{} {
	return schema.SpecTableShortNameCol
}

type StarCountColumn struct{}

func (s StarCountColumn) SelectExpr(_ string, _ string) (AliaseableExpression, error) {
	return goqu.COUNT(goqu.Star()), nil
}

func (s StarCountColumn) GroupByExpr() interface{} {
	return nil
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

func (s ItemParentParentTimestampColumn) GroupByExpr() interface{} {
	return nil
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

func (s AttrsUserValuesUpdateDateColumn) GroupByExpr() interface{} {
	return nil
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

func (s HasChildSpecsColumn) GroupByExpr() interface{} {
	return nil
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

func (s HasSpecsColumn) GroupByExpr() interface{} {
	return nil
}
