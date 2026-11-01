package schema

import (
	"database/sql"
	"time"

	"github.com/doug-martin/goqu/v9"
)

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

type ArticleRow struct {
	ID              int64          `db:"id"               goqu:"pk,skipinsert"`
	Name            string         `db:"name"`
	AuthorID        sql.NullInt64  `db:"author_id"`
	Catname         string         `db:"catname"`
	CreatedAt       time.Time      `db:"created_at"`
	PreviewFilename sql.NullString `db:"preview_filename"`
	Description     string         `db:"description"`
	HTML            sql.NullString `db:"html"`
}
