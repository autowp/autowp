package items

import (
	"context"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
)

func (s *Repository) CreateLink(ctx context.Context, row schema.LinkRow) (int64, error) {
	var id int64

	found, err := s.db.Insert(schema.ItemLinkTable).Rows(row).
		Returning(schema.ItemLinkTableIDCol).Executor().ScanValContext(ctx, &id)
	if err != nil {
		return 0, err
	}

	if !found {
		return 0, errNoRowsReturned
	}

	return id, nil
}

func (s *Repository) UpdateLink(ctx context.Context, row schema.LinkRow) error {
	_, err := s.db.Update(schema.ItemLinkTable).Set(goqu.Record{
		schema.ItemLinkTableNameColName:   row.Name,
		schema.ItemLinkTableURLColName:    row.URL,
		schema.ItemLinkTableTypeColName:   row.Type,
		schema.ItemLinkTableItemIDColName: row.ItemID,
	}).Where(schema.ItemLinkTableIDCol.Eq(row.ID)).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) DeleteLink(ctx context.Context, id int64) error {
	_, err := s.db.Delete(schema.ItemLinkTable).Where(schema.ItemLinkTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)

	return err
}
