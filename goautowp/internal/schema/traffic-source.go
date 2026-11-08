package schema

import "github.com/doug-martin/goqu/v9"

const (
	TrafficSourceTableName               = "traffic_source"
	TrafficSourceTableIDColName          = "id"
	TrafficSourceTableNameColName        = "name"
	TrafficSourceTableNetworksColName    = "networks"
	TrafficSourceTableDNSSuffixesColName = "dns_suffixes"
	TrafficSourceTableReferencesColName  = "reference_urls"
	IPSourceTableName                    = "ip_source"
	IPSourceTableIPColName               = "ip"
	IPSourceTableSourceIDColName         = "source_id"
)

var (
	TrafficSourceTable       = goqu.T(TrafficSourceTableName)
	TrafficSourceTableIDCol  = TrafficSourceTable.Col(TrafficSourceTableIDColName)
	IPSourceTable            = goqu.T(IPSourceTableName)
	IPSourceTableIPCol       = IPSourceTable.Col(IPSourceTableIPColName)
	IPSourceTableSourceIDCol = IPSourceTable.Col(IPSourceTableSourceIDColName)
)
