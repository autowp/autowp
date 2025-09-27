package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
)

const (
	PersonalMessagesTableName                 = "personal_messages"
	PersonalMessagesTableAddDatetimeColName   = "add_datetime"
	PersonalMessagesTableContentsColName      = "contents"
	PersonalMessagesTableDeletedByFromColName = "deleted_by_from"
	PersonalMessagesTableDeletedByToColName   = "deleted_by_to"
	PersonalMessagesTableFromUserIDColName    = "from_user_id"
	PersonalMessagesTableToUserIDColName      = "to_user_id"
	PersonalMessagesTableReadenColName        = "readen"
)

var (
	PersonalMessagesTable               = goqu.T(PersonalMessagesTableName)
	PersonalMessagesTableIDCol          = PersonalMessagesTable.Col("id")
	PersonalMessagesTableAddDatetimeCol = PersonalMessagesTable.Col(
		PersonalMessagesTableAddDatetimeColName,
	)
	PersonalMessagesTableDeletedByFromCol = PersonalMessagesTable.Col(
		PersonalMessagesTableDeletedByFromColName,
	)
	PersonalMessagesTableDeletedByToCol = PersonalMessagesTable.Col(
		PersonalMessagesTableDeletedByToColName,
	)
	PersonalMessagesTableFromUserIDCol = PersonalMessagesTable.Col(
		PersonalMessagesTableFromUserIDColName,
	)
	PersonalMessagesTableToUserIDCol = PersonalMessagesTable.Col(
		PersonalMessagesTableToUserIDColName,
	)
	PersonalMessagesTableReadenCol = PersonalMessagesTable.Col(
		PersonalMessagesTableReadenColName,
	)
)

type PersonalMessageRow struct {
	ID          int64         `db:"id"`
	FromUserID  sql.NullInt64 `db:"from_user_id"`
	ToUserID    int64         `db:"to_user_id"`
	Readen      bool          `db:"readen"`
	Contents    string        `db:"contents"`
	AddDatetime time.Time     `db:"add_datetime"`
}
