package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	userItemSubscribeAlias = "uis"
)

type UserItemSubscribeListOptions struct {
	ItemIDs []int64
}

func (s *UserItemSubscribeListOptions) JoinToItemIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s == nil {
		return sqSelect
	}

	sqSelect = sqSelect.Join(
		schema.UserItemSubscribeTable.As(alias),
		goqu.On(srcCol.Eq(goqu.T(alias).Col(schema.UserItemSubscribeTableUserIDColName))),
	)

	return s.apply(alias, sqSelect)
}

func (s *UserItemSubscribeListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if len(s.ItemIDs) > 0 {
		sqSelect = sqSelect.Where(
			goqu.T(alias).Col(schema.UserItemSubscribeTableItemIDColName).In(s.ItemIDs),
		)
	}

	return sqSelect
}
