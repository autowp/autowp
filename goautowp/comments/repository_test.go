package comments

import (
	"context"
	"database/sql"
	"math/rand"
	"strconv"
	"testing"
	"time"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	"github.com/google/uuid"
	_ "github.com/lib/pq" // enable postgres driver
	"github.com/stretchr/testify/require"
)

func createRandomUser(ctx context.Context, t *testing.T, db *goqu.Database) int64 {
	t.Helper()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	emailAddr := "test" + strconv.Itoa(random.Int()) + "@example.com"
	name := "ivan"

	var id int64

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

func createRepositoryWithCallback(
	t *testing.T, afterCommentAdded func(ctx context.Context, authorID int64) error,
) (*Repository, *goqu.Database) {
	t.Helper()

	cfg := config.LoadConfig("..")

	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquDB := goqu.New("postgres", db)

	client := gocloak.NewClient(cfg.Keycloak.URL)

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	usersRepository := users.NewRepository(
		goquDB,
		cfg.UsersSalt,
		cfg.Languages,
		client,
		cfg.Keycloak,
		cfg.MessageInterval,
		imageStorage,
	)

	hostsManager := hosts.NewManager(cfg.Languages)

	i, err := i18nbundle.New()
	require.NoError(t, err)

	messagingRepository := messaging.NewRepository(
		goquDB,
		func(_ context.Context, _ int64, _ int64, _ string) error {
			return nil
		},
		func(_ context.Context, _ []int64) error {
			return nil
		},
		i,
	)

	repo := NewRepository(goquDB, usersRepository, messagingRepository, hostsManager, afterCommentAdded)

	return repo, goquDB
}

func createRepository(t *testing.T) (*Repository, *goqu.Database) {
	t.Helper()

	return createRepositoryWithCallback(t, func(context.Context, int64) error { return nil })
}

func TestCleanupDeleted(t *testing.T) {
	t.Parallel()

	s, _ := createRepository(t)

	ctx := t.Context()

	_, err := s.CleanupDeleted(ctx)
	require.NoError(t, err)
}

func TestRefreshRepliesCount(t *testing.T) {
	t.Parallel()

	s, _ := createRepository(t)

	ctx := t.Context()

	_, err := s.RefreshRepliesCount(ctx)
	require.NoError(t, err)
}

func TestAdd(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()
	userID := createRandomUser(ctx, t, db)

	var (
		commentType       = schema.CommentMessageTypeIDPictures
		itemID      int64 = 1
	)

	_, err := repo.Add(ctx, commentType, itemID, 0, userID, "Test message", "127.0.0.1", false)
	require.NoError(t, err)
}

func TestAddCallsAfterCommentAdded(t *testing.T) {
	t.Parallel()

	var calledWith []int64

	repo, db := createRepositoryWithCallback(t, func(_ context.Context, authorID int64) error {
		calledWith = append(calledWith, authorID)

		return nil
	})
	ctx := t.Context()
	userID := createRandomUser(ctx, t, db)

	var (
		commentType       = schema.CommentMessageTypeIDPictures
		itemID      int64 = 1
	)

	_, err := repo.Add(ctx, commentType, itemID, 0, userID, "Test message", "127.0.0.1", false)
	require.NoError(t, err)
	require.Equal(t, []int64{userID}, calledWith)

	_, err = repo.Add(ctx, commentType, itemID, 0, userID, "Second message", "127.0.0.1", false)
	require.NoError(t, err)
	require.Equal(t, []int64{userID, userID}, calledWith)
}

func containsAuthorID(rows []RatingUser, authorID int64) bool {
	for _, row := range rows {
		if row.AuthorID == authorID {
			return true
		}
	}

	return false
}

func containsFanUserID(rows []RatingFan, userID int64) bool {
	for _, row := range rows {
		if row.UserID == userID {
			return true
		}
	}

	return false
}

func markUserDeleted(ctx context.Context, t *testing.T, db *goqu.Database, userID int64) {
	t.Helper()

	_, err := db.Update(schema.UserTable).
		Set(goqu.Record{schema.UserTableDeletedColName: true}).
		Where(schema.UserTableIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	require.NoError(t, err)
}

func TestTopAuthorsAndAuthorsFansExcludeDeletedUsers(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()

	author := createRandomUser(ctx, t, db)
	voter := createRandomUser(ctx, t, db)

	var (
		commentType       = schema.CommentMessageTypeIDPictures
		itemID      int64 = 1
	)

	commentID, err := repo.Add(ctx, commentType, itemID, 0, author, "Test message", "127.0.0.1", false)
	require.NoError(t, err)

	_, err = repo.VoteComment(ctx, voter, commentID, 1)
	require.NoError(t, err)

	topAuthors, err := repo.TopAuthors(ctx, 1000)
	require.NoError(t, err)
	require.True(t, containsAuthorID(topAuthors, author))

	fans, err := repo.AuthorsFans(ctx, author, 1000)
	require.NoError(t, err)
	require.True(t, containsFanUserID(fans, voter))

	markUserDeleted(ctx, t, db, author)

	topAuthors, err = repo.TopAuthors(ctx, 1000)
	require.NoError(t, err)
	require.False(t, containsAuthorID(topAuthors, author))

	markUserDeleted(ctx, t, db, voter)

	fans, err = repo.AuthorsFans(ctx, author, 1000)
	require.NoError(t, err)
	require.False(t, containsFanUserID(fans, voter))
}

func TestCleanBrokenMessages(t *testing.T) {
	t.Parallel()

	repo, _ := createRepository(t)

	_, err := repo.CleanBrokenMessages(t.Context())
	require.NoError(t, err)
}

func TestCleanTopics(t *testing.T) {
	t.Parallel()

	repo, _ := createRepository(t)

	_, err := repo.CleanTopics(t.Context())
	require.NoError(t, err)
}
