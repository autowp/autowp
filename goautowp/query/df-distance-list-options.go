package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	DfDistanceAlias = "dd"
)

func AppendDfDistanceAlias(alias string) string {
	return alias + "_" + DfDistanceAlias
}

type DfDistanceListOptions struct {
	SrcPictureID int64
	DstPicture   *PictureListOptions
}

func (s *DfDistanceListOptions) Clone() *DfDistanceListOptions {
	if s == nil {
		return nil
	}

	clone := *s

	clone.DstPicture = s.DstPicture.Clone()

	return &clone
}

func (s *DfDistanceListOptions) Select(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	return s.apply(
		alias,
		db.Select().From(schema.DfDistanceTable.As(alias)),
	)
}

func (s *DfDistanceListOptions) JoinToSrcPictureIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s == nil {
		return sqSelect, nil
	}

	return s.apply(
		alias,
		sqSelect.Join(
			schema.DfDistanceTable.As(alias),
			goqu.On(srcCol.Eq(goqu.T(alias).Col(schema.DfDistanceTableSrcPictureIDColName))),
		),
	)
}

func (s *DfDistanceListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	var err error

	aliasTable := goqu.T(alias)

	sqSelect = sqSelect.Where(goqu.T(alias).Col(schema.DfDistanceTableHideColName).IsFalse())

	if s.SrcPictureID != 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.DfDistanceTableSrcPictureIDColName).Eq(s.SrcPictureID),
		)
	}

	sqSelect, err = s.DstPicture.JoinToIDAndApply(
		aliasTable.Col(schema.DfDistanceTableDstPictureIDColName),
		AppendPictureAlias(alias),
		sqSelect,
	)
	if err != nil {
		return nil, err
	}

	return sqSelect, nil
}
