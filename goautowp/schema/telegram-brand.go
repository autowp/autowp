package schema

import "github.com/doug-martin/goqu/v9"

const (
	TelegramBrandTableName = "telegram_brand"

	TelegramBrandTableChatIDColName = "chat_id"
	TelegramBrandTableItemIDColName = "item_id"
	TelegramBrandTableNewColName    = "new"
	TelegramBrandTableInboxColName  = "inbox"
)

var (
	TelegramBrandTable          = goqu.T(TelegramBrandTableName)
	TelegramBrandTableChatIDCol = TelegramBrandTable.Col(TelegramBrandTableChatIDColName)
	TelegramBrandTableItemIDCol = TelegramBrandTable.Col(TelegramBrandTableItemIDColName)
	TelegramBrandTableNewCol    = TelegramBrandTable.Col(TelegramBrandTableNewColName)
	TelegramBrandTableInboxCol  = TelegramBrandTable.Col(TelegramBrandTableInboxColName)
)

type TelegramBrandRow struct {
	New   bool `db:"new"`
	Inbox bool `db:"inbox"`
}
