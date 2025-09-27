package schema

import "github.com/doug-martin/goqu/v9"

const (
	ArticleTableName = "article"
)

var (
	ArticleTable                   = goqu.T(ArticleTableName)
	ArticleTableIDCol              = ArticleTable.Col("id")
	ArticleTableNameCol            = ArticleTable.Col("name")
	ArticleTableCatnameCol         = ArticleTable.Col("catname")
	ArticleTableAuthorIDCol        = ArticleTable.Col("author_id")
	ArticleTableEnabledCol         = ArticleTable.Col("enabled")
	ArticleTableAddDateCol         = ArticleTable.Col("add_date")
	ArticleTablePreviewFilenameCol = ArticleTable.Col("preview_filename")
	ArticleTableDescriptionCol     = ArticleTable.Col("description")
	ArticleTableHTMLIDCol          = ArticleTable.Col("html_id")
)
