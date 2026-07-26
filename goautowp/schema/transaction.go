package schema

import "github.com/doug-martin/goqu/v9"

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
