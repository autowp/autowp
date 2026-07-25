package achievements

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

func createRepository(t *testing.T) (*Repository, *goqu.Database) {
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

	i18n, err := i18nbundle.New()
	require.NoError(t, err)

	messagingRepository := messaging.NewRepository(
		goquDB,
		func(_ context.Context, _ int64, _ int64, _ string) error { return nil },
		func(_ context.Context, _ []int64) error { return nil },
		i18n,
	)

	repo := NewRepository(goquDB, usersRepository, messagingRepository, hostsManager)

	return repo, goquDB
}

func createRandomUser(t *testing.T, db *goqu.Database) int64 {
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
		Executor().ScanValContext(t.Context(), &id)
	require.NoError(t, err)
	require.True(t, success)

	return id
}

func TestGrantIsIdempotent(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()
	userID := createRandomUser(t, db)

	granted, err := repo.Grant(ctx, userID, schema.AchievementIDVeteran)
	require.NoError(t, err)
	require.True(t, granted)

	granted, err = repo.Grant(ctx, userID, schema.AchievementIDVeteran)
	require.NoError(t, err)
	require.False(t, granted)

	count, err := db.From(schema.UserAchievementTable).Where(
		schema.UserAchievementTableUserIDCol.Eq(userID),
		schema.UserAchievementTableAchievementIDCol.Eq(schema.AchievementIDVeteran),
	).CountContext(ctx)
	require.NoError(t, err)
	require.Equal(t, int64(1), count)
}

func TestGrantSkipsDeletedUser(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()
	userID := createRandomUser(t, db)

	_, err := db.Update(schema.UserTable).
		Set(goqu.Record{schema.UserTableDeletedColName: true}).
		Where(schema.UserTableIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	granted, err := repo.Grant(ctx, userID, schema.AchievementIDVeteran)
	require.NoError(t, err)
	require.False(t, granted)

	count, err := db.From(schema.UserAchievementTable).Where(
		schema.UserAchievementTableUserIDCol.Eq(userID),
	).CountContext(ctx)
	require.NoError(t, err)
	require.Equal(t, int64(0), count)
}

func TestGrantCommentPostedTierCrossing(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()
	userID := createRandomUser(t, db)

	for range 99 {
		require.NoError(t, repo.GrantCommentPosted(ctx, userID))
	}

	count, err := db.From(schema.UserAchievementTable).Where(
		schema.UserAchievementTableUserIDCol.Eq(userID),
		schema.UserAchievementTableAchievementIDCol.Eq(schema.AchievementIDCommentatorRookie),
	).CountContext(ctx)
	require.NoError(t, err)
	require.Equal(t, int64(0), count, "must not be granted before reaching the threshold")

	require.NoError(t, repo.GrantCommentPosted(ctx, userID))

	count, err = db.From(schema.UserAchievementTable).Where(
		schema.UserAchievementTableUserIDCol.Eq(userID),
		schema.UserAchievementTableAchievementIDCol.Eq(schema.AchievementIDCommentatorRookie),
	).CountContext(ctx)
	require.NoError(t, err)
	require.Equal(t, int64(1), count, "must be granted exactly upon reaching the threshold")

	var progressCount int64

	success, err := db.Select(schema.UserAchievementProgressTableCountCol).
		From(schema.UserAchievementProgressTable).
		Where(
			schema.UserAchievementProgressTableUserIDCol.Eq(userID),
			schema.UserAchievementProgressTableMetricCol.Eq(string(MetricCommentator)),
		).
		ScanValContext(ctx, &progressCount)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, int64(100), progressCount)
}

func TestGrantConcurrentIncrementsAreNotLost(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()
	userID := createRandomUser(t, db)

	const goroutines = 20

	errs := make(chan error, goroutines)

	for range goroutines {
		go func() {
			errs <- repo.GrantSpecValueSet(ctx, userID)
		}()
	}

	for range goroutines {
		require.NoError(t, <-errs)
	}

	var count int64

	success, err := db.Select(schema.UserAchievementProgressTableCountCol).
		From(schema.UserAchievementProgressTable).
		Where(
			schema.UserAchievementProgressTableUserIDCol.Eq(userID),
			schema.UserAchievementProgressTableMetricCol.Eq(string(MetricSpecMaster)),
		).
		ScanValContext(ctx, &count)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, int64(goroutines), count, "concurrent increments must not be lost")
}

