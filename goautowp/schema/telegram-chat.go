package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	TelegramChatTableName = "telegram_chat"

	TelegramChatTableChatIDColName   = "chat_id"
	TelegramChatTableUserIDColName   = "user_id"
	TelegramChatTableTokenColName    = "token"
	TelegramChatTableMessagesColName = "messages"
)

var (
	TelegramChatTable            = goqu.T(TelegramChatTableName)
	TelegramChatTableChatIDCol   = TelegramChatTable.Col(TelegramChatTableChatIDColName)
	TelegramChatTableUserIDCol   = TelegramChatTable.Col(TelegramChatTableUserIDColName)
	TelegramChatTableTokenCol    = TelegramChatTable.Col(TelegramChatTableTokenColName)
	TelegramChatTableMessagesCol = TelegramChatTable.Col(TelegramChatTableMessagesColName)
)

type TelegramChatRow struct {
	ChatID int64          `db:"chat_id"`
	UserID sql.NullInt64  `db:"user_id"`
	Token  sql.NullString `db:"token"`
}
