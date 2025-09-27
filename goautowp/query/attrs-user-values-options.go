package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const AttrsUserValuesAlias = "auv"

func AppendAttrsUserValuesAlias(alias string) string {
	return alias + "_" + AttrsUserValuesAlias
}

type AttrsUserValueListOptions struct {
	ZoneID         int64
	AttributeID    int64
	ItemID         int64
	UserID         int64
	ExcludeUserID  int64
	WeightLtZero   bool
	ConflictLtZero bool
	ConflictGtZero bool
	UpdatedInDays  int
}

func (s *AttrsUserValueListOptions) Select(db *goqu.Database, alias string) *goqu.SelectDataset {
	return s.apply(
		alias,
		db.From(schema.AttrsUserValuesTable.As(alias)).
			Order(goqu.T(alias).Col(schema.AttrsUserValuesTableUpdateDateColName).Desc()),
	)
}

func (s *AttrsUserValueListOptions) JoinToItemIDAndApply(
	srcItemCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s == nil {
		return sqSelect
	}

	sqSelect = sqSelect.Join(schema.AttrsUserValuesTable.As(alias), goqu.On(
		srcItemCol.Eq(goqu.T(alias).Col(schema.AttrsUserValuesTableItemIDColName)),
	))

	return s.apply(alias, sqSelect)
}

func (s *AttrsUserValueListOptions) JoinToAttributeIDItemIDAndApply(
	srcAttributeCol exp.IdentifierExpression, srcItemCol exp.IdentifierExpression,
	alias string, sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s == nil {
		return sqSelect
	}

	sqSelect = sqSelect.Join(schema.AttrsUserValuesTable.As(alias), goqu.On(
		srcAttributeCol.Eq(goqu.T(alias).Col(schema.AttrsUserValuesTableAttributeIDColName)),
		srcItemCol.Eq(goqu.T(alias).Col(schema.AttrsUserValuesTableItemIDColName)),
	))

	return s.apply(alias, sqSelect)
}

func (s *AttrsUserValueListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	aliasTable := goqu.T(alias)

	if s.ItemID != 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsUserValuesTableItemIDColName).Eq(s.ItemID),
		)
	}

	if s.ZoneID != 0 {
		azaAlias := AppendAttrsZoneAttributesAlias(alias)

		sqSelect = sqSelect.Join(
			schema.AttrsZoneAttributesTable.As(azaAlias),
			goqu.On(aliasTable.Col(schema.AttrsUserValuesTableAttributeIDColName).Eq(
				goqu.T(azaAlias).Col(schema.AttrsZoneAttributesTableAttributeIDColName),
			)),
		).Where(goqu.T(azaAlias).Col(schema.AttrsZoneAttributesTableZoneIDColName).Eq(s.ZoneID))
	}

	if s.AttributeID != 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsUserValuesTableAttributeIDColName).Eq(s.AttributeID),
		)
	}

	if s.UserID != 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsUserValuesTableUserIDColName).Eq(s.UserID),
		)
	}

	if s.ExcludeUserID != 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsUserValuesTableUserIDColName).Neq(s.ExcludeUserID),
		)
	}

	if s.WeightLtZero {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsUserValuesTableWeightColName).Lt(0),
		)
	}

	if s.ConflictLtZero {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsUserValuesTableConflictColName).Lt(0),
		)
	}

	if s.ConflictGtZero {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsUserValuesTableConflictColName).Gt(0),
		)
	}

	if s.UpdatedInDays > 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.AttrsUserValuesTableUpdateDateColName).Gt(
				goqu.Func("DATE_SUB", goqu.Func("NOW"), goqu.L("INTERVAL ? DAY", s.UpdatedInDays)),
			),
		)
	}

	return sqSelect
}
