package schema

import "github.com/doug-martin/goqu/v9"

type ForumsTopicStatus string

const (
	ForumsTopicStatusUnknown ForumsTopicStatus = ""
	ForumsTopicStatusNormal  ForumsTopicStatus = "normal"
	ForumsTopicStatusClosed  ForumsTopicStatus = "closed"
	ForumsTopicStatusDeleted ForumsTopicStatus = "deleted"
)

const (
	ForumsTopicsTableName             = "forums_topics"
	ForumsTopicsTableIDColName        = "id"
	ForumsTopicsTableStatusColName    = "status"
	ForumsTopicsTableThemeIDColName   = "theme_id"
	ForumsTopicsTableNameColName      = "name"
	ForumsTopicsTableCreatedAtColName = "created_at"
	ForumsTopicsTableAuthorIDColName  = "author_id"
	ForumsTopicsTableAuthorIPColName  = "author_ip"
	ForumsTopicsTableViewsColName     = "views"
	ForumsTopicsTableDeletedAtColName = "deleted_at"
)

var (
	ForumsTopicsTable             = goqu.T(ForumsTopicsTableName)
	ForumsTopicsTableIDCol        = ForumsTopicsTable.Col(ForumsTopicsTableIDColName)
	ForumsTopicsTableStatusCol    = ForumsTopicsTable.Col(ForumsTopicsTableStatusColName)
	ForumsTopicsTableThemeIDCol   = ForumsTopicsTable.Col(ForumsTopicsTableThemeIDColName)
	ForumsTopicsTableNameCol      = ForumsTopicsTable.Col(ForumsTopicsTableNameColName)
	ForumsTopicsTableCreatedAtCol = ForumsTopicsTable.Col(ForumsTopicsTableCreatedAtColName)
	ForumsTopicsTableAuthorIDCol  = ForumsTopicsTable.Col(ForumsTopicsTableAuthorIDColName)
	ForumsTopicsTableAuthorIPCol  = ForumsTopicsTable.Col(ForumsTopicsTableAuthorIPColName)
	ForumsTopicsTableViewsCol     = ForumsTopicsTable.Col(ForumsTopicsTableViewsColName)
	ForumsTopicsTableDeletedAtCol = ForumsTopicsTable.Col(ForumsTopicsTableDeletedAtColName)
)
