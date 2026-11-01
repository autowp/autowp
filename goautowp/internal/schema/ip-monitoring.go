package schema

import (
	"github.com/doug-martin/goqu/v9"
)

const (
	IPMonitoringTableName             = "ip_monitoring"
	IPMonitoringTableIPColName        = "ip"
	IPMonitoringTableCountColName     = "count"
	IPMonitoringTableDayDateColName   = "day_date"
	IPMonitoringTableMinuteColName    = "minute"
	IPMonitoringTableTenminuteColName = "tenminute"
	IPMonitoringTableHourColName      = "hour"
)

var (
	IPMonitoringTable             = goqu.T(IPMonitoringTableName)
	IPMonitoringTableIPCol        = IPMonitoringTable.Col(IPMonitoringTableIPColName)
	IPMonitoringTableCountCol     = IPMonitoringTable.Col(IPMonitoringTableCountColName)
	IPMonitoringTableDayDateCol   = IPMonitoringTable.Col(IPMonitoringTableDayDateColName)
	IPMonitoringTableMinuteCol    = IPMonitoringTable.Col(IPMonitoringTableMinuteColName)
	IPMonitoringTableTenminuteCol = IPMonitoringTable.Col(IPMonitoringTableTenminuteColName)
	IPMonitoringTableHourCol      = IPMonitoringTable.Col(IPMonitoringTableHourColName)
)
