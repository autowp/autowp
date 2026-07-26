package schema

import "github.com/doug-martin/goqu/v9"

const (
	IPWhitelistTableName               = "ip_whitelist"
	IPWhitelistTableIPColName          = "ip"
	IPWhitelistTableDescriptionColName = "description"
)

var (
	IPWhitelistTable               = goqu.T(IPWhitelistTableName)
	IPWhitelistTableIPCol          = IPWhitelistTable.Col(IPWhitelistTableIPColName)
	IPWhitelistTableDescriptionCol = IPWhitelistTable.Col(IPWhitelistTableDescriptionColName)
)
