package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	PictureModerVoteAlias = "pmv"
)

func AppendPictureModerVoteAlias(alias string) string {
	return alias + "_" + PictureModerVoteAlias
}

type PictureModerVoteListOptions struct {
	PictureID   int64
	VoteGtZero  bool
	VoteLteZero bool
}

func (s *PictureModerVoteListOptions) Clone() *PictureModerVoteListOptions {
	if s == nil {
		return nil
	}

	clone := *s

	return &clone
}

func (s *PictureModerVoteListOptions) Select(db *goqu.Database, alias string) *goqu.SelectDataset {
	return s.apply(
		alias,
		db.Select().From(schema.PictureModerVoteTable.As(alias)),
	)
}

func (s *PictureModerVoteListOptions) JoinToPictureIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s == nil {
		return sqSelect
	}

	return s.apply(
		alias,
		sqSelect.Join(
			schema.PictureModerVoteTable.As(alias),
			goqu.On(
				srcCol.Eq(goqu.T(alias).Col(schema.PictureModerVoteTablePictureIDColName)),
			),
		),
	)
}

func (s *PictureModerVoteListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) *goqu.SelectDataset {
	if s.PictureID != 0 {
		sqSelect = sqSelect.Where(
			goqu.T(alias).Col(schema.PictureModerVoteTablePictureIDColName).Eq(s.PictureID),
		)
	}

	if s.VoteGtZero {
		sqSelect = sqSelect.Where(goqu.T(alias).Col(schema.PictureModerVoteTableVoteColName).Gt(0))
	}

	if s.VoteLteZero {
		sqSelect = sqSelect.Where(goqu.T(alias).Col(schema.PictureModerVoteTableVoteColName).Lte(0))
	}

	return sqSelect
}
