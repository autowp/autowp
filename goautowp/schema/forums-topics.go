package schema

import "github.com/doug-martin/goqu/v9"

const (
	ForumsTopicsTableName               = "forums_topics"
	ForumsTopicsTableIDColName          = "id"
	ForumsTopicsTableStatusColName      = "status"
	ForumsTopicsTableThemeIDColName     = "theme_id"
	ForumsTopicsTableNameColName        = "name"
	ForumsTopicsTableAddDatetimeColName = "add_datetime"
	ForumsTopicsTableAuthorIDColName    = "author_id"
	ForumsTopicsTableAuthorIPColName    = "author_ip"
	ForumsTopicsTableViewsColName       = "views"
)

var (
	ForumsTopicsTable               = goqu.T(ForumsTopicsTableName)
	ForumsTopicsTableIDCol          = ForumsTopicsTable.Col(ForumsTopicsTableIDColName)
	ForumsTopicsTableStatusCol      = ForumsTopicsTable.Col(ForumsTopicsTableStatusColName)
	ForumsTopicsTableThemeIDCol     = ForumsTopicsTable.Col(ForumsTopicsTableThemeIDColName)
	ForumsTopicsTableNameCol        = ForumsTopicsTable.Col(ForumsTopicsTableNameColName)
	ForumsTopicsTableAddDatetimeCol = ForumsTopicsTable.Col(ForumsTopicsTableAddDatetimeColName)
	ForumsTopicsTableAuthorIDCol    = ForumsTopicsTable.Col(ForumsTopicsTableAuthorIDColName)
	ForumsTopicsTableAuthorIPCol    = ForumsTopicsTable.Col(ForumsTopicsTableAuthorIPColName)
	ForumsTopicsTableViewsCol       = ForumsTopicsTable.Col(ForumsTopicsTableViewsColName)
)
