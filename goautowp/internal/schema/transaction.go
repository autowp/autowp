package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
)

const (
	TransactionTableName               = "transaction"
	TransactionTableSumColName         = "sum"
	TransactionTableCurrencyColName    = "currency"
	TransactionTableDateColName        = "date"
	TransactionTableContributorColName = "contributor"
	TransactionTablePurposeColName     = "purpose"
	TransactionTableUserIDColName      = "user_id"
)

var (
	TransactionTable               = goqu.T(TransactionTableName)
	TransactionTableSumCol         = TransactionTable.Col(TransactionTableSumColName)
	TransactionTableCurrencyCol    = TransactionTable.Col(TransactionTableCurrencyColName)
	TransactionTableDateCol        = TransactionTable.Col(TransactionTableDateColName)
	TransactionTableContributorCol = TransactionTable.Col(TransactionTableContributorColName)
	TransactionTablePurposeCol     = TransactionTable.Col(TransactionTablePurposeColName)
	TransactionTableUserIDCol      = TransactionTable.Col(TransactionTableUserIDColName)
)

type TransactionRow struct {
	Sum         int32         `db:"sum"`
	Currency    string        `db:"currency"`
	Date        time.Time     `db:"date"`
	Contributor string        `db:"contributor"`
	Purpose     string        `db:"purpose"`
	UserID      sql.NullInt64 `db:"user_id"`
}
