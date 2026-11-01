package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	OfDayTableName           = "of_day"
	OfDayTableItemIDColName  = "item_id"
	OfDayTableUserIDColName  = "user_id"
	OfDayTableDayDateColName = "day_date"
)

var (
	OfDayTable           = goqu.T(OfDayTableName)
	OfDayTableDayDateCol = OfDayTable.Col(OfDayTableDayDateColName)
	OfDayTableItemIDCol  = OfDayTable.Col(OfDayTableItemIDColName)
	OfDayTableUserIDCol  = OfDayTable.Col(OfDayTableUserIDColName)
)

type OfDayRow struct {
	ItemID int64         `db:"item_id"`
	UserID sql.NullInt64 `db:"user_id"`
}
