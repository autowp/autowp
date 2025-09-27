package schema

import "github.com/doug-martin/goqu/v9"

const (
	ForumsThemeParentTableName = "forums_theme_parent"
)

var (
	ForumsThemeParentTable                = goqu.T(ForumsThemeParentTableName)
	ForumsThemeParentTableParentIDCol     = ForumsThemeParentTable.Col("parent_id")
	ForumsThemeParentTableForumThemeIDCol = ForumsThemeParentTable.Col("forum_theme_id")
)
