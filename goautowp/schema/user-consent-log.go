package schema

import "github.com/doug-martin/goqu/v9"

const (
	UserConsentLogTableName                 = "user_consent_log"
	UserConsentLogTableIDColName            = "id"
	UserConsentLogTableUserIDColName        = "user_id"
	UserConsentLogTableAnalyticsColName     = "analytics"
	UserConsentLogTablePolicyVersionColName = "policy_version"
	UserConsentLogTableCreatedAtColName     = "created_at"
)

var (
	UserConsentLogTable             = goqu.T(UserConsentLogTableName)
	UserConsentLogTableIDCol        = UserConsentLogTable.Col(UserConsentLogTableIDColName)
	UserConsentLogTableUserIDCol    = UserConsentLogTable.Col(UserConsentLogTableUserIDColName)
	UserConsentLogTableCreatedAtCol = UserConsentLogTable.Col(UserConsentLogTableCreatedAtColName)
)
