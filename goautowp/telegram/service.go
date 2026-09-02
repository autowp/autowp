package telegram

import (
	"context"
	"crypto/rand"
	"database/sql"
	"encoding/hex"
	"errors"
	"fmt"
	"net/http"
	"net/url"
	"regexp"
	"strconv"
	"strings"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	"github.com/gin-gonic/gin"
	tgbotapi "github.com/go-telegram-bot-api/telegram-bot-api/v5"
)

const (
	commandMe       = "me"
	commandStart    = "start"
	commandNew      = "new"
	commandMessages = "messages"
	commandInbox    = "inbox"

	commandMeDescription       = "Command to identify you as autowp.ru user"
	commandNewDescription      = "Subscribe to new pictures"
	commandStartDescription    = "Start Command to get you started"
	commandMessagesDescription = "Enable/disable personal messages"
	commandInboxDescription    = "Subscribe to inbox pictures"

	tokenLength = 20
)

var (
	errChatIDNotProvide         = errors.New("`chat_id` not provided")
	errUpdateMessageMissing     = errors.New("update.message is nil")
	errUpdateMessageChatMissing = errors.New("update.message.chat is nil")
)

var spacesRegExp = regexp.MustCompile(`[[:space:]]+`)

type Service struct {
	config              config.TelegramConfig
	db                  *goqu.Database
	hostsManager        *hosts.Manager
	botAPI              *tgbotapi.BotAPI
	userRepository      *users.Repository
	itemRepository      *items.Repository
	picturesRepository  *pictures.Repository
	messagingRepository *messaging.Repository
	mockModeEnabled     bool
}

func NewService(
	config config.TelegramConfig,
	db *goqu.Database,
	hostsManager *hosts.Manager,
	userRepository *users.Repository,
	itemRepository *items.Repository,
	messagingRepository *messaging.Repository,
	picturesRepository *pictures.Repository,
) *Service {
	return &Service{
		config:              config,
		db:                  db,
		hostsManager:        hostsManager,
		userRepository:      userRepository,
		itemRepository:      itemRepository,
		messagingRepository: messagingRepository,
		mockModeEnabled:     false,
		picturesRepository:  picturesRepository,
	}
}