func TestProgressOmitsUnstartedAndMaxedSeries(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()
	userID := createRandomUser(t, db)

	// No activity at all yet: no progress entries.
	progress, err := repo.Progress(ctx, userID, map[string]bool{})
	require.NoError(t, err)
	require.Empty(t, progress)

	for range 42 {
		require.NoError(t, repo.GrantCommentPosted(ctx, userID))
	}

	progress, err = repo.Progress(ctx, userID, map[string]bool{})
	require.NoError(t, err)
	require.Len(t, progress, 1)
	require.Equal(t, "commentator-rookie", progress[0].Code)
	require.Equal(t, int64(42), progress[0].Current)
	require.Equal(t, int64(100), progress[0].Threshold)

	// Once the rookie tier is (simulated as) earned, progress should point at the
	// next tier instead.
	progress, err = repo.Progress(ctx, userID, map[string]bool{"commentator-rookie": true})
	require.NoError(t, err)
	require.Len(t, progress, 1)
	require.Equal(t, "commentator-practicing", progress[0].Code)

	// If every tier in the series is already earned, no progress entry remains.
	progress, err = repo.Progress(ctx, userID, map[string]bool{
		"commentator-rookie":     true,
		"commentator-practicing": true,
		"commentator-regular":    true,
		"commentator-expert":     true,
		"commentator-god":        true,
	})
	require.NoError(t, err)
	require.Empty(t, progress)
}

func TestUserAchievements(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()
	userID := createRandomUser(t, db)

	granted, err := repo.Grant(ctx, userID, schema.AchievementIDPicturesContributor)
	require.NoError(t, err)
	require.True(t, granted)

	for range 5 {
		require.NoError(t, repo.GrantCommentPosted(ctx, userID))
	}

	result, err := repo.UserAchievements(ctx, userID)
	require.NoError(t, err)
	require.Len(t, result.Earned, 1)
	require.Equal(t, "pictures-contributor", result.Earned[0].Code)
	require.Len(t, result.Progress, 1)
	require.Equal(t, "commentator-rookie", result.Progress[0].Code)
	require.Equal(t, int64(5), result.Progress[0].Current)
}

func TestAchievementCounts(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()

	userA := createRandomUser(t, db)
	userB := createRandomUser(t, db)

	granted, err := repo.Grant(ctx, userA, schema.AchievementIDPicturesContributor)
	require.NoError(t, err)
	require.True(t, granted)

	granted, err = repo.Grant(ctx, userB, schema.AchievementIDPicturesContributor)
	require.NoError(t, err)
	require.True(t, granted)

	rows, err := repo.AchievementCounts(ctx)
	require.NoError(t, err)

	counts := make(map[string]int64, len(rows))
	for _, row := range rows {
		counts[row.Code] = row.Count
	}

	require.GreaterOrEqual(t, counts["pictures-contributor"], int64(2))
	// An achievement nobody (in this run) has earned still appears, with count 0 or
	// more — the LEFT JOIN must not omit rows entirely.
	_, ok := counts["inspector-god"]
	require.True(t, ok)
}

func TestRecomputeVeteranBadges(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()

	veteranUser := createRandomUser(t, db)
	recentUser := createRandomUser(t, db)

	_, err := db.Update(schema.UserTable).
		Set(goqu.Record{schema.UserTableRegDateColName: goqu.L("NOW() - INTERVAL '11 years'")}).
		Where(schema.UserTableIDCol.Eq(veteranUser)).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	_, err = db.Update(schema.UserTable).
		Set(goqu.Record{schema.UserTableRegDateColName: goqu.L("NOW() - INTERVAL '5 years'")}).
		Where(schema.UserTableIDCol.Eq(recentUser)).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	_, err = repo.RecomputeVeteranBadges(ctx)
	require.NoError(t, err)

	veteranCount, err := db.From(schema.UserAchievementTable).Where(
		schema.UserAchievementTableUserIDCol.Eq(veteranUser),
		schema.UserAchievementTableAchievementIDCol.Eq(schema.AchievementIDVeteran),
	).CountContext(ctx)
	require.NoError(t, err)
	require.Equal(t, int64(1), veteranCount)

	recentCount, err := db.From(schema.UserAchievementTable).Where(
		schema.UserAchievementTableUserIDCol.Eq(recentUser),
		schema.UserAchievementTableAchievementIDCol.Eq(schema.AchievementIDVeteran),
	).CountContext(ctx)
	require.NoError(t, err)
	require.Equal(t, int64(0), recentCount)

	// Idempotent: running again grants nothing new for the already-granted user.
	grantedAgain, err := repo.RecomputeVeteranBadges(ctx)
	require.NoError(t, err)
	require.Equal(t, 0, grantedAgain)
}

func TestRecomputeTopPicturesContributors(t *testing.T) {
	t.Parallel()

	repo, db := createRepository(t)
	ctx := t.Context()

	// An extreme pictures_total makes this user's top-10 membership deterministic
	// regardless of whatever other data exists in the shared test database.
	topUser := createRandomUser(t, db)

	_, err := db.Update(schema.UserTable).
		Set(goqu.Record{schema.UserTablePicturesTotalColName: 1_000_000_000}).
		Where(schema.UserTableIDCol.Eq(topUser)).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	_, err = repo.RecomputeTopPicturesContributors(ctx)
	require.NoError(t, err)

	count, err := db.From(schema.UserAchievementTable).Where(
		schema.UserAchievementTableUserIDCol.Eq(topUser),
		schema.UserAchievementTableAchievementIDCol.Eq(schema.AchievementIDTopPicturesContributor),
	).CountContext(ctx)
	require.NoError(t, err)
	require.Equal(t, int64(1), count)
}
