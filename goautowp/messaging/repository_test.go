package messaging

import (
	"context"
	"database/sql"
	"math/rand"
	"strconv"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/mysql" // enable mysql dialect
	_ "github.com/go-sql-driver/mysql"               // enable mysql driver
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
)

func createRepository(t *testing.T) *Repository {
	t.Helper()

	cfg := config.LoadConfig("..")

	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)

	i18n, err := i18nbundle.New()
	require.NoError(t, err)

	s := NewRepository(goquDB, func(_ context.Context, _ int64, _ int64, _ string) error {
		return nil
	}, i18n)

	return s
}

func TestGetUserNewMessagesCount(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.GetUserNewMessagesCount(t.Context(), 1)
	require.NoError(t, err)
}

func TestGetInboxCount(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.GetInboxCount(t.Context(), 1)
	require.NoError(t, err)
}

func TestGetInboxNewCount(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.GetInboxNewCount(t.Context(), 1)
	require.NoError(t, err)
}

func TestGetSentCount(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.GetSentCount(t.Context(), 1)
	require.NoError(t, err)
}

func TestGetSystemCount(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.GetSystemCount(t.Context(), 1)
	require.NoError(t, err)
}

func TestGetSystemNewCount(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.GetSystemNewCount(t.Context(), 1)
	require.NoError(t, err)
}

func TestGetDialogCount(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.GetDialogCount(t.Context(), 1, 2)
	require.NoError(t, err)
}

func TestDeleteMessage(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	err := s.DeleteMessage(t.Context(), 1, 1)
	require.NoError(t, err)
}

func TestClearSent(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	err := s.ClearSent(t.Context(), 1)
	require.NoError(t, err)
}

func TestClearSystem(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	err := s.ClearSystem(t.Context(), 1)
	require.NoError(t, err)
}

func TestGetInbox(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, _, err := s.GetInbox(t.Context(), 1, 1)
	require.NoError(t, err)
}

func TestGetSentbox(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, _, err := s.GetSentbox(t.Context(), 1, 1)
	require.NoError(t, err)
}

func TestGetSystembox(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, _, err := s.GetSystembox(t.Context(), 1, 1)
	require.NoError(t, err)
}

func TestGetDialogbox(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, _, err := s.GetDialogbox(t.Context(), 1, 2, 1)
	require.NoError(t, err)
}

func createRandomUser(t *testing.T, s *Repository) int64 {
	t.Helper()

	emailAddr := "test" + strconv.Itoa(rand.Int()) + "@example.com" //nolint: gosec
	name := "ivan"
	res, err := s.db.Insert(schema.UserTable).
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
		Executor().ExecContext(t.Context())
	require.NoError(t, err)

	id, err := res.LastInsertId()
	require.NoError(t, err)

	return id
}

func TestDialogCount(t *testing.T) { //nolint:paralleltest
	repo := createRepository(t)
	ctx := t.Context()

	user1 := createRandomUser(t, repo)
	user2 := createRandomUser(t, repo)

	countBefore, err := repo.GetDialogCount(ctx, user1, user2)
	require.NoError(t, err)

	err = repo.CreateMessage(ctx, user1, user2, "Test message")
	require.NoError(t, err)

	countAfter, err := repo.GetDialogCount(ctx, user1, user2)
	require.NoError(t, err)

	require.Greater(t, countAfter, countBefore)

	messages, _, err := repo.GetSentbox(ctx, user1, 1)
	require.NoError(t, err)
	require.Equal(t, countAfter, messages[0].DialogCount)
}
