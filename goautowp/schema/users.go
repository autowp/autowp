package schema

import (
	"time"

	"github.com/doug-martin/goqu/v9"
)

const (
	UserTableName                     = "users"
	UserTableIDColName                = "id"
	UserTableSpecsVolumeColName       = "specs_volume"
	UserTableSpecsVolumeValidColName  = "specs_volume_valid"
	UserTableVotesLeftColName         = "votes_left"
	UserTableVotesPerDayColName       = "votes_per_day"
	UserTableLanguageColName          = "language"
	UserTablePicturesTotalColName     = "pictures_total"
	UserTableRoleColName              = "role"
	UserTableDeletedColName           = "deleted"
	UserTableUUIDColName              = "uuid"
	UserTableEmailColName             = "e_mail"
	UserTableEmailToCheckColName      = "email_to_check"
	UserTableHideEmailColName         = "hide_e_mail"
	UserTablePasswordColName          = "password"
	UserTableEmailCheckCodeColName    = "email_check_code"
	UserTableLastOnlineColName        = "last_online"
	UserTableTimezoneColName          = "timezone"
	UserTableLastIPColName            = "last_ip"
	UserTableRegDateColName           = "reg_date"
	UserTableLastMessageTimeColName   = "last_message_time"
	UserTableMessagingIntervalColName = "messaging_interval"
	UserTableIdentityColName          = "identity"
	UserTableNameColName              = "name"
	UserTableSpecsWeightColName       = "specs_weight"
	UserTableLoginColName             = "login"
	UserTableForumsMessagesColName    = "forums_messages"
	UserTableForumsTopicsColName      = "forums_topics"
	UserTablePicturesAddedColName     = "pictures_added"
	UserTableImgColName               = "img"
	UserTableGreenColName             = "green"
)

var ( //nolint: dupl
	UserTable                     = goqu.T(UserTableName)
	UserTableIDCol                = UserTable.Col(UserTableIDColName)
	UserTableDeletedCol           = UserTable.Col(UserTableDeletedColName)
	UserTableNameCol              = UserTable.Col(UserTableNameColName)
	UserTableIdentityCol          = UserTable.Col(UserTableIdentityColName)
	UserTableLanguageCol          = UserTable.Col(UserTableLanguageColName)
	UserTablePicturesTotalCol     = UserTable.Col(UserTablePicturesTotalColName)
	UserTableSpecsVolumeCol       = UserTable.Col(UserTableSpecsVolumeColName)
	UserTableSpecsVolumeValidCol  = UserTable.Col(UserTableSpecsVolumeValidColName)
	UserTableVotesLeftCol         = UserTable.Col(UserTableVotesLeftColName)
	UserTableVotesPerDayCol       = UserTable.Col(UserTableVotesPerDayColName)
	UserTableUUIDCol              = UserTable.Col(UserTableUUIDColName)
	UserTableLastOnlineCol        = UserTable.Col(UserTableLastOnlineColName)
	UserTableLastIPCol            = UserTable.Col(UserTableLastIPColName)
	UserTableSpecsWeightCol       = UserTable.Col(UserTableSpecsWeightColName)
	UserTableImgCol               = UserTable.Col(UserTableImgColName)
	UserTableEmailCol             = UserTable.Col(UserTableEmailColName)
	UserTableEmailToCheckCol      = UserTable.Col(UserTableEmailToCheckColName)
	UserTableRegDateCol           = UserTable.Col(UserTableRegDateColName)
	UserTableLastMessageTimeCol   = UserTable.Col(UserTableLastMessageTimeColName)
	UserTableMessagingIntervalCol = UserTable.Col(UserTableMessagingIntervalColName)
	UserTableLoginCol             = UserTable.Col(UserTableLoginColName)
	UserTablePasswordCol          = UserTable.Col(UserTablePasswordColName)
	UserTableTimezoneCol          = UserTable.Col(UserTableTimezoneColName)
	UserTablePicturesAddedCol     = UserTable.Col(UserTablePicturesAddedColName)
	UserTableGreenCol             = UserTable.Col(UserTableGreenColName)
)

type UsersRow struct {
	ID            int64      `db:"id"`
	Name          string     `db:"name"`
	Deleted       bool       `db:"deleted"`
	Identity      *string    `db:"identity"`
	LastOnline    *time.Time `db:"last_online"`
	EMail         *string    `db:"email"`
	Img           *int       `db:"img"`
	SpecsWeight   float64    `db:"specs_weight"`
	SpecsVolume   int64      `db:"specs_volume"`
	PicturesTotal int64      `db:"pictures_total"`
	VotesLeft     int64      `db:"votes_left"`
	VotesPerDay   int64      `db:"votes_per_day"`
	Language      string     `db:"language"`
	Timezone      string     `db:"timezone"`
	RegDate       *time.Time `db:"reg_date"`
	PicturesAdded int64      `db:"pictures_added"`
	LastIP        string     `db:"last_ip"`
	Login         *string    `db:"login"`
	Green         bool       `db:"green"`
	UUID          *[]byte    `db:"uuid"`
}
