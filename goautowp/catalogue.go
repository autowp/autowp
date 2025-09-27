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
	db *goqu.Database
}

// NewCatalogue constructor.
func NewCatalogue(db *goqu.Database) (*Catalogue, error) {
	if db == nil {
		return nil, errDatabaseConnectionIsNil
	}

	return &Catalogue{
		db: db,
	}, nil
}

func (s *Catalogue) getVehicleTypesTree(
	ctx context.Context,
	parentID int64,
) ([]*VehicleType, error) {
	sqSelect := s.db.Select(schema.VehicleTypeTableIDCol, schema.VehicleTypeTableNameCol).
		From(schema.VehicleTypeTable).
		Order(schema.VehicleTypeTablePositionCol.Asc())

	if parentID != 0 {
		sqSelect = sqSelect.Where(schema.VehicleTypeTableParentIDCol.Eq(parentID))
	} else {
		sqSelect = sqSelect.Where(schema.VehicleTypeTableParentIDCol.IsNull())
	}

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	defer util.Close(rows)

	if err != nil {
		return nil, err
	}

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
	sqSelect := s.db.Select(schema.SpecTableIDCol, schema.SpecTableNameCol, schema.SpecTableShortNameCol).
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

	var specs []*Spec

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
	sqSelect := s.db.Select(schema.PerspectivesGroupsTableIDCol, schema.PerspectivesGroupsTableNameCol).
		From(schema.PerspectivesGroupsTable).
		Where(schema.PerspectivesGroupsTablePageIDCol.Eq(pageID)).
		Order(schema.PerspectivesGroupsTablePositionCol.Asc())

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	var wg sync.WaitGroup

	var perspectiveGroups []*PerspectiveGroup

	for rows.Next() {
		var group PerspectiveGroup

		err = rows.Scan(&group.Id, &group.Name)
		if err != nil {
			return nil, err
		}

		wg.Add(1)

		go func() {
			perspectives, err := s.getPerspectives(ctx, &group.Id)
			if err != nil {
				return
			}

			group.Perspectives = perspectives

			wg.Done()
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
	sqSelect := s.db.Select(schema.PerspectivesPagesTableIDCol, schema.PerspectivesPagesTableNameCol).
		From(schema.PerspectivesPagesTable).
		Order(schema.PerspectivesPagesTableIDCol.Asc())

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	var wg sync.WaitGroup

	var perspectivePages []*PerspectivePage

	for rows.Next() {
		var page PerspectivePage

		err = rows.Scan(&page.Id, &page.Name)
		if err != nil {
			return nil, err
		}

		wg.Add(1)

		go func() {
			groups, err := s.getPerspectiveGroups(ctx, page.GetId())
			if err != nil {
				return
			}

			page.Groups = groups

			wg.Done()
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
	sqSelect := s.db.Select(schema.PerspectivesTableIDCol, schema.PerspectivesTableNameCol).
		From(schema.PerspectivesTable)

	if groupID != nil {
		sqSelect = sqSelect.
			Join(
				schema.PerspectivesGroupsPerspectivesTable,
				goqu.On(
					schema.PerspectivesTableIDCol.Eq(
						schema.PerspectivesGroupsPerspectivesTablePerspectiveIDCol,
					),
				),
			).
			Where(schema.PerspectivesGroupsPerspectivesTableGroupIDCol.Eq(*groupID)).
			Order(schema.PerspectivesGroupsPerspectivesTablePositionCol.Asc())
	} else {
		sqSelect = sqSelect.Order(schema.PerspectivesTablePositionCol.Asc())
	}

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	var perspectives []*Perspective

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
			goqu.Or(schema.ItemTableBeginYearCol, schema.ItemTableBeginModelYearCol),
			schema.ItemTableIsGroupCol.IsFalse(),
		).
		GroupBy(schema.VehicleTypeTableIDCol).
		Order(schema.VehicleTypeTablePositionCol.Asc())

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	defer util.Close(rows)

	if err != nil {
		return nil, err
	}

	var result []*BrandVehicleType

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
