package telegram

import (
	"context"
	"database/sql"
	"fmt"
	"math/rand"
	"strconv"
	"testing"
	"time"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/mysql"    // enable mysql dialect
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	tgbotapi "github.com/go-telegram-bot-api/telegram-bot-api/v5"
	_ "github.com/golang-migrate/migrate/v4/database/mysql"    // enable mysql migrations
	_ "github.com/golang-migrate/migrate/v4/database/postgres" // enable postgres migrations
	"github.com/google/uuid"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/require"
)

func TestInboxCommand(t *testing.T) {
	t.Parallel()

	logrus.SetLevel(logrus.DebugLevel)

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	postgresDB, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquPostgresDB := goqu.New("postgres", postgresDB)
	client := gocloak.NewClient(cfg.Keycloak.URL)

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	usersRepo := users.NewRepository(
		goquDB,
		goquPostgresDB,
		"",
		cfg.Languages,
		client,
		cfg.Keycloak,
		cfg.MessageInterval,
		imageStorage,
	)
	textStorageRepo := textstorage.New(goquDB)
	itemRepo := items.NewRepository(
		goquDB,
		cfg.MostsMinCarsCount,
		cfg.ContentLanguages,
		textStorageRepo,
		imageStorage,
	)
	i18n, err := i18nbundle.New()
	require.NoError(t, err)

	messagingRepo := messaging.NewRepository(
		goquDB,
		func(_ context.Context, _ int64, _ int64, _ string) error {
			return nil
		},
		i18n,
	)
	hostManager := hosts.NewManager(cfg.Languages)
	commentsRepo := comments.NewRepository(goquDB, usersRepo, messagingRepo, hostManager)
	picturesRepo := pictures.NewRepository(
		goquDB,
		imageStorage,
		textStorageRepo,
		itemRepo,
		cfg.DuplicateFinder,
		commentsRepo,
	)

	userID := createRandomUser(ctx, t, goquDB)

	repository := NewService(
		cfg.Telegram,
		goquDB,
		hosts.NewManager(cfg.Languages),
		usersRepo,
		itemRepo,
		messagingRepo,
		picturesRepo,
	)
	repository.enableMockMode()

	err = repository.handleMeCommand(ctx, &tgbotapi.Update{
		Message: &tgbotapi.Message{
			Chat:     &tgbotapi.Chat{},
			Text:     fmt.Sprintf("/%s %d", commandMe, userID),
			Entities: []tgbotapi.MessageEntity{{Type: "bot_command", Length: len(commandMe)}},
		},
	})
	require.NoError(t, err)

	err = repository.handleInboxCommand(ctx, &tgbotapi.Update{
		Message: &tgbotapi.Message{
			Chat: &tgbotapi.Chat{},
		},
	})
	require.NoError(t, err)
}

func createRandomUser(ctx context.Context, t *testing.T, db *goqu.Database) int64 {
	t.Helper()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	emailAddr := "test" + strconv.Itoa(random.Int()) + "@example.com"
	name := "ivan"
	res, err := db.Insert(schema.UserTable).
		Rows(goqu.Record{
			schema.UserTableLoginColName:          nil,
			schema.UserTableEmailColName:          emailAddr,
			schema.UserTablePasswordColName:       nil,
			schema.UserTableEmailToCheckColName:   nil,
			schema.UserTableHideEmailColName:      1,
			schema.UserTableEmailCheckCodeColName: nil,
			schema.UserTableNameColName:           name,
			schema.UserTableRegDateColName:        goqu.Func("NOW"),
			schema.UserTableLastOnlineColName:     goqu.Func("NOW"),
			schema.UserTableTimezoneColName:       "Europe/Moscow",
			schema.UserTableLastIPColName:         goqu.Func("INET6_ATON", "127.0.0.1"),
			schema.UserTableLanguageColName:       "en",
			schema.UserTableUUIDColName:           goqu.Func("UUID_TO_BIN", uuid.New().String()),
		}).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	id, err := res.LastInsertId()
	require.NoError(t, err)

	return id
}
