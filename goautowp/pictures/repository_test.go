package pictures

import (
	"database/sql"
	"io"
	"math/rand"
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

func repository(t *testing.T) (*goqu.Database, *Repository) {
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
	)
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

	pictureID, err := repo.AddPictureFromReader(ctx, handle, userID, "127.0.0.1", 0, 0, 0)
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

	require.Equal(t, pgtype.Null, picture.Point.Status)
}

func TestImageExifGPS(t *testing.T) {
	t.Parallel()

	goquDB, repo := repository(t)
	ctx := t.Context()

	userID := createRandomUser(t, goquDB)

	handle, err := os.OpenFile("../test/test_exif_gps.jpeg", os.O_RDONLY, 0)
	require.NoError(t, err)

	defer util.Close(handle)

	pictureID, err := repo.AddPictureFromReader(ctx, handle, userID, "127.0.0.1", 0, 0, 0)
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
	require.Equal(t, pgtype.Present, picture.Point.Status)
	require.InDelta(t, 43.464455, picture.Point.P.Y, 0.001)
	require.InDelta(t, 11.881478333333334, picture.Point.P.X, 0.001)
}

func TestImageBlackEdgeCrop(t *testing.T) {
	t.Parallel()

	goquDB, repo := repository(t)
	ctx := t.Context()

	userID := createRandomUser(t, goquDB)

	handle, err := os.OpenFile("../test/black-edge.jpeg", os.O_RDONLY, 0)
	require.NoError(t, err)

	defer util.Close(handle)

	pictureID, err := repo.AddPictureFromReader(ctx, handle, userID, "127.0.0.1", 0, 0, 0)
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
