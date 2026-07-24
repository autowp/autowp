package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
)

const (
	PersonalMessageTableName                 = "personal_message"
	PersonalMessageTableCreatedAtColName     = "created_at"
	PersonalMessageTableContentsColName      = "contents"
	PersonalMessageTableDeletedByFromColName = "deleted_by_from"
	PersonalMessageTableDeletedByToColName   = "deleted_by_to"
	PersonalMessageTableFromUserIDColName    = "from_user_id"
	PersonalMessageTableToUserIDColName      = "to_user_id"
	PersonalMessageTableReadenColName        = "readen"
)

var (
	PersonalMessageTable             = goqu.T(PersonalMessageTableName)
	PersonalMessageTableIDCol        = PersonalMessageTable.Col("id")
	PersonalMessageTableCreatedAtCol = PersonalMessageTable.Col(
		PersonalMessageTableCreatedAtColName,
	)
	PersonalMessageTableDeletedByFromCol = PersonalMessageTable.Col(
		PersonalMessageTableDeletedByFromColName,
	)
	PersonalMessageTableDeletedByToCol = PersonalMessageTable.Col(
		PersonalMessageTableDeletedByToColName,
	)
	PersonalMessageTableFromUserIDCol = PersonalMessageTable.Col(
		PersonalMessageTableFromUserIDColName,
	)
	PersonalMessageTableToUserIDCol = PersonalMessageTable.Col(
		PersonalMessageTableToUserIDColName,
	)
	PersonalMessageTableReadenCol = PersonalMessageTable.Col(
		PersonalMessageTableReadenColName,
	)
)

type PersonalMessageRow struct {
	ID         int64         `db:"id"           goqu:"pk,skipinsert"`
	FromUserID sql.NullInt64 `db:"from_user_id"`
	ToUserID   int64         `db:"to_user_id"`
	Readen     bool          `db:"readen"`
	Contents   string        `db:"contents"`
	CreatedAt  time.Time     `db:"created_at"`
}
