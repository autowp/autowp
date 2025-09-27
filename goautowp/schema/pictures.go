package schema

import (
	"database/sql"
	"time"

	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

type PictureStatus string

const (
	PictureStatusUnknown  PictureStatus = ""
	PictureStatusAccepted PictureStatus = "accepted"
	PictureStatusRemoving PictureStatus = "removing"
	PictureStatusRemoved  PictureStatus = "removed"
	PictureStatusInbox    PictureStatus = "inbox"

	PictureTableName                      = "pictures"
	PictureTableIDColName                 = "id"
	PictureTableImageIDColName            = "image_id"
	PictureTableIdentityColName           = "identity"
	PictureTableIPColName                 = "ip"
	PictureTableOwnerIDColName            = "owner_id"
	PictureTableStatusColName             = "status"
	PictureTableChangeStatusUserIDColName = "change_status_user_id"
	PictureTableWidthColName              = "width"
	PictureTableHeightColName             = "height"
	PictureTableContentCountColName       = "content_count"
	PictureTableReplacePictureIDColName   = "replace_picture_id"
	PictureTablePointColName              = "point"
	PictureTableNameColName               = "name"
	PictureTableTakenDayColName           = "taken_day"
	PictureTableTakenMonthColName         = "taken_month"
	PictureTableTakenYearColName          = "taken_year"
	PictureTableCopyrightsTextIDColName   = "copyrights_text_id"
	PictureTableAcceptDatetimeColName     = "accept_datetime"
	PictureTableRemovingDateColName       = "removing_date"
	PictureTableAddDateColName            = "add_date"
	PictureTableFilesizeColName           = "filesize"
	PictureTableDPIXColName               = "dpi_x"
	PictureTableDPIYColName               = "dpi_y"

	PicturesTableIdentityLength = 6
)

var (
	PictureTable                      = goqu.T(PictureTableName)
	PictureTableIDCol                 = PictureTable.Col(PictureTableIDColName)
	PictureTableIdentityCol           = PictureTable.Col(PictureTableIdentityColName)
	PictureTableOwnerIDCol            = PictureTable.Col(PictureTableOwnerIDColName)
	PictureTableStatusCol             = PictureTable.Col(PictureTableStatusColName)
	PictureTableImageIDCol            = PictureTable.Col(PictureTableImageIDColName)
	PictureTableChangeStatusUserIDCol = PictureTable.Col(PictureTableChangeStatusUserIDColName)
	PictureTableWidthCol              = PictureTable.Col(PictureTableWidthColName)
	PictureTableHeightCol             = PictureTable.Col(PictureTableHeightColName)
	PictureTableReplacePictureIDCol   = PictureTable.Col(PictureTableReplacePictureIDColName)
	PictureTablePointCol              = PictureTable.Col(PictureTablePointColName)
	PictureTableCopyrightsTextIDCol   = PictureTable.Col(PictureTableCopyrightsTextIDColName)
	PictureTableAcceptDatetimeCol     = PictureTable.Col(PictureTableAcceptDatetimeColName)
	PictureTableContentCountCol       = PictureTable.Col(PictureTableContentCountColName)
	PictureTableRemovingDateCol       = PictureTable.Col(PictureTableRemovingDateColName)
)

type PictureRow struct {
	ID                 int64          `db:"id"`
	OwnerID            sql.NullInt64  `db:"owner_id"`
	ChangeStatusUserID sql.NullInt64  `db:"change_status_user_id"`
	Identity           string         `db:"identity"`
	Status             PictureStatus  `db:"status"`
	ImageID            sql.NullInt64  `db:"image_id"`
	Width              uint16         `db:"width"`
	Height             uint16         `db:"height"`
	Point              NullPoint      `db:"point"`
	TakenYear          sql.NullInt16  `db:"taken_year"`
	TakenMonth         sql.NullByte   `db:"taken_month"`
	TakenDay           sql.NullByte   `db:"taken_day"`
	CopyrightsTextID   sql.NullInt32  `db:"copyrights_text_id"`
	AcceptDatetime     sql.NullTime   `db:"accept_datetime"`
	ReplacePictureID   sql.NullInt64  `db:"replace_picture_id"`
	IP                 util.IP        `db:"ip"`
	Name               sql.NullString `db:"name"`
	AddDate            time.Time      `db:"add_date"`
	DPIX               sql.NullInt32  `db:"dpi_x"`
	DPIY               sql.NullInt32  `db:"dpi_y"`
}
