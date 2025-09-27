package goautowp

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// ContactsRepository Main Object.
type ContactsRepository struct {
	autowpDB *goqu.Database
}

// NewContactsRepository constructor.
func NewContactsRepository(db *goqu.Database) *ContactsRepository {
	return &ContactsRepository{
		autowpDB: db,
	}
}

func (s *ContactsRepository) isExists(
	ctx context.Context,
	id int64,
	contactID int64,
) (bool, error) {
	v := 0

	return s.autowpDB.Select(goqu.V(1)).
		From(schema.ContactTable).
		Where(schema.ContactTableUserIDCol.Eq(id), schema.ContactTableContactUserIDCol.Eq(contactID)).
		ScanValContext(ctx, &v)
}

func (s *ContactsRepository) create(ctx context.Context, id int64, contactID int64) error {
	_, err := s.autowpDB.Insert(schema.ContactTable).Rows(goqu.Record{
		schema.ContactTableUserIDColName:        id,
		schema.ContactTableContactUserIDColName: contactID,
		schema.ContactTableTimestampColName:     goqu.Func("NOW"),
	}).OnConflict(goqu.DoNothing()).Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return nil
}

func (s *ContactsRepository) delete(ctx context.Context, id int64, contactID int64) error {
	_, err := s.autowpDB.Delete(schema.ContactTable).
		Where(schema.ContactTableUserIDCol.Eq(id), schema.ContactTableContactUserIDCol.Eq(contactID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return nil
}

func (s *ContactsRepository) deleteUserEverywhere(ctx context.Context, id int64) error {
	ctx = context.WithoutCancel(ctx)

	_, err := s.autowpDB.Delete(schema.ContactTable).
		Where(schema.ContactTableUserIDCol.Eq(id)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	_, err = s.autowpDB.Delete(schema.ContactTable).
		Where(schema.ContactTableContactUserIDCol.Eq(id)).
		Executor().ExecContext(ctx)

	return err
}
