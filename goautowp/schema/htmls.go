package schema

import "github.com/doug-martin/goqu/v9"

const (
	HtmlsTableName = "htmls"
)

var (
	HtmlsTable        = goqu.T(HtmlsTableName)
	HtmlsTableIDCol   = HtmlsTable.Col("id")
	HtmlsTableHTMLCol = HtmlsTable.Col("html")
)
