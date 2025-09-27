package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const PerspectiveGroupPerspectiveAlias = "pgp"

func AppendPerspectiveGroupPerspectiveAlias(alias string) string {
	return alias + "_" + PerspectiveGroupPerspectiveAlias
}

type PerspectiveGroupPerspectiveListOptions struct {
	GroupID int32
}

func (s *PerspectiveGroupPerspectiveListOptions) Clone() *PerspectiveGroupPerspectiveListOptions {
	if s == nil {
		return nil
	}

	clone := *s

	return &clone
}

func (s *PerspectiveGroupPerspectiveListOptions) JoinToPerspectiveIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s == nil {
		return sqSelect
	}

	return s.apply(
		alias,
		sqSelect.Join(
			schema.PerspectivesGroupsPerspectivesTable.As(alias),
			goqu.On(
				srcCol.Eq(
					goqu.T(alias).
						Col(schema.PerspectivesGroupsPerspectivesTablePerspectiveIDColName),
				),
			),
		),
	)
}

func (s *PerspectiveGroupPerspectiveListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s.GroupID != 0 {
		sqSelect = sqSelect.Where(
			goqu.T(alias).
				Col(schema.PerspectivesGroupsPerspectivesTableGroupIDColName).
				Eq(s.GroupID),
		)
	}

	return sqSelect
}
