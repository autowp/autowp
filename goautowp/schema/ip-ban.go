package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
	"github.com/jackc/pgtype"
)

const (
	IPBanTableName            = "ip_ban"
	IPBanTableIPColName       = "ip"
	IPBanTableUntilColName    = "until"
	IPBanTableByUserIDColName = "by_user_id"
	IPBanTableReasonColName   = "reason"
)

var (
	IPBanTable            = goqu.T(IPBanTableName)
	IPBanTableIPCol       = IPBanTable.Col(IPBanTableIPColName)
	IPBanTableUntilCol    = IPBanTable.Col(IPBanTableUntilColName)
	IPBanTableByUserIDCol = IPBanTable.Col(IPBanTableByUserIDColName)
	IPBanTableReasonCol   = IPBanTable.Col(IPBanTableReasonColName)
)

type IPBanRow struct {
	PgInet   pgtype.Inet   `db:"ip"`
	Until    time.Time     `db:"until"`
	Reason   string        `db:"reason"`
	ByUserID sql.NullInt64 `db:"by_user_id"`
}
