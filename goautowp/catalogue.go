package goautowp

import (
	"context"
	"errors"
	"sync"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

var errDatabaseConnectionIsNil = errors.New("database connection is nil")

// Catalogue service.
type Catalogue struct {
	db   *goqu.Database
	pgDB *goqu.Database
}

// NewCatalogue constructor.
func NewCatalogue(db *goqu.Database, pgDB *goqu.Database) (*Catalogue, error) {
	if db == nil {
		return nil, errDatabaseConnectionIsNil
	}

	if pgDB == nil {
		return nil, errDatabaseConnectionIsNil
	}

	return &Catalogue{
		db:   db,
		pgDB: pgDB,
	}, nil
}

func (s *Catalogue) getVehicleTypesTree(
	ctx context.Context,
	parentID int64,
) ([]*VehicleType, error) {
	sqSelect := s.pgDB.Select(schema.VehicleTypeTableIDCol, schema.VehicleTypeTableNameCol).
		From(schema.VehicleTypeTable).
		Order(schema.VehicleTypeTablePositionCol.Asc())

	if parentID != 0 {
		sqSelect = sqSelect.Where(schema.VehicleTypeTableParentIDCol.Eq(parentID))
	} else {
		sqSelect = sqSelect.Where(schema.VehicleTypeTableParentIDCol.IsNull())
	}

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	result := make([]*VehicleType, 0)

	for rows.Next() {
		var vType VehicleType

		err = rows.Scan(&vType.Id, &vType.Name)
		if err != nil {
			return nil, err
		}

		vType.Childs, err = s.getVehicleTypesTree(ctx, vType.GetId())
		if err != nil {
			return nil, err
		}

		result = append(result, &vType)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return result, nil
}

func (s *Catalogue) getSpecs(ctx context.Context, parentID int32) ([]*Spec, error) {
	sqSelect := s.pgDB.Select(schema.SpecTableIDCol, schema.SpecTableNameCol, schema.SpecTableShortNameCol).
		From(schema.SpecTable).
		Order(schema.SpecTableNameCol.Asc())

	if parentID != 0 {
		sqSelect = sqSelect.Where(schema.SpecTableParentIDCol.Eq(parentID))
	} else {
		sqSelect = sqSelect.Where(schema.SpecTableParentIDCol.IsNull())
	}

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	specs := make([]*Spec, 0)

	for rows.Next() {
		var spec Spec

		err = rows.Scan(&spec.Id, &spec.Name, &spec.ShortName)
		if err != nil {
			return nil, err
		}

		childs, err := s.getSpecs(ctx, spec.GetId())
		if err != nil {
			return nil, err
		}

		spec.Childs = childs
		specs = append(specs, &spec)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return specs, nil
}

func (s *Catalogue) getPerspectiveGroups(
	ctx context.Context,
	pageID int32,
) ([]*PerspectiveGroup, error) {
	sqSelect := s.pgDB.Select(schema.PerspectiveGroupTableIDCol, schema.PerspectiveGroupTableNameCol).
		From(schema.PerspectiveGroupTable).
		Where(schema.PerspectiveGroupTablePageIDCol.Eq(pageID)).
		Order(schema.PerspectiveGroupTablePositionCol.Asc())

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	var wg sync.WaitGroup

	perspectiveGroups := make([]*PerspectiveGroup, 0)

	for rows.Next() {
		var group PerspectiveGroup

		err = rows.Scan(&group.Id, &group.Name)
		if err != nil {
			return nil, err
		}

		wg.Add(1)

		go func() {
			defer wg.Done()

			perspectives, err := s.getPerspectives(ctx, &group.Id)
			if err != nil {
				return
			}

			group.Perspectives = perspectives
		}()

		perspectiveGroups = append(perspectiveGroups, &group)
	}

	wg.Wait()

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return perspectiveGroups, nil
}

func (s *Catalogue) getPerspectivePages(ctx context.Context) ([]*PerspectivePage, error) {
	sqSelect := s.pgDB.Select(schema.PerspectivePageTableIDCol, schema.PerspectivePageTableNameCol).
		From(schema.PerspectivePageTable).
		Order(schema.PerspectivePageTableIDCol.Asc())

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	var wg sync.WaitGroup

	perspectivePages := make([]*PerspectivePage, 0)

	for rows.Next() {
		var page PerspectivePage

		err = rows.Scan(&page.Id, &page.Name)
		if err != nil {
			return nil, err
		}

		wg.Add(1)

		go func() {
			defer wg.Done()

			groups, err := s.getPerspectiveGroups(ctx, page.GetId())
			if err != nil {
				return
			}

			page.Groups = groups
		}()

		perspectivePages = append(perspectivePages, &page)
	}

	wg.Wait()

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return perspectivePages, nil
}

func (s *Catalogue) getPerspectives(ctx context.Context, groupID *int32) ([]*Perspective, error) {
	sqSelect := s.pgDB.Select(schema.PerspectiveTableIDCol, schema.PerspectiveTableNameCol).
		From(schema.PerspectiveTable)

	if groupID != nil {
		sqSelect = sqSelect.
			Join(
				schema.PerspectiveGroupPerspectiveTable,
				goqu.On(
					schema.PerspectiveTableIDCol.Eq(
						schema.PerspectiveGroupPerspectiveTablePerspectiveIDCol,
					),
				),
			).
			Where(schema.PerspectiveGroupPerspectiveTableGroupIDCol.Eq(*groupID)).
			Order(schema.PerspectiveGroupPerspectiveTablePositionCol.Asc())
	} else {
		sqSelect = sqSelect.Order(schema.PerspectiveTablePositionCol.Asc())
	}

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	perspectives := make([]*Perspective, 0)

	for rows.Next() {
		var perspective Perspective

		err = rows.Scan(&perspective.Id, &perspective.Name)
		if err != nil {
			return nil, err
		}

		perspectives = append(perspectives, &perspective)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return perspectives, nil
}

func (s *Catalogue) getBrandVehicleTypes(
	ctx context.Context,
	brandID int32,
) ([]*BrandVehicleType, error) {
	sqSelect := s.db.
		Select(schema.VehicleTypeTableIDCol, schema.VehicleTypeTableNameCol, schema.VehicleTypeTableCatnameCol,
			goqu.COUNT(goqu.DISTINCT(schema.ItemTableIDCol))).
		From(schema.VehicleTypeTable).
		Join(
			schema.ItemVehicleTypeTable,
			goqu.On(schema.VehicleTypeTableIDCol.Eq(schema.ItemVehicleTypeTableVehicleTypeIDCol)),
		).
		Join(schema.ItemTable, goqu.On(schema.ItemVehicleTypeTableItemIDCol.Eq(schema.ItemTableIDCol))).
		Join(schema.ItemParentCacheTable, goqu.On(schema.ItemTableIDCol.Eq(schema.ItemParentCacheTableItemIDCol))).
		Where(
			schema.ItemParentCacheTableParentIDCol.Eq(brandID),
			goqu.Or(schema.ItemTableBeginYearCol.Gt(0), schema.ItemTableBeginModelYearCol.Gt(0)),
			schema.ItemTableIsGroupCol.IsFalse(),
		).
		GroupBy(schema.VehicleTypeTableIDCol).
		Order(schema.VehicleTypeTablePositionCol.Asc())

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}

	defer util.Close(rows)

	result := make([]*BrandVehicleType, 0)

	for rows.Next() {
		var bvType BrandVehicleType

		err = rows.Scan(&bvType.Id, &bvType.Name, &bvType.Catname, &bvType.ItemsCount)
		if err != nil {
			return nil, err
		}

		result = append(result, &bvType)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return result, nil
}
