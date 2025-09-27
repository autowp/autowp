package textstorage

import (
	"context"
	"errors"
	"fmt"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

var ErrTextNotFound = errors.New("text not found")

// Repository Main Object.
type Repository struct {
	db *goqu.Database
}

// New constructor.
func New(db *goqu.Database) *Repository {
	return &Repository{
		db: db,
	}
}

func (s *Repository) Text(ctx context.Context, id int32) (string, error) {
	sqlSelect := s.db.From(schema.TextstorageTextTable).
		Select(schema.TextstorageTextTableTextCol).
		Where(schema.TextstorageTextTableIDCol.Eq(id))

	result := ""

	success, err := sqlSelect.Executor().ScanValContext(ctx, &result)
	if err != nil {
		return "", err
	}

	if !success {
		return "", fmt.Errorf("%w: `%v`", ErrTextNotFound, id)
	}

	return result, nil
}

func (s *Repository) FirstText(ctx context.Context, ids []int32) (string, error) {
	if len(ids) == 0 {
		return "", nil
	}

	args := append([]interface{}{schema.TextstorageTextTableIDColName}, ids)
	result := ""

	success, err := s.db.From(schema.TextstorageTextTable).
		Select(schema.TextstorageTextTableTextCol).
		Where(
			schema.TextstorageTextTableIDCol.In(ids),
			goqu.Func("length", schema.TextstorageTextTableTextCol).Gt(0),
		).
		Order(goqu.Func("field", args...).Asc()).
		Limit(1).
		ScanValContext(ctx, &result)
	if err != nil {
		return "", err
	}

	if !success {
		return "", fmt.Errorf("%w: `%v`", ErrTextNotFound, ids)
	}

	return result, nil
}

func (s *Repository) CreateText(ctx context.Context, text string, userID int64) (int32, error) {
	ctx = context.WithoutCancel(ctx)

	res, err := s.db.Insert(schema.TextstorageTextTable).Rows(goqu.Record{
		schema.TextstorageTextTableRevisionColName:    0,
		schema.TextstorageTextTableTextColName:        "",
		schema.TextstorageTextTableLastUpdatedColName: goqu.Func("NOW"),
	}).Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	lastInsertID, err := res.LastInsertId()
	if err != nil {
		return 0, err
	}

	id := int32(lastInsertID) //nolint: gosec
	err = s.SetText(ctx, id, text, userID)

	return id, err
}

func (s *Repository) SetText(ctx context.Context, textID int32, text string, userID int64) error {
	ctx = context.WithoutCancel(ctx)

	res, err := s.db.Update(schema.TextstorageTextTable).
		Set(goqu.Record{
			schema.TextstorageTextTableRevisionColName: goqu.L(
				"? + 1", goqu.C(schema.TextstorageTextTableRevisionColName)),
			schema.TextstorageTextTableTextColName:        text,
			schema.TextstorageTextTableLastUpdatedColName: goqu.Func("NOW"),
		}).
		Where(
			schema.TextstorageTextTableIDCol.Eq(textID),
			schema.TextstorageTextTableTextCol.Neq(text),
		).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return err
	}

	if affected > 0 {
		_, err = s.db.Insert(schema.TextstorageRevisionTable).
			Cols(
				schema.TextstorageRevisionTableTextIDColName,
				schema.TextstorageRevisionTableRevisionColName,
				schema.TextstorageRevisionTableTextColName,
				schema.TextstorageRevisionTableTimestampColName,
				schema.TextstorageRevisionTableUserIDColName,
			).
			FromQuery(
				s.db.Select(
					schema.TextstorageTextTableIDCol,
					schema.TextstorageTextTableRevisionCol,
					schema.TextstorageTextTableTextCol,
					schema.TextstorageTextTableLastUpdatedCol,
					goqu.V(userID),
				).
					From(schema.TextstorageTextTable).
					Where(schema.TextstorageTextTableIDCol.Eq(textID)),
			).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) TextUserIDs(ctx context.Context, textID int32) ([]int64, error) {
	userIDs := make([]int64, 0)

	err := s.db.Select(schema.TextstorageRevisionTableUserIDCol).Distinct().
		From(schema.TextstorageRevisionTable).
		Where(
			schema.TextstorageRevisionTableUserIDCol.IsNotNull(),
			schema.TextstorageRevisionTableTextIDCol.Eq(textID),
		).ScanValsContext(ctx, &userIDs)

	return userIDs, err
}
