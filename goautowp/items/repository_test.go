package items

import (
	"context"
	"database/sql"
	"math/rand"
	"net"
	"strconv"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/mysql" // enable mysql dialect
	_ "github.com/go-sql-driver/mysql"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
)

func TestTopBrandsListRuZh(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	langs := []string{"ru", "zh"}

	for _, lang := range langs {
		options := query.ItemListOptions{
			Language:   lang,
			TypeID:     []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
			Limit:      150,
			SortByName: true,
		}
		res, _, err := repository.List(ctx, &options, &ItemFields{
			NameOnly:                   true,
			DescendantsCount:           true,
			NewDescendantsCount:        true,
			Description:                true,
			FullText:                   true,
			NameHTML:                   true,
			NameText:                   true,
			NameDefault:                true,
			DescendantTwinsGroupsCount: true,
		}, OrderByNone, true)
		require.NoError(t, err)
		require.NotEmpty(t, res)

		c, err := repository.Count(ctx, options)
		require.NoError(t, err)
		require.Positive(t, c)
	}
}

func TestListFilters(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	options := query.ItemListOptions{
		Language: "en",
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		ItemParentChild: &query.ItemParentListOptions{
			ChildItems: &query.ItemListOptions{
				TypeID:       []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDVehicle},
				IsConcept:    true,
				EngineItemID: 1,
			},
		},
		ItemParentParent: &query.ItemParentListOptions{
			ParentItems: &query.ItemListOptions{
				TypeID:    []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDVehicle},
				NoParents: true,
				Catname:   "test",
			},
		},
		Limit: 150,
	}
	_, _, err = repository.List(ctx, &options, &ItemFields{
		NameOnly:                   true,
		DescendantsCount:           true,
		NewDescendantsCount:        true,
		Description:                true,
		FullText:                   true,
		NameHTML:                   true,
		NameText:                   true,
		NameDefault:                true,
		DescendantTwinsGroupsCount: true,
	}, OrderByDescendantsCount, true)
	require.NoError(t, err)
}

func TestGetItemsNameAndCatnameShouldNotBeOmittedWhenDescendantsCountRequested(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)
	options := query.ItemListOptions{
		Language: "en",
		TypeID:   []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		Limit:    10,
	}
	_, _, err = repository.List(ctx, &options, &ItemFields{
		NameOnly:         true,
		DescendantsCount: true,
		ChildsCount:      true,
	}, OrderByNone, true)
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

func TestGetUserPicturesBrands(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	userID := createRandomUser(ctx, t, goquDB)
	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	brandID, err := repository.CreateItem(ctx, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDBrand,
		Name:            "",
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	}, userID)
	require.NoError(t, err)

	vehicleID, err := repository.CreateItem(ctx, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
		Name:            "",
		Body:            "",
		ProducedExactly: false,
	}, userID)
	require.NoError(t, err)

	success, err := repository.CreateItemParent(
		ctx,
		vehicleID,
		brandID,
		schema.ItemParentTypeDefault,
		"",
	)
	require.NoError(t, err)
	require.True(t, success)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	identity := "t" + strconv.Itoa(int(random.Uint32()%100000))

	res, err := goquDB.Insert(schema.PictureTable).Rows(goqu.Record{
		schema.PictureTableIdentityColName: identity,
		schema.PictureTableStatusColName:   schema.PictureStatusAccepted,
		schema.PictureTableIPColName:       "",
		schema.PictureTableOwnerIDColName:  userID,
	}).Executor().ExecContext(ctx)
	require.NoError(t, err)

	pictureID, err := res.LastInsertId()
	require.NoError(t, err)

	_, err = goquDB.Insert(schema.PictureItemTable).Rows(goqu.Record{
		schema.PictureItemTablePictureIDColName: pictureID,
		schema.PictureItemTableItemIDColName:    vehicleID,
	}).Executor().ExecContext(ctx)
	require.NoError(t, err)

	res2, _, err := repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{
				Pictures: &query.PictureListOptions{
					OwnerID: userID,
					Status:  schema.PictureStatusAccepted,
				},
			},
		},
		TypeID:     []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		Limit:      10,
		SortByName: true,
	}, &ItemFields{
		NameOnly:                true,
		DescendantPicturesCount: true,
		ChildsCount:             true,
	}, OrderByNone, true)
	require.NoError(t, err)
	require.NotEmpty(t, res2)

	for _, i := range res2 {
		require.Equal(t, int32(1), i.DescendantPicturesCount)
	}
}

