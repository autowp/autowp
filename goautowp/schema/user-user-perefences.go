package schema

import "github.com/doug-martin/goqu/v9"

const (
	UserUserPreferencesTableName             = "user_user_preferences"
	UserUserPreferencesTableDCNColName       = "disable_comments_notifications"
	UserUserPreferencesTableUserIDColName    = "user_id"
	UserUserPreferencesTableToUserIDColName  = "to_user_id"
	UserUserPreferencesTableBlacklistColName = "blacklist"
)

var (
	UserUserPreferencesTable            = goqu.T(UserUserPreferencesTableName)
	UserUserPreferencesTableUserIDCol   = UserUserPreferencesTable.Col(UserUserPreferencesTableUserIDColName)
	UserUserPreferencesTableToUserIDCol = UserUserPreferencesTable.Col(UserUserPreferencesTableToUserIDColName)
	UserUserPreferencesTableDCNCol      = UserUserPreferencesTable.Col(
		UserUserPreferencesTableDCNColName,
	)
	UserUserPreferencesTableBlacklistCol = UserUserPreferencesTable.Col(
		UserUserPreferencesTableBlacklistColName,
	)
)