func (s *Service) NotifyMessage(
	ctx context.Context,
	fromID int64,
	userID int64,
	text string,
) error {
	fromName := "New personal message"

	if fromID > 0 {
		success, err := s.db.Select(schema.UserTableNameCol).
			From(schema.UserTable).
			Where(schema.UserTableIDCol.Eq(fromID)).
			ScanValContext(ctx, &fromName)
		if err != nil {
			return err
		}

		if !success {
			return sql.ErrNoRows
		}
	}

	var chatIDs []int64

	err := s.db.Select(schema.TelegramChatTableChatIDCol).
		From(schema.TelegramChatTable).
		Where(
			schema.TelegramChatTableUserIDCol.Eq(userID),
			schema.TelegramChatTableMessagesCol.IsTrue(),
		).
		ScanValsContext(ctx, &chatIDs)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, chatID := range chatIDs {
		uri, err := s.getURIByChatID(ctx, chatID)
		if err != nil {
			return err
		}

		uri.Path = "/account/messages"

		if fromID <= 0 {
			q := uri.Query()
			q.Add("folder", "system")
			uri.RawQuery = q.Encode()
		}

		telegramMessage := fmt.Sprintf(
			"%s: \n%s\n\n%s",
			fromName,
			text,
			uri.String(),
		)

		err = s.sendMessage(ctx, telegramMessage, chatID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Service) NotifyPicture(
	ctx context.Context, picture *schema.PictureRow, itemRepository *items.Repository,
) error {
	itemIDsSelect, err := itemRepository.IDsSelect(query.ItemListOptions{
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{
				PictureID: picture.ID,
			},
		},
	})
	if err != nil {
		return err
	}

	var chatIDs []int64

	err = s.db.Select(schema.TelegramBrandTableChatIDCol).From(schema.TelegramBrandTable).Where(
		schema.TelegramBrandTableItemIDCol.In(itemIDsSelect),
		schema.TelegramBrandTableNewCol.IsTrue(),
		schema.TelegramBrandTableChatIDCol.NotIn(
			s.db.Select(schema.TelegramChatTableChatIDCol).
				From(schema.TelegramChatTable).
				Join(schema.PictureTable, goqu.On(schema.TelegramChatTableUserIDCol.Eq(schema.PictureTableOwnerIDCol))).
				Where(schema.PictureTableIDCol.Eq(picture.ID)),
		),
		schema.TelegramBrandTableChatIDCol.IsNotNull(),
	).ScanValsContext(ctx, &chatIDs)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, chatID := range chatIDs {
		uri, err := s.getURIByChatID(ctx, chatID)
		if err != nil {
			return err
		}

		err = s.sendMessage(ctx, frontend.PictureURL(uri, picture.Identity), chatID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Service) WebhookInfo() error {
	bot, err := s.getBotAPI()
	if err != nil {
		return err
	}

	wh, err := bot.GetWebhookInfo()
	if err != nil {
		return err
	}

	logging.Info("URL: " + wh.URL)
	logging.Info("LastErrorMessage: " + wh.LastErrorMessage)
	logging.Infof("LastErrorDate: %d", wh.LastErrorDate)
	logging.Info("IPAddress: " + wh.IPAddress)
	logging.Infof("AllowedUpdates: %v", wh.AllowedUpdates)
	logging.Infof("HasCustomCertificate: %v", wh.HasCustomCertificate)
	logging.Infof("PendingUpdateCount: %d", wh.PendingUpdateCount)
	logging.Infof("MaxConnections: %d", wh.MaxConnections)

	return nil
}

func (s *Service) RegisterWebhook() error {
	bot, err := s.getBotAPI()
	if err != nil {
		return err
	}

	wh, err := tgbotapi.NewWebhook(s.config.WebHook)
	if err != nil {
		return err
	}

	res, err := bot.Request(wh)
	if err != nil {
		return err
	}

	if res.Ok {
		logging.Infof("Webhook successfully registered: %s", res.Description)
	} else {
		logging.Errorf("Failed to register webhook: %d: %s", res.ErrorCode, res.Description)
	}

	return nil
}

func GenerateSecureToken(length int) string {
	b := make([]byte, length)
	if _, err := rand.Read(b); err != nil {
		return ""
	}

	return hex.EncodeToString(b)
}

func (s *Service) SetupRouter(router *gin.Engine) {
	router.POST("/telegram/webhook/token/:token", func(ctx *gin.Context) {
		if ctx.Param("token") != s.config.WebhookToken {
			ctx.Status(http.StatusForbidden)

			return
		}

		bot, err := s.getBotAPI()
		if err != nil {
			ctx.String(http.StatusInternalServerError, err.Error())

			return
		}

		update, err := bot.HandleUpdate(ctx.Request)
		if err != nil {
			ctx.String(http.StatusBadRequest, err.Error())

			return
		}

		if update.Message == nil { // ignore any non-Message updates
			ctx.String(http.StatusOK, "empty update")

			return
		}

		if !update.Message.IsCommand() { // ignore any non-command Messages
			ctx.String(http.StatusOK, "is not command")

			return
		}

		err = s.handleUpdate(ctx, update)
		if err != nil {
			logging.Errorf("telegram webhook error: %s", err.Error())
			ctx.String(http.StatusInternalServerError, err.Error())

			return
		}

		ctx.String(http.StatusOK, "success")
	})
}

func (s *Service) NotifyInbox(ctx context.Context, pictureID int64) error {
	if pictureID == 0 {
		return nil
	}

	brandIDs, err := s.itemRepository.IDs(ctx, query.ItemListOptions{
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{
				PictureID: pictureID,
			},
		},
	})
	if err != nil {
		return err
	}

	if len(brandIDs) > 0 {
		picture, err := s.picturesRepository.Picture(
			ctx,
			&query.PictureListOptions{ID: pictureID},
			nil,
			pictures.OrderByNone,
		)
		if err != nil {
			return err
		}

		var chatIDs []int64

		sqSelect := s.db.Select(schema.TelegramBrandTableChatIDCol).
			From(schema.TelegramBrandTable).
			Join(schema.TelegramChatTable, goqu.On(schema.TelegramBrandTableChatIDCol.Eq(schema.TelegramChatTableChatIDCol))).
			Join(schema.UserTable, goqu.On(schema.TelegramChatTableUserIDCol.Eq(schema.UserTableIDCol))).
			Where(
				schema.TelegramBrandTableItemIDCol.In(brandIDs),
				schema.TelegramBrandTableInboxCol.IsTrue(),
				schema.UserTableDeletedCol.IsFalse(),
			)
		if picture.OwnerID.Valid {
			sqSelect = sqSelect.Where(schema.UserTableIDCol.Neq(picture.OwnerID.Int64))
		}

		err = sqSelect.ScanValsContext(ctx, &chatIDs)
		if err != nil {
			return err
		}

		for _, chatID := range chatIDs {
			uri, err := s.getURIByChatID(ctx, chatID)
			if err != nil {
				return err
			}

			err = s.sendMessage(ctx, frontend.PictureURL(uri, picture.Identity), chatID)
			if err != nil {
				return err
			}
		}
	}

	return nil
}

func (s *Service) enableMockMode() {
	s.mockModeEnabled = true
}

func (s *Service) getBotAPI() (*tgbotapi.BotAPI, error) {
	if s.botAPI == nil {
		bot, err := tgbotapi.NewBotAPI(s.config.AccessToken)
		if err != nil {
			return nil, fmt.Errorf("NewBotAPI(): %w", err)
		}

		s.botAPI = bot
	}

	return s.botAPI, nil
}

func (s *Service) getURIByChatID(ctx context.Context, chatID int64) (*url.URL, error) {
	if chatID > 0 {
		language := ""

		success, err := s.db.Select(schema.UserTableLanguageCol).
			From(schema.UserTable).
			Join(schema.TelegramChatTable, goqu.On(schema.UserTableIDCol.Eq(schema.TelegramChatTableUserIDCol))).
			Where(schema.TelegramChatTableChatIDCol.Eq(chatID)).
			ScanValContext(ctx, &language)
		if err != nil {
			return nil, err
		}

		if success && len(language) > 0 {
			return s.hostsManager.URIByLanguage(language)
		}
	}

	return url.Parse("https://wheelsage.org")
}

func (s *Service) sendMessage(ctx context.Context, text string, chat int64) error {
	bot, err := s.getBotAPI()
	if err != nil {
		return err
	}

	if chat <= 0 {
		return errChatIDNotProvide
	}

	mc := tgbotapi.NewMessage(chat, text)

	_, err = bot.Send(mc)
	if err != nil {
		if strings.Contains(err.Error(), "deactivated") {
			return s.unsubscribeChat(ctx, chat)
		}

		if strings.Contains(err.Error(), "blocked") {
			return s.unsubscribeChat(ctx, chat)
		}
	}

	return err
}

func (s *Service) unsubscribeChat(ctx context.Context, chatID int64) error {
	_, err := s.db.Delete(schema.TelegramBrandTable).
		Where(schema.TelegramBrandTableChatIDCol.Eq(chatID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	_, err = s.db.Delete(schema.TelegramChatTable).
		Where(schema.TelegramChatTableChatIDCol.Eq(chatID)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Service) replyWithMessage(update *tgbotapi.Update, text string) error {
	if s.mockModeEnabled {
		logging.Debugf("Mock reply: `%s`", text)

		return nil
	}

	bot, err := s.getBotAPI()
	if err != nil {
		return err
	}

	_, err = bot.Send(tgbotapi.MessageConfig{
		BaseChat: tgbotapi.BaseChat{
			ChatID: update.Message.Chat.ID,
		},
		Text:                  text,
		DisableWebPagePreview: false,
	})

	return err
}

func (s *Service) handleInboxCommand(ctx context.Context, update *tgbotapi.Update) error {
	if update.Message == nil {
		return errUpdateMessageMissing
	}

	if update.Message.Chat == nil {
		return errUpdateMessageChatMissing
	}

	chatID := update.Message.Chat.ID
	args := strings.TrimSpace(update.Message.CommandArguments())
	cmd := update.Message.Command()

	var exists bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.TelegramChatTable).
		Join(schema.UserTable, goqu.On(schema.TelegramChatTableUserIDCol.Eq(schema.UserTableIDCol))).
		Where(
			schema.UserTableDeletedCol.IsFalse(),
			schema.TelegramChatTableChatIDCol.Eq(chatID),
		).ScanValContext(ctx, &exists)
	if err != nil {
		return err
	}

	if !success || !exists {
		return s.replyWithMessage(
			update,
			fmt.Sprintf(
				"You need to identify your account with /%s command to use that service",
				commandMe,
			),
		)
	}

	if len(args) == 0 {
		return s.replyWithMessage(
			update,
			fmt.Sprintf("Please, type brand name. For Example /%s BMW", cmd),
		)
	}

	brandRow, err := s.itemRepository.Item(ctx, &query.ItemListOptions{
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		Name:   args,
	}, &items.ItemFields{NameOnly: true})
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return s.replyWithMessage(update, fmt.Sprintf(`Brand "%s" not found`, args))
		}

		return err
	}

	var telegramBrandRow schema.TelegramBrandRow

	success, err = s.db.Select(schema.TelegramBrandTableInboxCol).
		From(schema.TelegramBrandTable).
		Where(
			schema.TelegramBrandTableItemIDCol.Eq(brandRow.ID),
			schema.TelegramBrandTableChatIDCol.Eq(chatID),
		).
		ScanStructContext(ctx, &telegramBrandRow)
	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	if success && telegramBrandRow.Inbox {
		_, err = s.db.Update(schema.TelegramBrandTable).Set(goqu.Record{
			schema.TelegramBrandTableInboxColName: false,
		}).Where(
			schema.TelegramBrandTableItemIDCol.Eq(brandRow.ID),
			schema.TelegramBrandTableChatIDCol.Eq(chatID),
		).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		return s.replyWithMessage(
			update,
			fmt.Sprintf("Successful unsubscribed from `%s`", brandRow.NameOnly),
		)
	}

	_, err = s.db.Insert(schema.TelegramBrandTable).
		Rows(goqu.Record{
			schema.TelegramBrandTableInboxColName:  true,
			schema.TelegramBrandTableItemIDColName: brandRow.ID,
			schema.TelegramBrandTableChatIDColName: chatID,
		}).
		OnConflict(goqu.DoUpdate(
			schema.TelegramBrandTableItemIDColName+","+schema.TelegramBrandTableChatIDColName,
			goqu.Record{
				schema.TelegramBrandTableInboxColName: schema.Excluded(schema.TelegramBrandTableInboxColName),
			},
		)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return s.replyWithMessage(
		update,
		fmt.Sprintf("Successful subscribed to `%s`", brandRow.NameOnly),
	)
}

func (s *Service) handleNewCommand(ctx context.Context, update *tgbotapi.Update) error {
	if update.Message == nil {
		return errUpdateMessageMissing
	}

	if update.Message.Chat == nil {
		return errUpdateMessageChatMissing
	}

	args := strings.TrimSpace(update.Message.CommandArguments())
	cmd := update.Message.Command()
	chatID := update.Message.Chat.ID

	if len(args) == 0 {
		return s.replyWithMessage(
			update,
			fmt.Sprintf("Please, type brand name. For Example /%s BMW", cmd),
		)
	}

	brandRow, err := s.itemRepository.Item(ctx, &query.ItemListOptions{
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		Name:   args,
	}, &items.ItemFields{NameOnly: true})
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return s.replyWithMessage(update, fmt.Sprintf(`Brand "%s" not found`, args))
		}

		return err
	}

	var telegramBrandRow schema.TelegramBrandRow

	success, err := s.db.Select(schema.TelegramBrandTableNewCol).
		From(schema.TelegramBrandTable).
		Where(
			schema.TelegramBrandTableItemIDCol.Eq(brandRow.ID),
			schema.TelegramBrandTableChatIDCol.Eq(chatID),
		).
		ScanStructContext(ctx, &telegramBrandRow)
	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	if success && telegramBrandRow.New {
		_, err = s.db.Update(schema.TelegramBrandTable).Set(goqu.Record{
			schema.TelegramBrandTableNewColName: false,
		}).Where(
			schema.TelegramBrandTableItemIDCol.Eq(brandRow.ID),
			schema.TelegramBrandTableChatIDCol.Eq(chatID),
		).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		return s.replyWithMessage(
			update,
			fmt.Sprintf("Successful unsubscribed from `%s`", brandRow.NameOnly),
		)
	}

	_, err = s.db.Insert(schema.TelegramBrandTable).
		Rows(goqu.Record{
			schema.TelegramBrandTableNewColName:    true,
			schema.TelegramBrandTableItemIDColName: brandRow.ID,
			schema.TelegramBrandTableChatIDColName: chatID,
		}).
		OnConflict(goqu.DoUpdate(
			schema.TelegramBrandTableItemIDColName+","+schema.TelegramBrandTableChatIDColName,
			goqu.Record{
				schema.TelegramBrandTableNewColName: schema.Excluded(schema.TelegramBrandTableNewColName),
			},
		)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return s.replyWithMessage(
		update,
		fmt.Sprintf("Successful subscribed to `%s`", brandRow.NameOnly),
	)
}

func (s *Service) handleMessagesCommand(ctx context.Context, update *tgbotapi.Update) error {
	if update.Message == nil {
		return errUpdateMessageMissing
	}

	if update.Message.Chat == nil {
		return errUpdateMessageChatMissing
	}

	chatID := update.Message.Chat.ID

	var exists bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.TelegramChatTable).
		Join(schema.UserTable, goqu.On(schema.TelegramChatTableUserIDCol.Eq(schema.UserTableIDCol))).
		Where(
			schema.UserTableDeletedCol.IsFalse(),
			schema.TelegramChatTableChatIDCol.Eq(chatID),
		).ScanValContext(ctx, &exists)
	if err != nil {
		return err
	}

	if !success || !exists {
		return s.replyWithMessage(
			update,
			fmt.Sprintf(
				"You need to identify your account with /%s command to use that service",
				commandMe,
			),
		)
	}

	value := update.Message.CommandArguments() == "on"
	ctx = context.WithoutCancel(ctx)

	_, err = s.db.Update(schema.TelegramChatTable).
		Set(goqu.Record{
			schema.TelegramChatTableMessagesColName: value,
		}).
		Where(schema.TelegramChatTableChatIDCol.Eq(chatID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	cmd := update.Message.Command()

	if value {
		return s.replyWithMessage(
			update,
			fmt.Sprintf(
				"Subscription to new personal messages is enabled. Send `/%s off` to disable",
				cmd,
			),
		)
	}

	return s.replyWithMessage(
		update,
		fmt.Sprintf(
			"Subscription to new personal messages is disabled. Send `/%s on` to enable",
			cmd,
		),
	)
}

func (s *Service) handleStartCommand(update *tgbotapi.Update) error {
	return s.replyWithMessage(
		update,
		"Hello! Welcome to our bot, Here are our available commands:"+
			fmt.Sprintf("/%s - %s\n", commandStart, commandStartDescription)+
			fmt.Sprintf("/%s - %s\n", commandMe, commandMeDescription)+
			fmt.Sprintf("/%s - %s\n", commandMessages, commandMessagesDescription)+
			fmt.Sprintf("/%s - %s\n", commandNew, commandNewDescription)+
			fmt.Sprintf("/%s - %s\n", commandInbox, commandInboxDescription),
	)
}

func (s *Service) handleMeCommand(ctx context.Context, update *tgbotapi.Update) error {
	if update.Message == nil {
		return errUpdateMessageMissing
	}

	if update.Message.Chat == nil {
		return errUpdateMessageChatMissing
	}

	args := spacesRegExp.Split(strings.TrimSpace(update.Message.CommandArguments()), -1)
	if args[0] == "" {
		args = []string{}
	}

	cmd := update.Message.Command()
	chatID := update.Message.Chat.ID

	var telegramChatRow schema.TelegramChatRow

	telegramChatRowFound, err := s.db.Select(
		schema.TelegramChatTableChatIDCol,
		schema.TelegramChatTableUserIDCol,
		schema.TelegramChatTableTokenCol,
	).
		From(schema.TelegramChatTable).
		Where(schema.TelegramChatTableChatIDCol.Eq(chatID)).
		ScanStructContext(ctx, &telegramChatRow)
	if err != nil {
		return err
	}

	if len(args) == 0 {
		if !telegramChatRowFound || !telegramChatRow.UserID.Valid {
			return s.replyWithMessage(update, fmt.Sprintf(
				`Use this command to identify you as autowp.ru user.\n`+
					`For example type "/%s 12345" to identify you as user number 12345`,
				cmd,
			))
		}

		if telegramChatRow.UserID.Int64 > 0 {
			userRow, err := s.userRepository.User(
				ctx,
				&query.UserListOptions{ID: telegramChatRow.UserID.Int64},
				users.UserFields{},
				users.OrderByNone,
			)
			if err != nil {
				return err
			}

			return s.replyWithMessage(update, "You identified as "+userRow.Name)
		}

		return nil
	}

	userID, err := strconv.ParseInt(args[0], 10, 64)
	if err != nil {
		return s.replyWithMessage(update, err.Error())
	}

	userRow, err := s.userRepository.User(
		ctx,
		&query.UserListOptions{ID: userID},
		users.UserFields{},
		users.OrderByNone,
	)
	if err != nil {
		if errors.Is(err, users.ErrUserNotFound) {
			return s.replyWithMessage(update, fmt.Sprintf(`User "%s" not found`, args[0]))
		}

		return err
	}

	if len(args) == 1 {
		token := GenerateSecureToken(tokenLength)
		ctx = context.WithoutCancel(ctx)

		_, err = s.db.Insert(schema.TelegramChatTable).
			Rows(goqu.Record{
				schema.TelegramChatTableTokenColName:  token,
				schema.TelegramChatTableChatIDColName: chatID,
			}).
			OnConflict(goqu.DoUpdate(
				schema.TelegramChatTableChatIDColName,
				goqu.Record{
					schema.TelegramChatTableTokenColName: schema.Excluded(schema.TelegramChatTableTokenColName),
				},
			)).
			Executor().ExecContext(ctx)
		if err != nil {
			return err
		}

		err = s.messagingRepository.CreateMessage(
			ctx,
			0,
			userRow.ID,
			fmt.Sprintf(
				"To complete identifications type `/%s %d %s` to @autowp_bot",
				cmd,
				userRow.ID,
				token,
			),
		)
		if err != nil {
			return err
		}

		return s.replyWithMessage(update, "Check your personal messages / system notifications")
	}

	token := args[1]

	if !telegramChatRowFound || !telegramChatRow.Token.Valid ||
		!strings.EqualFold(telegramChatRow.Token.String, token) {
		return s.replyWithMessage(
			update,
			fmt.Sprintf("Token not matched. Try again with `/%s %d`", cmd, userRow.ID),
		)
	}

	ctx = context.WithoutCancel(ctx)

	_, err = s.db.Update(schema.TelegramChatTable).
		Set(goqu.Record{
			schema.TelegramChatTableTokenColName:  nil,
			schema.TelegramChatTableUserIDColName: userRow.ID,
		}).
		Where(schema.TelegramChatTableChatIDCol.Eq(chatID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return s.replyWithMessage(update, fmt.Sprintf("Complete. Nice to see you, `%s`", userRow.Name))
}

func (s *Service) handleUpdate(ctx context.Context, update *tgbotapi.Update) error {
	switch update.Message.Command() {
	case commandMe:
		return s.handleMeCommand(ctx, update)
	case commandStart:
		return s.handleStartCommand(update)
	case commandMessages:
		return s.handleMessagesCommand(ctx, update)
	case commandNew:
		return s.handleNewCommand(ctx, update)
	case commandInbox:
		return s.handleInboxCommand(ctx, update)
	}

	return nil
}