func TestPaginator(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	name := "TestPaginator" + strconv.Itoa(int(random.Uint32()%100000))

	for i := range 10 {
		CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDBrand,
			Name:            name + "_" + strconv.Itoa(i),
			Body:            "",
			ProducedExactly: false,
		})
	}

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)
	options := query.ItemListOptions{
		Language: "en",
		Limit:    2,
		Page:     2,
		Name:     name + "%",
	}
	r, pages, err := repository.List(ctx, &options, nil, OrderByNone, true)
	require.NoError(t, err)
	require.NotEmpty(t, r)
	require.Len(t, r, 2)
	require.Equal(t, int32(10), pages.TotalItemCount)
	require.Equal(t, int32(5), pages.PageCount)
	require.Equal(t, int32(2), pages.Current)
}

func TestOrderByDescendantsCount(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	name := "TestOrderByDescendantsCount" + strconv.Itoa(int(random.Uint32()%100000))
	itemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDBrand,
		Name:            name,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	subName := name + "sub"
	subItemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
		Name:            subName,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	success, err := repository.CreateItemParent(
		ctx,
		subItemID,
		itemID,
		schema.ItemParentTypeDefault,
		"",
	)
	require.NoError(t, err)
	require.True(t, success)

	for i := range 10 {
		subSubName := name + "_" + strconv.Itoa(i)
		subSubItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
			Name:            subSubName,
			Body:            "",
			ProducedExactly: false,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)
	}

	// with field DescendantsCount, with pagination
	list, pages, err := repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     name + "%",
	}, &ItemFields{DescendantsCount: true}, OrderByDescendantsCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(11), list[0].DescendantsCount)

	// without field DescendantsCount, with pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     name + "%",
	}, nil, OrderByDescendantsCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantsCount)

	// with field DescendantsCount, without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     name + "%",
	}, &ItemFields{DescendantsCount: true}, OrderByDescendantsCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(11), list[0].DescendantsCount)

	// without field DescendantsCount, without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     name + "%",
	}, nil, OrderByDescendantsCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantsCount)
}

func TestOrderByOrderByDescendantPicturesCount(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	name := "TestOrderByOrderByDescendantPicturesCount" + strconv.Itoa(int(random.Uint32()%100000))
	userID := createRandomUser(ctx, t, goquDB)
	itemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDBrand,
		Name:            name,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	subName := name + "sub"
	subItemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
		Name:            subName,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	success, err := repository.CreateItemParent(
		ctx,
		subItemID,
		itemID,
		schema.ItemParentTypeDefault,
		"",
	)
	require.NoError(t, err)
	require.True(t, success)

	for i := range 10 {
		subSubName := name + "_" + strconv.Itoa(i)
		subSubItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
			Name:            subSubName,
			Body:            "",
			ProducedExactly: false,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)

		// add picture
		identity := "t" + strconv.Itoa(int(random.Uint32()%100000))

		res, err := goquDB.Insert(schema.PictureTable).Rows(schema.PictureRow{
			Identity: identity,
			Status:   schema.PictureStatusAccepted,
			OwnerID:  sql.NullInt64{Valid: true, Int64: userID},
			AddDate:  time.Now(),
			IP:       util.IP(net.IPv4zero),
		}).Executor().ExecContext(ctx)
		require.NoError(t, err)

		pictureID, err := res.LastInsertId()
		require.NoError(t, err)

		_, err = goquDB.Insert(schema.PictureItemTable).Rows(schema.PictureItemRow{
			PictureID: pictureID,
			ItemID:    subSubItemID,
		}).Executor().ExecContext(ctx)
		require.NoError(t, err)
	}

	// with field DescendantPicturesCount, with pagination
	list, pages, err := repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{},
		},
	}, &ItemFields{DescendantPicturesCount: true}, OrderByDescendantPicturesCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(10), list[0].DescendantPicturesCount)

	// without field DescendantPicturesCount, with pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{},
		},
	}, nil, OrderByDescendantPicturesCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantPicturesCount)

	// with field DescendantPicturesCount, without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{},
		},
	}, &ItemFields{DescendantPicturesCount: true}, OrderByDescendantPicturesCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(10), list[0].DescendantPicturesCount)

	// without field DescendantPicturesCount, without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{},
		},
	}, nil, OrderByDescendantPicturesCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantPicturesCount)
}

