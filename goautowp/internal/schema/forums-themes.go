package schema

import "github.com/doug-martin/goqu/v9"

const (
	ForumsThemesTableName            = "forums_themes"
	ForumsThemesTableTopicsColName   = "topics"
	ForumsThemesTableMessagesColName = "messages"
)

var (
	ForumsThemesTable                 = goqu.T(ForumsThemesTableName)
	ForumsThemesTableIDCol            = ForumsThemesTable.Col("id")
	ForumsThemesTableNameCol          = ForumsThemesTable.Col("name")
	ForumsThemesTableTopicsCol        = ForumsThemesTable.Col(ForumsThemesTableTopicsColName)
	ForumsThemesTableMessagesCol      = ForumsThemesTable.Col(ForumsThemesTableMessagesColName)
	ForumsThemesTableDisableTopicsCol = ForumsThemesTable.Col("disable_topics")
	ForumsThemesTableDescriptionCol   = ForumsThemesTable.Col("description")
	ForumsThemesTableIsModeratorCol   = ForumsThemesTable.Col("is_moderator")
	ForumsThemesTablePositionCol      = ForumsThemesTable.Col("position")
	ForumsThemesTableParentIDCol      = ForumsThemesTable.Col("parent_id")
)

type ForumsThemeRow struct {
	ID            int64  `db:"id"             goqu:"pk,skipinsert"`
	Name          string `db:"name"`
	TopicsCount   int32  `db:"topics"`
	MessagesCount int32  `db:"messages"`
	DisableTopics bool   `db:"disable_topics"`
	Description   string `db:"description"`
}
