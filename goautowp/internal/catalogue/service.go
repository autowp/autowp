package catalogue

import (
	"context"
	"database/sql"
	"errors"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/util"
	"github.com/doug-martin/goqu/v9"
	"golang.org/x/sync/errgroup"
)

const catalogueLookupParallelism = 4

var errDatabaseConnectionIsNil = errors.New("database connection is nil")

type Service struct {
	db   *goqu.Database
	pgDB *goqu.Database
}

func New(db *goqu.Database, pgDB *goqu.Database) (*Service, error) {
	if db == nil {
		return nil, errDatabaseConnectionIsNil
	}

	if pgDB == nil {
		return nil, errDatabaseConnectionIsNil
	}

	return &Service{
		db:   db,
		pgDB: pgDB,
	}, nil
}

func (s *Service) VehicleTypesTree(
	ctx context.Context,
	parentID int64,
) ([]*pb.VehicleType, error) {
	var rows []struct {
		ID       int64         `db:"id"`
		Name     string        `db:"name"`
		ParentID sql.NullInt64 `db:"parent_id"`
	}

	err := s.pgDB.Select(
		schema.VehicleTypeTableIDCol, schema.VehicleTypeTableNameCol, schema.VehicleTypeTableParentIDCol,
	).
		From(schema.VehicleTypeTable).
		Order(schema.VehicleTypeTablePositionCol.Asc()).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	nodes := make(map[int64]*pb.VehicleType, len(rows))
	for _, row := range rows {
		nodes[row.ID] = &pb.VehicleType{Id: row.ID, Name: row.Name, Childs: []*pb.VehicleType{}}
	}

	result := make([]*pb.VehicleType, 0)

	for _, row := range rows {
		node := nodes[row.ID]
		if row.ParentID.Valid {
			if parent := nodes[row.ParentID.Int64]; parent != nil {
				parent.Childs = append(parent.Childs, node)
			}
		}

		if (parentID == 0 && !row.ParentID.Valid) ||
			(parentID != 0 && row.ParentID.Valid && row.ParentID.Int64 == parentID) {
			result = append(result, node)
		}
	}

	return result, nil
}

func (s *Service) Specs(ctx context.Context, parentID int32) ([]*pb.Spec, error) {
	var rows []struct {
		ID        int32         `db:"id"`
		Name      string        `db:"name"`
		ShortName string        `db:"short_name"`
		ParentID  sql.NullInt32 `db:"parent_id"`
	}

	err := s.pgDB.Select(schema.SpecTableIDCol, schema.SpecTableNameCol,
		schema.SpecTableShortNameCol, schema.SpecTableParentIDCol).
		From(schema.SpecTable).
		Order(schema.SpecTableNameCol.Asc()).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	nodes := make(map[int32]*pb.Spec, len(rows))
	for _, row := range rows {
		nodes[row.ID] = &pb.Spec{Id: row.ID, Name: row.Name, ShortName: row.ShortName, Childs: []*pb.Spec{}}
	}

	specs := make([]*pb.Spec, 0)

	for _, row := range rows {
		node := nodes[row.ID]
		if row.ParentID.Valid {
			if parent := nodes[row.ParentID.Int32]; parent != nil {
				parent.Childs = append(parent.Childs, node)
			}
		}

		if (parentID == 0 && !row.ParentID.Valid) ||
			(parentID != 0 && row.ParentID.Valid && row.ParentID.Int32 == parentID) {
			specs = append(specs, node)
		}
	}

	return specs, nil
}

func (s *Service) PerspectiveGroups(
	ctx context.Context,
	pageID int32,
) ([]*pb.PerspectiveGroup, error) {
	sqSelect := s.pgDB.Select(schema.PerspectiveGroupTableIDCol, schema.PerspectiveGroupTableNameCol).
		From(schema.PerspectiveGroupTable).
		Where(schema.PerspectiveGroupTablePageIDCol.Eq(pageID)).
		Order(schema.PerspectiveGroupTablePositionCol.Asc())

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	perspectiveGroups := make([]*pb.PerspectiveGroup, 0)

	for rows.Next() {
		var group pb.PerspectiveGroup

		err = rows.Scan(&group.Id, &group.Name)
		if err != nil {
			return nil, err
		}

		perspectiveGroups = append(perspectiveGroups, &group)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	group, lookupCtx := errgroup.WithContext(ctx)
	group.SetLimit(catalogueLookupParallelism)

	for _, item := range perspectiveGroups {
		group.Go(func() error {
			values, err := s.Perspectives(lookupCtx, &item.Id)
			if err != nil {
				return err
			}

			item.Perspectives = values

			return nil
		})
	}

	if err := group.Wait(); err != nil {
		return nil, err
	}

	return perspectiveGroups, nil
}

func (s *Service) PerspectivePages(ctx context.Context) ([]*pb.PerspectivePage, error) {
	sqSelect := s.pgDB.Select(schema.PerspectivePageTableIDCol, schema.PerspectivePageTableNameCol).
		From(schema.PerspectivePageTable).
		Order(schema.PerspectivePageTableIDCol.Asc())

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	perspectivePages := make([]*pb.PerspectivePage, 0)

	for rows.Next() {
		var page pb.PerspectivePage

		err = rows.Scan(&page.Id, &page.Name)
		if err != nil {
			return nil, err
		}

		perspectivePages = append(perspectivePages, &page)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	group, lookupCtx := errgroup.WithContext(ctx)
	group.SetLimit(catalogueLookupParallelism)

	for _, item := range perspectivePages {
		group.Go(func() error {
			values, err := s.PerspectiveGroups(lookupCtx, item.GetId())
			if err != nil {
				return err
			}

			item.Groups = values

			return nil
		})
	}

	if err := group.Wait(); err != nil {
		return nil, err
	}

	return perspectivePages, nil
}

func (s *Service) Perspectives(ctx context.Context, groupID *int32) ([]*pb.Perspective, error) {
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

	perspectives := make([]*pb.Perspective, 0)

	for rows.Next() {
		var perspective pb.Perspective

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

func (s *Service) BrandVehicleTypes(
	ctx context.Context,
	brandID int32,
) ([]*pb.BrandVehicleType, error) {
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

	result := make([]*pb.BrandVehicleType, 0)

	for rows.Next() {
		var bvType pb.BrandVehicleType

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
