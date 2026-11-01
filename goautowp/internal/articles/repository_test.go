package articles_test

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/articles"
	"github.com/autowp/goautowp/internal/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres"
	_ "github.com/lib/pq"
	"github.com/stretchr/testify/require"
)

func newTestRepository(t *testing.T) (*articles.Repository, *sql.DB) {
	t.Helper()

	pool, err := sql.Open("postgres", config.LoadConfig("../..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	_, err = pool.ExecContext(t.Context(), `
		CREATE TEMP TABLE article (id bigint PRIMARY KEY, name text, catname text, author_id bigint,
			created_at timestamptz, preview_filename text, description text, html text, enabled boolean);
		INSERT INTO article
		SELECT n, 'Article ' || n, 'article-' || n, CASE WHEN n = 1 THEN NULL ELSE 42 END,
			TIMESTAMPTZ '2020-01-01' + n * INTERVAL '1 day',
			CASE WHEN n = 1 THEN NULL WHEN n = 2 THEN '' ELSE n || '.jpg' END,
			'Description ' || n, CASE WHEN n = 1 THEN NULL ELSE '<p>Text</p>' END, true
		FROM generate_series(1, 12) AS n;
		INSERT INTO article VALUES (99, 'Hidden', 'disabled', NULL, NOW(), NULL, '', NULL, false);
	`)
	require.NoError(t, err)

	return articles.NewRepository(goqu.New("postgres", pool)), pool
}

func TestRepositoryList(t *testing.T) {
	t.Parallel()

	repository, _ := newTestRepository(t)
	for _, test := range []struct {
		name        string
		page        int32
		current     int32
		expectedIDs []int64
	}{
		{name: "default page", expectedIDs: []int64{12, 11, 10, 9, 8, 7, 6, 5, 4, 3}, current: 1},
		{name: "second page", page: 2, expectedIDs: []int64{2, 1}, current: 2},
		{name: "past last page", page: 99, expectedIDs: []int64{2, 1}, current: 2},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			rows, pages, err := repository.List(t.Context(), test.page)
			require.NoError(t, err)
			require.EqualValues(t, 12, pages.TotalItemCount, "disabled articles must be excluded")
			require.EqualValues(t, 2, pages.PageCount)
			require.Equal(t, test.current, pages.Current)

			ids := make([]int64, 0, len(rows))
			for _, row := range rows {
				ids = append(ids, row.ID)
				require.NotEmpty(t, row.Description)
				require.False(t, row.HTML.Valid, "list reads must not load the full article body")
			}

			require.Equal(t, test.expectedIDs, ids, "newest articles must come first")
		})
	}
}

func TestRepositoryByCatname(t *testing.T) {
	t.Parallel()

	repository, _ := newTestRepository(t)
	article, err := repository.ByCatname(t.Context(), "article-1")
	require.NoError(t, err)
	require.EqualValues(t, 1, article.ID)
	require.False(t, article.AuthorID.Valid)
	require.False(t, article.HTML.Valid)
	require.False(t, article.PreviewFilename.Valid)

	article, err = repository.ByCatname(t.Context(), "article-2")
	require.NoError(t, err)
	require.EqualValues(t, 42, article.AuthorID.Int64)
	require.True(t, article.PreviewFilename.Valid, "empty filenames must remain distinct from NULL")
	require.Empty(t, article.PreviewFilename.String)
	require.Equal(t, "<p>Text</p>", article.HTML.String)

	article, err = repository.ByCatname(t.Context(), "article-3")
	require.NoError(t, err)
	require.Equal(t, "3.jpg", article.PreviewFilename.String)

	for _, catname := range []string{"missing", "disabled"} {
		_, err := repository.ByCatname(t.Context(), catname)
		require.ErrorIs(t, err, articles.ErrNotFound)
	}
}

func TestRepositoryEmptyList(t *testing.T) {
	t.Parallel()

	repository, pool := newTestRepository(t)
	_, err := pool.ExecContext(t.Context(), "TRUNCATE pg_temp.article")
	require.NoError(t, err)
	rows, pages, err := repository.List(t.Context(), 1)
	require.NoError(t, err)
	require.NotNil(t, rows)
	require.Empty(t, rows)
	require.Zero(t, pages.TotalItemCount)
	require.Zero(t, pages.PageCount)
	require.EqualValues(t, 1, pages.Current)
}

func TestRepositoryErrors(t *testing.T) {
	t.Parallel()

	repository, pool := newTestRepository(t)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	rows, pages, err := repository.List(ctx, 1)
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, rows)
	require.Nil(t, pages)

	_, err = repository.ByCatname(ctx, "article-1")
	require.ErrorIs(t, err, context.Canceled)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.article RENAME COLUMN catname TO broken_catname")
	require.NoError(t, err)
	_, _, err = repository.List(t.Context(), 1)
	require.Error(t, err)
	_, err = repository.ByCatname(t.Context(), "article-1")
	require.Error(t, err)
	require.NotErrorIs(t, err, articles.ErrNotFound, "query failures must not become missing articles")
}
