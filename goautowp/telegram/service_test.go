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
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	tgbotapi "github.com/go-telegram-bot-api/telegram-bot-api/v5"
	_ "github.com/golang-migrate/migrate/v4/database/postgres" // enable postgres migrations
	"github.com/google/uuid"
	"github.com/sirupsen/logrus"
	"github.com/stretchr/testify/require"
)

func TestInboxCommand(t *testing.T) {
	t.Parallel()

	logrus.SetLevel(logrus.DebugLevel)

	cfg := config.LoadConfig("../")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquDB := goqu.New("postgres", db)
	ctx := t.Context()

	client := gocloak.NewClient(cfg.Keycloak.URL)

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	usersRepo := users.NewRepository(
		goquDB,
		"",
		cfg.Languages,
		client,
		cfg.Keycloak,
		cfg.MessageInterval,
		imageStorage,
	)
	textStorageRepo := textstorage.New(goquDB)
	itemParentLanguageRepository := items.NewItemParentLanguageRepository(goquDB, cfg.ContentLanguages)
	itemRepo := items.NewRepository(
		goquDB,
		cfg.MostsMinCarsCount,
		itemParentLanguageRepository,
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
	picturesRepo := pictures.NewRepository(
		goquDB,
		imageStorage,
		textStorageRepo,
		itemRepo,
		cfg.DuplicateFinder,
		func(int64) error { return nil },
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

	var id int64

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	emailAddr := "test" + strconv.Itoa(random.Int()) + "@example.com"
	name := "ivan"
	success, err := db.Insert(schema.UserTable).
		Rows(goqu.Record{
			schema.UserTableLoginColName:          nil,
			schema.UserTableEmailColName:          emailAddr,
			schema.UserTablePasswordColName:       nil,
			schema.UserTableEmailToCheckColName:   nil,
			schema.UserTableHideEmailColName:      true,
			schema.UserTableEmailCheckCodeColName: nil,
			schema.UserTableNameColName:           name,
			schema.UserTableRegDateColName:        goqu.Func("NOW"),
			schema.UserTableLastOnlineColName:     goqu.Func("NOW"),
			schema.UserTableTimezoneColName:       "Europe/Moscow",
			schema.UserTableLastIPColName:         goqu.Func("INET", "127.0.0.1"),
			schema.UserTableLanguageColName:       schema.EnglishLanguageCode,
			schema.UserTableUUIDColName:           uuid.New().String(),
		}).
		Returning(schema.UserTableIDCol).
		Executor().ScanValContext(ctx, &id)
	require.NoError(t, err)
	require.True(t, success)

	return id
}
