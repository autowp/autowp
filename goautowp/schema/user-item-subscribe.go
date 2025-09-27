package schema

import "github.com/doug-martin/goqu/v9"

const (
	UserItemSubscribeTableName          = "user_item_subscribe"
	UserItemSubscribeTableUserIDColName = "user_id"
	UserItemSubscribeTableItemIDColName = "item_id"
)

var (
	UserItemSubscribeTable          = goqu.T(UserItemSubscribeTableName)
	UserItemSubscribeTableUserIDCol = UserItemSubscribeTable.Col(
		UserItemSubscribeTableUserIDColName,
	)
	UserItemSubscribeTableItemIDCol = UserItemSubscribeTable.Col(
		UserItemSubscribeTableItemIDColName,
	)
)
