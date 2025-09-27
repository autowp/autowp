package goautowp

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestGetVODData(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewDonationsClient(conn)

	r, err := client.GetVODData(ctx, &emptypb.Empty{})
	require.NoError(t, err)
	require.NotEmpty(t, r)
	require.NotEmpty(t, r.GetDates())
	require.NotEmpty(t, r.GetSum())
}

func TestGetTransactions(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig("..")

	postgresDB, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	db := goqu.New("postgres", postgresDB)

	_, err = db.Insert(schema.TransactionTable).
		Rows(goqu.Record{
			schema.TransactionTableSumColName:         10,
			schema.TransactionTableCurrencyColName:    "EUR",
			schema.TransactionTableDateColName:        goqu.L("NOW()"),
			schema.TransactionTableContributorColName: "Contributor",
			schema.TransactionTablePurposeColName:     "Purpose",
		}).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	client := NewDonationsClient(conn)

	r, err := client.GetTransactions(ctx, &emptypb.Empty{})
	require.NoError(t, err)
	require.NotEmpty(t, r)
}
