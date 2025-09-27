package schema

import "github.com/doug-martin/goqu/v9"

const (
	UserAccountTableName = "user_account"
)

var (
	UserAccountTable             = goqu.T(UserAccountTableName)
	UserAccountTableIDCol        = UserAccountTable.Col("id")
	UserAccountTableUserIDCol    = UserAccountTable.Col("user_id")
	UserAccountTableServiceIDCol = UserAccountTable.Col("service_id")
)

type UserAccountRow struct {
	ID         int64  `db:"id"`
	UserID     int64  `db:"user_id"`
	ServiceID  string `db:"service_id"`
	ExternalID string `db:"external_id"`
	Name       string `db:"name"`
	Link       string `db:"link"`
	UsedForReg bool   `db:"used_for_reg"`
}
