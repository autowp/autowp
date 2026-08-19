package query

import (
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const VehicleTypeTableAlias = "vt"

type VehicleTypeListOptions struct {
	Catname  string
	NoParent bool
	ParentID int64
	Childs   *VehicleTypeParentsListOptions
}

func (s *VehicleTypeListOptions) Select(
	db *goqu.Database,
	alias string,
) (*goqu.SelectDataset, error) {
	return s.apply(alias, db.From(schema.VehicleTypeTable.As(alias)))
}

func (s *VehicleTypeListOptions) apply(
	alias string,
	sqSelect *goqu.SelectDataset,
) (*goqu.SelectDataset, error) {
	aliasTable := goqu.T(alias)

	var err error

	if len(s.Catname) > 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.VehicleTypeTableCatnameColName).Eq(s.Catname),
		)
	}

	if s.NoParent {
		sqSelect = sqSelect.Where(aliasTable.Col(schema.VehicleTypeTableParentIDColName).IsNull())
	}

	if s.ParentID > 0 {
		sqSelect = sqSelect.Where(
			aliasTable.Col(schema.VehicleTypeTableParentIDColName).Eq(s.ParentID),
		)
	}

	if s.Childs != nil {
		// EXISTS, not a join: the outer select reads no column from these tables, so joining them
		// only ever filtered - but a vehicle type has one row per matching vehicle and per row of
		// that vehicle's item_parent_cache closure, so the join returned the same handful of
		// vehicle types thousands of times over for a large brand (duplicated in the menu the
		// caller builds from them, and taking minutes to compute). A semi-join stops at the first
		// match per vehicle type instead.
		childAlias := alias + "_" + VehicleTypeParentTableAlias

		subSelect := sqSelect.
			ClearSelect().
			ClearLimit().
			ClearOffset().
			ClearOrder().
			ClearWhere().
			GroupBy().
			FromSelf().
			From(schema.VehicleTypeParentTable.As(childAlias)).
			Select(goqu.V(true)).
			Where(
				aliasTable.Col(schema.VehicleTypeTableIDColName).Eq(
					goqu.T(childAlias).Col(schema.VehicleTypeParentTableParentIDColName),
				),
			)

		subSelect, err = s.Childs.apply(childAlias, subSelect)
		if err != nil {
			return nil, err
		}

		sqSelect = sqSelect.Where(goqu.L("EXISTS ?", subSelect))
	}

	return sqSelect, nil
}
