package schema

import "github.com/doug-martin/goqu/v9"

const (
	IPBanTableName            = "ip_ban"
	IPBanTableIPColName       = "ip"
	IPBanTableUntilColName    = "until"
	IPBanTableByUserIDColName = "by_user_id"
	IPBanTableReasonColName   = "reason"

	IPMonitoringTableName             = "ip_monitoring"
	IPMonitoringTableIPColName        = "ip"
	IPMonitoringTableCountColName     = "count"
	IPMonitoringTableDayDateColName   = "day_date"
	IPMonitoringTableMinuteColName    = "minute"
	IPMonitoringTableTenminuteColName = "tenminute"
	IPMonitoringTableHourColName      = "hour"

	IPWhitelistTableName               = "ip_whitelist"
	IPWhitelistTableIPColName          = "ip"
	IPWhitelistTableDescriptionColName = "description"

	TransactionTableName               = "transaction"
	TransactionTableSumColName         = "sum"
	TransactionTableCurrencyColName    = "currency"
	TransactionTableDateColName        = "date"
	TransactionTableContributorColName = "contributor"
	TransactionTablePurposeColName     = "purpose"
	TransactionTableUserIDColName      = "user_id"

	UserUserPreferencesTableName            = "user_user_preferences"
	UserUserPreferencesTableDCNColName      = "disable_comments_notifications"
	UserUserPreferencesTableUserIDColName   = "user_id"
	UserUserPreferencesTableToUserIDColName = "to_user_id"
)

var (
	IPBanTable            = goqu.T(IPBanTableName)
	IPBanTableIPCol       = IPBanTable.Col(IPBanTableIPColName)
	IPBanTableUntilCol    = IPBanTable.Col(IPBanTableUntilColName)
	IPBanTableByUserIDCol = IPBanTable.Col(IPBanTableByUserIDColName)
	IPBanTableReasonCol   = IPBanTable.Col(IPBanTableReasonColName)

	IPMonitoringTable             = goqu.T(IPMonitoringTableName)
	IPMonitoringTableIPCol        = IPMonitoringTable.Col(IPMonitoringTableIPColName)
	IPMonitoringTableCountCol     = IPMonitoringTable.Col(IPMonitoringTableCountColName)
	IPMonitoringTableDayDateCol   = IPMonitoringTable.Col(IPMonitoringTableDayDateColName)
	IPMonitoringTableMinuteCol    = IPMonitoringTable.Col(IPMonitoringTableMinuteColName)
	IPMonitoringTableTenminuteCol = IPMonitoringTable.Col(IPMonitoringTableTenminuteColName)
	IPMonitoringTableHourCol      = IPMonitoringTable.Col(IPMonitoringTableHourColName)

	IPWhitelistTable               = goqu.T(IPWhitelistTableName)
	IPWhitelistTableIPCol          = IPWhitelistTable.Col(IPWhitelistTableIPColName)
	IPWhitelistTableDescriptionCol = IPWhitelistTable.Col(IPWhitelistTableDescriptionColName)

	TransactionTable               = goqu.T(TransactionTableName)
	TransactionTableSumCol         = TransactionTable.Col(TransactionTableSumColName)
	TransactionTableCurrencyCol    = TransactionTable.Col(TransactionTableCurrencyColName)
	TransactionTableDateCol        = TransactionTable.Col(TransactionTableDateColName)
	TransactionTableContributorCol = TransactionTable.Col(TransactionTableContributorColName)
	TransactionTablePurposeCol     = TransactionTable.Col(TransactionTablePurposeColName)
	TransactionTableUserIDCol      = TransactionTable.Col(TransactionTableUserIDColName)

	UserUserPreferencesTable            = goqu.T(UserUserPreferencesTableName)
	UserUserPreferencesTableUserIDCol   = UserUserPreferencesTable.Col("user_id")
	UserUserPreferencesTableToUserIDCol = UserUserPreferencesTable.Col("to_user_id")
	UserUserPreferencesTableDCNCol      = UserUserPreferencesTable.Col(
		UserUserPreferencesTableDCNColName,
	)
)
