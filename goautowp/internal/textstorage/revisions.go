package textstorage

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/internal/schema"
)

var ErrRevisionNotFound = errors.New("text revision not found")

func (s *Repository) LatestRevision(ctx context.Context, id int64) (int64, error) {
	var revision int64

	found, err := s.db.Select(schema.TextstorageTextTableRevisionCol).
		From(schema.TextstorageTextTable).
		Where(schema.TextstorageTextTableIDCol.Eq(id)).
		ScanValContext(ctx, &revision)
	if err != nil {
		return 0, err
	}

	if !found {
		return 0, ErrTextNotFound
	}

	return revision, nil
}

func (s *Repository) Revision(ctx context.Context, id, revision int64) (schema.TextstorageRevisionRow, error) {
	var row schema.TextstorageRevisionRow

	found, err := s.db.Select(schema.TextstorageRevisionTableTextCol, schema.TextstorageRevisionTableUserIDCol).
		From(schema.TextstorageRevisionTable).
		Where(
			schema.TextstorageRevisionTableTextIDCol.Eq(id),
			schema.TextstorageRevisionTableRevisionCol.Eq(revision),
		).
		ScanStructContext(ctx, &row)
	if err != nil {
		return schema.TextstorageRevisionRow{}, err
	}

	if !found {
		return schema.TextstorageRevisionRow{}, ErrRevisionNotFound
	}

	return row, nil
}
