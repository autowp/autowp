package donations

import (
	"context"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
)

type Repository struct {
	db *goqu.Database
}

func NewRepository(db *goqu.Database) *Repository {
	return &Repository{db: db}
}

func (s *Repository) Transactions(ctx context.Context) ([]schema.TransactionRow, error) {
	rows := make([]schema.TransactionRow, 0)

	err := s.db.Select(schema.TransactionTableSumCol, schema.TransactionTableCurrencyCol, schema.TransactionTableDateCol,
		schema.TransactionTableContributorCol, schema.TransactionTablePurposeCol, schema.TransactionTableUserIDCol).
		From(schema.TransactionTable).
		Order(schema.TransactionTableDateCol.Asc()).
		Where(schema.TransactionTableDateCol.Gt(goqu.L("CURRENT_DATE - INTERVAL '6 months'"))).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	return rows, nil
}
