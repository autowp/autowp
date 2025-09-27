package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

const VehicleTypeParentTableAlias = "vtp"

type VehicleTypeParentsListOptions struct {
	ItemVehicleTypeByID *ItemVehicleTypeListOptions
}

func (s *VehicleTypeParentsListOptions) Select(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	return s.apply(alias, db.From(schema.VehicleTypeParentTable.As(alias)))
}

func (s *VehicleTypeParentsListOptions) JoinToParentIDAndApply(
	srcCol exp.IdentifierExpression, alias string, sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s == nil {
		return sqSelect, nil
	}

	sqSelect = sqSelect.Join(
		schema.VehicleTypeParentTable.As(alias),
		goqu.On(srcCol.Eq(goqu.T(alias).Col(schema.VehicleTypeParentTableParentIDColName))),
	)

	return s.apply(alias, sqSelect)
}

func (s *VehicleTypeParentsListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	if s.ItemVehicleTypeByID != nil {
		var err error

		sqSelect, err = s.ItemVehicleTypeByID.JoinToVehicleTypeIDAndApply(
			goqu.T(alias).
				Col(schema.VehicleTypeParentTableIDColName),
			AppendItemVehicleTypeAlias(alias),
			sqSelect,
		)
		if err != nil {
			return nil, err
		}
	}

	return sqSelect, nil
}
