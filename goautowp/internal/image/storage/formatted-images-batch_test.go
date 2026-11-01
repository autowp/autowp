package storage_test

import (
	"testing"

	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/image/storage"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/stretchr/testify/require"
)

func TestRelatedGroupFormattedImagesBatch(t *testing.T) {
	t.Parallel()

	db, database, logger := testutil.NewPostgres(t, "../../..")
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE image (id integer, width integer, height integer, filesize integer, filepath text, dir text);
		CREATE TEMP TABLE image_formatted (image_id integer, format text, image_formatted_id integer);
		INSERT INTO image VALUES
			(101, 300, 200, 1000, 'one.jpg', 'format'), (102, 300, 200, 1000, 'two.jpg', 'format');
		INSERT INTO image_formatted VALUES (1, 'picture-thumb-large', 101), (2, 'picture-thumb-large', 102);
	`)
	require.NoError(t, err)
	imageStorage, err := storage.NewStorage(db, config.LoadConfig("../../..").ImageStorage)
	require.NoError(t, err)
	images, err := imageStorage.FormattedImages(t.Context(), []int{1, 2}, "picture-thumb-large")
	require.NoError(t, err)
	require.Len(t, images, 2)
	require.EqualValues(t, 1, logger.Queries.Load())

	for _, id := range []int{1, 2} {
		expected, err := imageStorage.FormattedImage(t.Context(), id, "picture-thumb-large")
		require.NoError(t, err)
		require.Equal(t, expected.Src(), images[id].Src())
	}
}