func TestOrderByAddDatetime(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	name := "TestOrderByAddDatetime" + strconv.Itoa(int(random.Uint32()%100000))
	itemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDBrand,
		Name:            name,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	subName := name + "sub"
	subItemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
		Name:            subName,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	success, err := repository.CreateItemParent(
		ctx,
		subItemID,
		itemID,
		schema.ItemParentTypeDefault,
		"",
	)
	require.NoError(t, err)
	require.True(t, success)

	for i := range 10 {
		subSubName := name + "_" + strconv.Itoa(i)
		subSubItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
			Name:            subSubName,
			Body:            "",
			ProducedExactly: false,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)
	}

	// with pagination
	list, pages, err := repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     name + "%",
	}, nil, OrderByAddDatetime, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantPicturesCount)

	// without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     name + "%",
	}, nil, OrderByAddDatetime, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantPicturesCount)
}

func TestOrderByName(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	name := "TestOrderByName" + strconv.Itoa(int(random.Uint32()%100000))
	itemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDBrand,
		Name:            "a" + name,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	subName := "b" + name + "sub"
	subItemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
		Name:            subName,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	success, err := repository.CreateItemParent(
		ctx,
		subItemID,
		itemID,
		schema.ItemParentTypeDefault,
		"",
	)
	require.NoError(t, err)
	require.True(t, success)

	for i := range 10 {
		subSubName := "c" + name + "_" + strconv.Itoa(i)
		subSubItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
			Name:            subSubName,
			Body:            "",
			ProducedExactly: false,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)
	}

	// with pagination
	list, pages, err := repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     "%" + name + "%",
	}, nil, OrderByName, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantPicturesCount)

	// with pagination, with fields
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     "%" + name + "%",
	}, &ItemFields{NameOnly: true, NameHTML: true, NameText: true, NameDefault: true}, OrderByName, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantPicturesCount)

	// without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     "%" + name + "%",
	}, nil, OrderByName, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantPicturesCount)

	// without pagination, with fields
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     "%" + name + "%",
	}, &ItemFields{NameOnly: true, NameHTML: true, NameText: true, NameDefault: true}, OrderByName, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantPicturesCount)
}

func TestOrderByDescendantsParentsCount(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	name := "TestOrderByDescendantsParentsCount" + strconv.Itoa(int(random.Uint32()%100000))

	itemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDBrand,
		Name:            name,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	subName := name + "sub"
	subItemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
		Name:            subName,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	success, err := repository.CreateItemParent(
		ctx,
		subItemID,
		itemID,
		schema.ItemParentTypeDefault,
		"",
	)
	require.NoError(t, err)
	require.True(t, success)

	for i := range 10 {
		subSubName := name + "_" + strconv.Itoa(i)
		subSubItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
			Name:            subSubName,
			Body:            "",
			ProducedExactly: false,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)

		// add parent
		subSubParentName := name + "_" + strconv.Itoa(i) + "_parent"
		subSubParentItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDTwins,
			Name:            subSubParentName,
			Body:            "",
			ProducedExactly: false,
			IsGroup:         true,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subSubParentItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)
	}

	// with field DescendantsParentsCount, with pagination
	list, pages, err := repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			ExcludeSelf: true,
			ItemParentByItemID: &query.ItemParentListOptions{
				ParentItems: &query.ItemListOptions{},
			},
		},
	}, &ItemFields{DescendantsParentsCount: true}, OrderByDescendantsParentsCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(12), list[0].DescendantsParentsCount)

	// without field DescendantsParentsCount, with pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Limit:    1,
		Page:     1,
		Name:     name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			ExcludeSelf:        true,
			ItemParentByItemID: &query.ItemParentListOptions{},
		},
	}, nil, OrderByDescendantsParentsCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantsParentsCount)

	// with field DescendantsParentsCount, without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			ExcludeSelf:        true,
			ItemParentByItemID: &query.ItemParentListOptions{},
		},
	}, &ItemFields{DescendantsParentsCount: true}, OrderByDescendantsParentsCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(12), list[0].DescendantsParentsCount)

	// without field DescendantsParentsCount, without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language: "en",
		Name:     name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			ExcludeSelf:        true,
			ItemParentByItemID: &query.ItemParentListOptions{},
		},
	}, nil, OrderByDescendantsParentsCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
	require.Equal(t, int32(0), list[0].DescendantsParentsCount)
}

