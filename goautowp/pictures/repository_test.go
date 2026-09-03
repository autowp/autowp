package pictures

import (
	"context"
	"database/sql"
	"io"
	"math/rand"
	"net"
	"os"
	"strconv"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	"github.com/google/uuid"
	"github.com/jackc/pgtype"
	_ "github.com/lib/pq" // enable postgres driver
	"github.com/stretchr/testify/require"
	"gopkg.in/gographics/imagick.v3/imagick"
)

func createRandomUser(t *testing.T, db *goqu.Database) int64 {
	t.Helper()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	emailAddr := "test" + strconv.Itoa(random.Int()) + "@example.com"
	name := "ivan"
	insertQuery := db.Insert(schema.UserTable).
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
			schema.UserTableLastIPColName:         goqu.Func("inet", "127.0.0.1"),
			schema.UserTableLanguageColName:       schema.EnglishLanguageCode,
			schema.UserTableUUIDColName:           uuid.New().String(),
		})

	var id int64

	success, err := insertQuery.Returning(schema.UserTableIDCol).Executor().ScanValContext(t.Context(), &id)
	require.NoError(t, err)
	require.True(t, success)

	return id
}

func repositoryWithCallbacks(
	t *testing.T,
	afterPictureAcceptedAchievements func(ctx context.Context, ownerID sql.NullInt64, moderatorID int64) error,
	afterPictureQueuedForRemoval func(ctx context.Context, moderatorID int64) error,
) (*goqu.Database, *Repository) {
	t.Helper()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquDB := goqu.New("postgres", db)

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	textStorage := textstorage.New(goquDB)
	itemParentLanguageRepository := items.NewItemParentLanguageRepository(goquDB, cfg.ContentLanguages)
	itemsRepo := items.NewRepository(
		goquDB,
		cfg.MostsMinCarsCount,
		itemParentLanguageRepository,
		textStorage,
		imageStorage,
	)

	return goquDB, NewRepository(
		goquDB,
		imageStorage,
		textStorage,
		itemsRepo,
		cfg.DuplicateFinder,
		func(int64) error { return nil },
		func(context.Context) error { return nil },
		afterPictureAcceptedAchievements,
		afterPictureQueuedForRemoval,
	)
}

func repository(t *testing.T) (*goqu.Database, *Repository) {
	t.Helper()

	return repositoryWithCallbacks(t,
		func(context.Context, sql.NullInt64, int64) error { return nil },
		func(context.Context, int64) error { return nil },
	)
}

func createTestPicture(t *testing.T, db *goqu.Database, ownerID sql.NullInt64) int64 {
	t.Helper()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	identity := "t" + strconv.Itoa(int(random.Uint32()%100000))

	var pgIP pgtype.Inet

	err := pgIP.Set(net.IPv4(127, 0, 0, 1))
	require.NoError(t, err)

	var id int64

	// Status is deliberately Removed, not Inbox: neither Accept nor QueueRemove requires
	// any particular starting status, and Inbox would race against TestInbox's
	// unscoped inbox-count assertion elsewhere in the suite (both hit the same shared
	// Postgres test DB, and `go test ./...` runs packages concurrently).
	success, err := db.Insert(schema.PictureTable).Rows(schema.PictureRow{
		Identity:  identity,
		Status:    schema.PictureStatusRemoved,
		IP:        pgIP,
		CreatedAt: time.Now(),
		OwnerID:   ownerID,
		Point:     schema.NullPoint{Valid: false},
	}).Returning(schema.PictureTableIDCol).Executor().ScanValContext(t.Context(), &id)
	require.NoError(t, err)
	require.True(t, success)

	return id
}

