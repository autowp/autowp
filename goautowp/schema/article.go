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
	ArticleTableCreatedAtCol       = ArticleTable.Col("created_at")
	ArticleTablePreviewFilenameCol = ArticleTable.Col("preview_filename")
	ArticleTableDescriptionCol     = ArticleTable.Col("description")
	ArticleTableHTMLCol            = ArticleTable.Col("html")
)
