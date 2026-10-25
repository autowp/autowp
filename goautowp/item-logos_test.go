package goautowp

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newItemLogoTestExtractor(t *testing.T) (*ItemExtractor, *items.Item, *itemBatchQueryLogger) {
	t.Helper()

	cfg := config.LoadConfig(".")
	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(2)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	goquDB := goqu.New("postgres", database)
	container := &Container{config: cfg, goquDB: goquDB}
	imageStorage, err := container.ImageStorage(t.Context())
	require.NoError(t, err)
	imageID, err := imageStorage.AddImageFromFilepath(
		t.Context(), "./test/test.jpg", "brand", storage.GenerateOptions{},
	)
	require.NoError(t, err)
	t.Cleanup(func() {
		ctx, cancel := context.WithTimeout(context.WithoutCancel(t.Context()), 10*time.Second)
		defer cancel()

		require.NoError(t, imageStorage.RemoveImage(ctx, imageID))
	})

	_, err = container.ItemsRepository(t.Context())
	require.NoError(t, err)

	logger := &itemBatchQueryLogger{}
	goquDB.Logger(logger)

	row := &items.Item{LogoID: sql.NullInt64{Int64: int64(imageID), Valid: true}}

	return NewItemExtractor(container), row, logger
}

func TestItemLogosShareParallelFieldGroup(t *testing.T) {
	t.Parallel()

	extractor, row, logger := newItemLogoTestExtractor(t)
	unblock := blockItemExtractionQueries(t, logger, itemExtractorParallelism)

	ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
	defer cancel()

	fields := &ItemFields{Logo: true, Logo120: true, Brandicon: true, OtherNames: true}
	original := proto.Clone(fields)
	result := &Item{}
	done := make(chan error, 1)

	go func() { done <- extractor.extractPlain(ctx, fields, row, result, "en", UserContext{}) }()

	// The three logo reads and OtherNames must all start before release. A nested
	// runItemLookups call would serialize the logo reads and fail this barrier.
	unblock()
	require.NoError(t, <-done)

	imageStorage, err := extractor.container.ImageStorage(ctx)
	require.NoError(t, err)
	source, err := imageStorage.Image(ctx, int(row.LogoID.Int64))
	require.NoError(t, err)
	logo, err := imageStorage.FormattedImage(ctx, int(row.LogoID.Int64), "logo")
	require.NoError(t, err)
	brandicon, err := imageStorage.FormattedImage(ctx, int(row.LogoID.Int64), "brandicon2")
	require.NoError(t, err)
	require.True(t, proto.Equal(APIImageToGRPC(source), result.GetLogo()))
	require.True(t, proto.Equal(APIImageToGRPC(logo), result.GetLogo120()))
	require.True(t, proto.Equal(APIImageToGRPC(brandicon), result.GetBrandicon()))
	require.NotEqual(t, result.GetLogo120().GetId(), result.GetBrandicon().GetId())
	require.True(t, proto.Equal(original, fields), "logo extraction must not mutate requested fields")
}

func TestItemLogosRespectFieldSelection(t *testing.T) {
	t.Parallel()

	extractor, row, logger := newItemLogoTestExtractor(t)
	for _, testCase := range []struct {
		name   string
		fields *ItemFields
	}{
		{name: "original", fields: &ItemFields{Logo: true}},
		{name: "logo", fields: &ItemFields{Logo120: true}},
		{name: "brandicon", fields: &ItemFields{Brandicon: true}},
		{name: "none", fields: &ItemFields{}},
		{name: "nil fields"},
	} {
		// Reuse one source sequentially so no other test touches the logger.
		logger.queries.Store(0)

		logo, logo120, brandicon, err := extractor.extractLogos(t.Context(), testCase.fields, row)
		require.NoError(t, err, testCase.name)
		require.Equal(t, testCase.fields.GetLogo(), logo != nil)
		require.Equal(t, testCase.fields.GetLogo120(), logo120 != nil)
		require.Equal(t, testCase.fields.GetBrandicon(), brandicon != nil)

		if testCase.fields == nil || !testCase.fields.GetLogo() && !testCase.fields.GetLogo120() &&
			!testCase.fields.GetBrandicon() {
			require.Zero(t, logger.queries.Load(), "unselected images must not be loaded")
		}
	}

	canceledCtx, cancel := context.WithCancel(t.Context())
	cancel()

	logo, logo120, brandicon, err := extractor.extractLogos(canceledCtx,
		&ItemFields{Logo: true, Logo120: true, Brandicon: true}, row)
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, logo)
	require.Nil(t, logo120)
	require.Nil(t, brandicon)

	missing := &items.Item{LogoID: sql.NullInt64{Int64: -1, Valid: true}}
	logo, logo120, brandicon, err = extractor.extractLogos(t.Context(),
		&ItemFields{Logo: true, Logo120: true, Brandicon: true}, missing)
	require.ErrorIs(t, err, storage.ErrImageNotFound)
	require.Nil(t, logo)
	require.Nil(t, logo120)
	require.Nil(t, brandicon)
}

func TestItemLogosWithoutImage(t *testing.T) {
	t.Parallel()

	// A missing logo must not access the absent container, even with all fields selected.
	logo, logo120, brandicon, err := (&ItemExtractor{}).extractLogos(t.Context(),
		&ItemFields{Logo: true, Logo120: true, Brandicon: true}, &items.Item{})
	require.NoError(t, err)
	require.Nil(t, logo)
	require.Nil(t, logo120)
	require.Nil(t, brandicon)
}
