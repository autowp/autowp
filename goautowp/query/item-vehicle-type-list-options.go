package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const (
	ItemVehicleTypeAlias = "ivt"
)

func AppendItemVehicleTypeAlias(alias string) string {
	return alias + "_" + ItemVehicleTypeAlias
}

type ItemVehicleTypeListOptions struct {
	VehicleTypeID           int64
	ItemParentCacheAncestor *ItemParentCacheListOptions
}

func (s *ItemVehicleTypeListOptions) Clone() *ItemVehicleTypeListOptions {
	if s == nil {
		return nil
	}

	clone := *s

	return &clone
}

func (s *ItemVehicleTypeListOptions) JoinToVehicleIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s == nil {
		return sqSelect, nil
	}

	sqSelect = sqSelect.Join(
		schema.ItemVehicleTypeTable.As(alias),
		goqu.On(
			srcCol.Eq(goqu.T(alias).Col(schema.ItemVehicleTypeTableItemIDColName)),
		),
	)

	return s.apply(alias, sqSelect)
}

func (s *ItemVehicleTypeListOptions) JoinToVehicleTypeIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s == nil {
		return sqSelect, nil
	}

	sqSelect = sqSelect.Join(
		schema.ItemVehicleTypeTable.As(alias),
		goqu.On(
			srcCol.Eq(goqu.T(alias).Col(schema.ItemVehicleTypeTableVehicleTypeIDColName)),
		),
	)

	return s.apply(alias, sqSelect)
}

func (s *ItemVehicleTypeListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	aliasTable := goqu.T(alias)

	var err error

	if s.VehicleTypeID != 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.ItemVehicleTypeTableVehicleTypeIDColName).Eq(s.VehicleTypeID),
		)
	}

	if s.ItemParentCacheAncestor != nil {
		sqSelect, err = s.ItemParentCacheAncestor.JoinToItemIDAndApply(
			aliasTable.Col(schema.ItemVehicleTypeTableItemIDColName),
			AppendItemParentCacheAlias(alias, "a"),
			sqSelect,
		)
		if err != nil {
			return nil, err
		}
	}

	return sqSelect, nil
}