func containsOwnerID(rows []RatingUser, ownerID int64) bool {
	for _, row := range rows {
		if row.OwnerID == ownerID {
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

func TestTopLikesAndTopOwnerFansExcludeDeletedUsers(t *testing.T) {
	t.Parallel()

	db, repo := repository(t)
	ctx := t.Context()

	owner := createRandomUser(t, db)
	voter := createRandomUser(t, db)
	pictureID := createTestPicture(t, db, sql.NullInt64{Int64: owner, Valid: true})

	err := repo.Vote(ctx, pictureID, 1, voter)
	require.NoError(t, err)

	topLikes, err := repo.TopLikes(ctx, 1000)
	require.NoError(t, err)
	require.True(t, containsOwnerID(topLikes, owner))

	fans, err := repo.TopOwnerFans(ctx, owner, 1000)
	require.NoError(t, err)
	require.True(t, containsFanUserID(fans, voter))

	markUserDeleted(ctx, t, db, owner)

	topLikes, err = repo.TopLikes(ctx, 1000)
	require.NoError(t, err)
	require.False(t, containsOwnerID(topLikes, owner))

	markUserDeleted(ctx, t, db, voter)

	fans, err = repo.TopOwnerFans(ctx, owner, 1000)
	require.NoError(t, err)
	require.False(t, containsFanUserID(fans, voter))
}

func TestAcceptCallsAchievementsCallback(t *testing.T) {
	t.Parallel()

	type call struct {
		ownerID     sql.NullInt64
		moderatorID int64
	}

	var calls []call

	db, repo := repositoryWithCallbacks(t,
		func(_ context.Context, ownerID sql.NullInt64, moderatorID int64) error {
			calls = append(calls, call{ownerID, moderatorID})

			return nil
		},
		func(context.Context, int64) error { return nil },
	)

	ctx := t.Context()
	ownerID := createRandomUser(t, db)
	moderatorID := createRandomUser(t, db)
	pictureID := createTestPicture(t, db, sql.NullInt64{Int64: ownerID, Valid: true})

	isFirstTime, success, err := repo.Accept(ctx, pictureID, moderatorID)
	require.NoError(t, err)
	require.True(t, success)
	require.True(t, isFirstTime)
	require.Len(t, calls, 1)
	require.True(t, calls[0].ownerID.Valid)
	require.Equal(t, ownerID, calls[0].ownerID.Int64)
	require.Equal(t, moderatorID, calls[0].moderatorID)

	// Re-accepting an already-accepted picture must NOT fire the callback again.
	isFirstTime, success, err = repo.Accept(ctx, pictureID, moderatorID)
	require.NoError(t, err)
	require.True(t, success)
	require.False(t, isFirstTime)
	require.Len(t, calls, 1)
}

func TestQueueRemoveCallsAchievementsCallback(t *testing.T) {
	t.Parallel()

	var calledWith []int64

	db, repo := repositoryWithCallbacks(t,
		func(context.Context, sql.NullInt64, int64) error { return nil },
		func(_ context.Context, moderatorID int64) error {
			calledWith = append(calledWith, moderatorID)

			return nil
		},
	)

	ctx := t.Context()
	moderatorID := createRandomUser(t, db)
	pictureID := createTestPicture(t, db, sql.NullInt64{})

	success, err := repo.QueueRemove(ctx, pictureID, moderatorID)
	require.NoError(t, err)
	require.True(t, success)
	require.Equal(t, []int64{moderatorID}, calledWith)
}

func TestImageExif(t *testing.T) {
	t.Parallel()

	goquDB, repo := repository(t)
	textStorage := textstorage.New(goquDB)
	ctx := t.Context()

	userID := createRandomUser(t, goquDB)

	handle, err := os.OpenFile("../test/test_exif.jpeg", os.O_RDONLY, 0)
	require.NoError(t, err)

	defer util.Close(handle)

	pictureID, err := repo.AddPictureFromReader(ctx, handle, userID, "127.0.0.1", 0, 0, 0, 0, 0, "")
	require.NoError(t, err)

	picture, err := repo.Picture(
		ctx,
		&query.PictureListOptions{ID: pictureID},
		&PictureFields{},
		OrderByNone,
	)
	require.NoError(t, err)

	require.EqualValues(t, 2022, picture.TakenYear.Int16)
	require.EqualValues(t, 11, picture.TakenMonth.Byte)
	require.EqualValues(t, 15, picture.TakenDay.Byte)
	require.NotEmpty(t, picture.CopyrightsTextID.Int32)

	text, err := textStorage.Text(ctx, picture.CopyrightsTextID.Int32)
	require.NoError(t, err)
	require.Equal(t, "Corey Escobar ©2021 Courtesy of RM Sotheby's", text)

	require.False(t, picture.Point.Valid)
}

func TestImageExifGPS(t *testing.T) {
	t.Parallel()

	goquDB, repo := repository(t)
	ctx := t.Context()

	userID := createRandomUser(t, goquDB)

	handle, err := os.OpenFile("../test/test_exif_gps.jpeg", os.O_RDONLY, 0)
	require.NoError(t, err)

	defer util.Close(handle)

	pictureID, err := repo.AddPictureFromReader(ctx, handle, userID, "127.0.0.1", 0, 0, 0, 0, 0, "")
	require.NoError(t, err)

	picture, err := repo.Picture(
		ctx,
		&query.PictureListOptions{ID: pictureID},
		&PictureFields{},
		OrderByNone,
	)
	require.NoError(t, err)

	require.EqualValues(t, 2008, picture.TakenYear.Int16)
	require.EqualValues(t, 10, picture.TakenMonth.Byte)
	require.EqualValues(t, 22, picture.TakenDay.Byte)
	require.True(t, picture.Point.Valid)
	require.InDelta(t, 43.464455, picture.Point.Point.Y(), 0.001)
	require.InDelta(t, 11.881478333333334, picture.Point.Point.X(), 0.001)
}

func TestImageBlackEdgeCrop(t *testing.T) {
	t.Parallel()

	goquDB, repo := repository(t)
	ctx := t.Context()

	userID := createRandomUser(t, goquDB)

	handle, err := os.OpenFile("../test/black-edge.jpeg", os.O_RDONLY, 0)
	require.NoError(t, err)

	defer util.Close(handle)

	pictureID, err := repo.AddPictureFromReader(ctx, handle, userID, "127.0.0.1", 0, 0, 0, 0, 0, "")
	require.NoError(t, err)

	cfg := config.LoadConfig("../")

	picture, err := repo.Picture(
		ctx,
		&query.PictureListOptions{ID: pictureID},
		&PictureFields{},
		OrderByNone,
	)
	require.NoError(t, err)

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	image, err := imageStorage.FormattedImage(ctx, int(picture.ImageID.Int64), "picture-thumb-large")
	require.NoError(t, err)

	imageBlob, err := imageStorage.ImageBlob(ctx, image.ID())
	require.NoError(t, err)

	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	imgBytes, err := io.ReadAll(imageBlob)
	require.NoError(t, err)

	err = mw.ReadImageBlob(imgBytes)
	require.NoError(t, err)

	color, err := mw.GetImagePixelColor(0, 2730)
	require.NoError(t, err)

	defer color.Destroy()

	require.InDelta(t, 0, color.GetRed(), 0.01)
	require.InDelta(t, 0, color.GetBlue(), 0.01)
	require.InDelta(t, 0, color.GetGreen(), 0.01)
}
