package traffic

import (
	"context"
	"errors"
	"fmt"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/lib/pq"
)

var (
	ErrSourceNotFound = errors.New("traffic source not found")
	ErrSourceExists   = errors.New("traffic source already exists")
)

func (s *Monitoring) Sources(ctx context.Context) ([]Source, error) {
	var rows []struct {
		ID          string         `db:"id"`
		Name        string         `db:"name"`
		Networks    pq.StringArray `db:"networks"`
		DNSSuffixes pq.StringArray `db:"dns_suffixes"`
		References  pq.StringArray `db:"reference_urls"`
	}

	err := s.db.From(schema.TrafficSourceTable).Select(
		goqu.C(schema.TrafficSourceTableIDColName), goqu.C(schema.TrafficSourceTableNameColName),
		goqu.C(schema.TrafficSourceTableNetworksColName), goqu.C(schema.TrafficSourceTableDNSSuffixesColName),
		goqu.C(schema.TrafficSourceTableReferencesColName),
	).Order(schema.TrafficSourceTableIDCol.Asc()).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, fmt.Errorf("read traffic sources: %w", err)
	}

	result := make([]Source, 0, len(rows))
	for _, row := range rows {
		result = append(result, Source{
			ID: row.ID, Name: row.Name, Networks: row.Networks,
			DNSSuffixes: row.DNSSuffixes, References: row.References,
		})
	}

	return result, nil
}

func (s *Monitoring) CreateSource(ctx context.Context, source Source) error {
	_, err := s.db.Insert(schema.TrafficSourceTable).Rows(goqu.Record{
		schema.TrafficSourceTableIDColName:          source.ID,
		schema.TrafficSourceTableNameColName:        source.Name,
		schema.TrafficSourceTableNetworksColName:    pq.Array(append([]string{}, source.Networks...)),
		schema.TrafficSourceTableDNSSuffixesColName: pq.Array(append([]string{}, source.DNSSuffixes...)),
		schema.TrafficSourceTableReferencesColName:  pq.Array(append([]string{}, source.References...)),
	}).Executor().ExecContext(ctx)

	var pgErr *pq.Error
	if errors.As(err, &pgErr) && pgErr.Code == "23505" {
		return ErrSourceExists
	}

	if err != nil {
		return fmt.Errorf("create traffic source: %w", err)
	}

	return nil
}

func (s *Monitoring) UpdateSource(ctx context.Context, source Source, paths []string) error {
	values := make(goqu.Record)

	for _, path := range paths {
		switch path {
		case "name":
			values[schema.TrafficSourceTableNameColName] = source.Name
		case "networks":
			values[schema.TrafficSourceTableNetworksColName] = pq.Array(append([]string{}, source.Networks...))
		case "dns_suffixes":
			values[schema.TrafficSourceTableDNSSuffixesColName] = pq.Array(append([]string{}, source.DNSSuffixes...))
		case "references":
			values[schema.TrafficSourceTableReferencesColName] = pq.Array(append([]string{}, source.References...))
		}
	}

	result, err := s.db.Update(schema.TrafficSourceTable).Set(values).
		Where(schema.TrafficSourceTableIDCol.Eq(source.ID)).Executor().ExecContext(ctx)
	if err != nil {
		return fmt.Errorf("update traffic source: %w", err)
	}

	rows, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("count updated traffic sources: %w", err)
	}

	if rows == 0 {
		return ErrSourceNotFound
	}

	return nil
}

func (s *Monitoring) DeleteSource(ctx context.Context, id string) error {
	result, err := s.db.Delete(schema.TrafficSourceTable).
		Where(schema.TrafficSourceTableIDCol.Eq(id)).
		Executor().
		ExecContext(ctx)
	if err != nil {
		return fmt.Errorf("delete traffic source: %w", err)
	}

	rows, err := result.RowsAffected()
	if err != nil {
		return fmt.Errorf("count deleted traffic sources: %w", err)
	}

	if rows == 0 {
		return ErrSourceNotFound
	}

	return nil
}
