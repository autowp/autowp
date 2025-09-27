package schema

import "github.com/doug-martin/goqu/v9"

const (
	ContactTableName                 = "contact"
	ContactTableUserIDColName        = "user_id"
	ContactTableContactUserIDColName = "contact_user_id"
	ContactTableTimestampColName     = "timestamp"
)

var (
	ContactTable                 = goqu.T(ContactTableName)
	ContactTableUserIDCol        = ContactTable.Col("user_id")
	ContactTableContactUserIDCol = ContactTable.Col("contact_user_id")
	ContactTableTimestampCol     = ContactTable.Col("timestamp")
)
