package items

import (
	"context"
	"database/sql"
	"slices"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const vehicleTypeUpdateTimeout = 5 * time.Minute

func (s *Repository) AddItemVehicleType(ctx context.Context, itemID, vehicleTypeID int64) error {
	return s.updateItemVehicleTypes(ctx, itemID, func(ctx context.Context, tx *goqu.TxDatabase) (bool, error) {
		res, err := tx.Insert(schema.ItemVehicleTypeTable).Rows(goqu.Record{
			schema.ItemVehicleTypeTableItemIDColName:        itemID,
			schema.ItemVehicleTypeTableVehicleTypeIDColName: vehicleTypeID,
			schema.ItemVehicleTypeTableInheritedColName:     false,
		}).OnConflict(goqu.DoUpdate(
			schema.ItemVehicleTypeTableItemIDColName+","+schema.ItemVehicleTypeTableVehicleTypeIDColName,
			goqu.Record{schema.ItemVehicleTypeTableInheritedColName: false},
		).Where(schema.ItemVehicleTypeTableInheritedCol.IsTrue())).Executor().ExecContext(ctx)
		if err != nil {
			return false, err
		}

		count, err := res.RowsAffected()

		return count > 0, err
	})
}

func (s *Repository) RemoveItemVehicleType(ctx context.Context, itemID, vehicleTypeID int64) error {
	return s.updateItemVehicleTypes(ctx, itemID, func(ctx context.Context, tx *goqu.TxDatabase) (bool, error) {
		res, err := tx.Delete(schema.ItemVehicleTypeTable).Where(
			schema.ItemVehicleTypeTableItemIDCol.Eq(itemID),
			schema.ItemVehicleTypeTableVehicleTypeIDCol.Eq(vehicleTypeID),
			schema.ItemVehicleTypeTableInheritedCol.IsFalse(),
		).Executor().ExecContext(ctx)
		if err != nil {
			return false, err
		}

		count, err := res.RowsAffected()

		return count > 0, err
	})
}

func (s *Repository) RefreshItemVehicleTypeInheritanceFromParents(ctx context.Context, itemID int64) error {
	return s.updateItemVehicleTypes(ctx, itemID, nil)
}

// Once a mutation starts, finish its inheritance propagation even if the caller disconnects.
// Recursive updates share this bounded context, and acquire only one item lock at a time.
func (s *Repository) updateItemVehicleTypes(
	ctx context.Context,
	itemID int64,
	mutate func(context.Context, *goqu.TxDatabase) (bool, error),
) error {
	if err := ctx.Err(); err != nil {
		return err
	}

	updateCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), vehicleTypeUpdateTimeout)
	defer cancel()

	return s.updateItemVehicleTypesLocked(updateCtx, itemID, mutate)
}

func (s *Repository) updateItemVehicleTypesLocked(
	ctx context.Context,
	itemID int64,
	mutate func(context.Context, *goqu.TxDatabase) (bool, error),
) error {
	tx, err := s.db.BeginTx(ctx, &sql.TxOptions{Isolation: sql.LevelReadCommitted})
	if err != nil {
		return err
	}

	// Rollback also covers panics; after a successful commit it is a harmless no-op.
	defer func() { _ = tx.Rollback() }()

	var lockedID int64

	found, err := tx.Select(schema.ItemTableIDCol).From(schema.ItemTable).
		Where(schema.ItemTableIDCol.Eq(itemID)).ForUpdate(goqu.Wait).
		ScanValContext(ctx, &lockedID)
	if err != nil {
		return err
	}

	if !found {
		return ErrItemNotFound
	}

	changed := false
	if mutate != nil {
		changed, err = mutate(ctx, tx)
		if err != nil {
			return err
		}
	}

	inheritedChanged, err := refreshItemVehicleTypes(ctx, tx, itemID)
	if err != nil {
		return err
	}

	changed = changed || inheritedChanged

	if err := tx.Commit(); err != nil {
		return err
	}

	if !changed {
		return nil
	}

	// Commit before visiting descendants: a child must see the committed parent values,
	// and overlapping updates must never hold parent and child locks in opposite order.
	ids := []int64{}

	err = s.db.Select(schema.ItemParentTableItemIDCol).From(schema.ItemParentTable).
		Where(schema.ItemParentTableParentIDCol.Eq(itemID)).ScanValsContext(ctx, &ids)
	if err != nil {
		return err
	}

	for _, childID := range ids {
		if err := s.updateItemVehicleTypesLocked(ctx, childID, nil); err != nil {
			return err
		}
	}

	return nil
}

func refreshItemVehicleTypes(ctx context.Context, tx *goqu.TxDatabase, itemID int64) (bool, error) {
	ownCount, err := tx.From(schema.ItemVehicleTypeTable).Where(
		schema.ItemVehicleTypeTableItemIDCol.Eq(itemID),
		schema.ItemVehicleTypeTableInheritedCol.IsFalse(),
	).CountContext(ctx)
	if err != nil {
		return false, err
	}

	types := []int64{}
	if ownCount == 0 {
		err = tx.From(schema.ItemVehicleTypeTable).
			Select(schema.ItemVehicleTypeTableVehicleTypeIDCol).Distinct().
			Join(schema.ItemParentTable, goqu.On(
				schema.ItemVehicleTypeTableItemIDCol.Eq(schema.ItemParentTableParentIDCol),
			)).Where(schema.ItemParentTableItemIDCol.Eq(itemID)).ScanValsContext(ctx, &types)
		if err != nil {
			return false, err
		}
	}

	current := []int64{}

	err = tx.From(schema.ItemVehicleTypeTable).Select(schema.ItemVehicleTypeTableVehicleTypeIDCol).Where(
		schema.ItemVehicleTypeTableItemIDCol.Eq(itemID),
		schema.ItemVehicleTypeTableInheritedCol.IsTrue(),
	).ScanValsContext(ctx, &current)
	if err != nil {
		return false, err
	}

	slices.Sort(types)
	slices.Sort(current)

	if slices.Equal(types, current) {
		return false, nil
	}

	_, err = tx.Delete(schema.ItemVehicleTypeTable).Where(
		schema.ItemVehicleTypeTableItemIDCol.Eq(itemID),
		schema.ItemVehicleTypeTableInheritedCol.IsTrue(),
	).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	if len(types) > 0 {
		rows := make([]goqu.Record, 0, len(types))
		for _, typeID := range types {
			rows = append(rows, goqu.Record{
				schema.ItemVehicleTypeTableItemIDColName:        itemID,
				schema.ItemVehicleTypeTableVehicleTypeIDColName: typeID,
				schema.ItemVehicleTypeTableInheritedColName:     true,
			})
		}

		_, err = tx.Insert(schema.ItemVehicleTypeTable).Rows(rows).OnConflict(goqu.DoNothing()).
			Executor().ExecContext(ctx)
		if err != nil {
			return false, err
		}
	}

	return true, nil
}