func TestOrderByStarCount(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	ctx := t.Context()

	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	name := "TestOrderByStarCount" + strconv.Itoa(int(random.Uint32()%100000))

	itemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDBrand,
		Name:            name,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	subName := name + "sub"
	subItemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
		Name:            subName,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	success, err := repository.CreateItemParent(
		ctx,
		subItemID,
		itemID,
		schema.ItemParentTypeDefault,
		"",
	)
	require.NoError(t, err)
	require.True(t, success)

	for i := range 10 {
		subSubName := name + "_" + strconv.Itoa(i)
		subSubItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
			Name:            subSubName,
			Body:            "",
			ProducedExactly: false,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)
	}

	// with pagination
	list, pages, err := repository.List(ctx, &query.ItemListOptions{
		Language:                  "en",
		Limit:                     1,
		Page:                      1,
		Name:                      name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{},
	}, nil, OrderByStarCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(12), pages.TotalItemCount)
	require.Equal(t, int32(12), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)
	require.Equal(t, itemID, list[0].ID)

	// without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language:                  "en",
		Name:                      name + "%",
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{},
	}, nil, OrderByStarCount, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 12)
	require.Nil(t, pages)
	require.Equal(t, itemID, list[0].ID)
}

func TestOrderByItemParentParentTimestamp(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("mysql", cfg.AutowpDSN)
	require.NoError(t, err)

	goquDB := goqu.New("mysql", db)
	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)
	ctx := t.Context()
	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	name := "TestOrderByItemParentParentTimestamp" + strconv.Itoa(int(random.Uint32()%100000))
	itemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDBrand,
		Name:            name,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	subName := name + "sub"
	subItemID := CreateItem(t, goquDB, schema.ItemRow{
		ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
		Name:            subName,
		Body:            "",
		ProducedExactly: false,
		IsGroup:         true,
	})

	success, err := repository.CreateItemParent(
		ctx,
		subItemID,
		itemID,
		schema.ItemParentTypeDefault,
		"",
	)
	require.NoError(t, err)
	require.True(t, success)

	for i := range 10 {
		subSubName := name + "_" + strconv.Itoa(i)
		subSubItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDVehicle,
			Name:            subSubName,
			Body:            "",
			ProducedExactly: false,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)

		// add parent
		subSubParentName := name + "_" + strconv.Itoa(i) + "_parent"
		subSubParentItemID := CreateItem(t, goquDB, schema.ItemRow{
			ItemTypeID:      schema.ItemTableItemTypeIDTwins,
			Name:            subSubParentName,
			Body:            "",
			ProducedExactly: false,
			IsGroup:         true,
		})

		success, err = repository.CreateItemParent(
			ctx, subSubItemID, subSubParentItemID, schema.ItemParentTypeDefault, strconv.Itoa(i),
		)
		require.NoError(t, err)
		require.True(t, success)
	}

	// with pagination
	list, pages, err := repository.List(ctx, &query.ItemListOptions{
		Language:         "en",
		Limit:            1,
		Page:             1,
		Name:             name + "%",
		ItemParentParent: &query.ItemParentListOptions{},
	}, nil, OrderByItemParentParentTimestamp, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 1)
	require.Equal(t, int32(11), pages.TotalItemCount)
	require.Equal(t, int32(11), pages.PageCount)
	require.Equal(t, int32(1), pages.Current)

	// without pagination
	list, pages, err = repository.List(ctx, &query.ItemListOptions{
		Language:         "en",
		Name:             name + "%",
		ItemParentParent: &query.ItemParentListOptions{},
	}, nil, OrderByItemParentParentTimestamp, true)
	require.NoError(t, err)
	require.NotEmpty(t, list)
	require.Len(t, list, 11)
	require.Nil(t, pages)
}

func CreateItem(t *testing.T, goquDB *goqu.Database, row schema.ItemRow) int64 {
	t.Helper()

	ctx := t.Context()
	cfg := config.LoadConfig("../")
	imageStorage, err := storage.NewStorage(goquDB, cfg.ImageStorage)
	require.NoError(t, err)

	repository := NewRepository(
		goquDB,
		200,
		cfg.ContentLanguages,
		textstorage.New(goquDB),
		imageStorage,
	)

	itemID, err := repository.CreateItem(ctx, row, 0)
	require.NoError(t, err)

	_, err = repository.UpdateItemLanguage(ctx, itemID, "en", row.Name, "", "", 0)
	require.NoError(t, err)

	return itemID
}
