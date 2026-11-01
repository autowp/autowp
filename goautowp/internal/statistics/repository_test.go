package statistics

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres"
	_ "github.com/lib/pq"
	"github.com/stretchr/testify/require"
)

func newTestRepository(t *testing.T) (*Repository, *sql.DB) {
	t.Helper()

	pool, err := sql.Open("postgres", config.LoadConfig("../..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	_, err = pool.ExecContext(t.Context(), `
		SET TIME ZONE 'UTC';
		CREATE TEMP TABLE users (id bigint, deleted boolean, green boolean, identity text,
			last_online timestamptz, pictures_total integer);
		INSERT INTO users VALUES (1, false, true, NULL, NOW(), 30),
			(2, false, true, 'autowp', NOW(), 20), (3, true, false, NULL, NOW(), 100),
			(4, false, true, NULL, NOW() - INTERVAL '1 year', 10);
		CREATE TEMP TABLE item (id bigint);
		INSERT INTO item VALUES (1), (2);
		CREATE TEMP TABLE comment_message (deleted boolean);
		INSERT INTO comment_message VALUES (false), (false), (true);
		CREATE TEMP TABLE picture (filesize bigint);
		INSERT INTO picture VALUES (1048576), (2097152), (NULL);
		CREATE TEMP TABLE log_event (user_id bigint, created_at timestamptz);
		INSERT INTO log_event VALUES
		(1, '2020-01-01 10:15:00+00'), (1, '2020-01-01 10:45:00+00'),
		(1, '2020-01-02 11:00:00+00'), (2, '2020-01-02 11:30:00+00'),
		(3, '2020-01-02 11:30:00+00'), (1, '2020-01-03 00:00:00+00');
	`)
	require.NoError(t, err)

	return NewRepository(goqu.New("postgres", pool)), pool
}

func TestCountsAndContributors(t *testing.T) {
	t.Parallel()

	repository, pool := newTestRepository(t)
	users, err := repository.TotalUsers(t.Context())
	require.NoError(t, err)
	require.EqualValues(t, 3, users)
	items, err := repository.TotalItems(t.Context())
	require.NoError(t, err)
	require.EqualValues(t, 2, items)
	comments, err := repository.TotalComments(t.Context())
	require.NoError(t, err)
	require.EqualValues(t, 2, comments)
	pictures, err := repository.PictureStats(t.Context())
	require.NoError(t, err)
	require.EqualValues(t, 3, pictures.Count)
	require.True(t, pictures.Size.Valid)
	require.InDelta(t, 3, pictures.Size.Float64, 0.000001)
	contributors, err := repository.Contributors(t.Context())
	require.NoError(t, err)
	require.ElementsMatch(t, []string{"1", "2", "4"}, contributors)

	_, err = pool.ExecContext(t.Context(), "TRUNCATE pg_temp.picture")
	require.NoError(t, err)
	pictures, err = repository.PictureStats(t.Context())
	require.NoError(t, err)
	require.Zero(t, pictures.Count)
	require.False(t, pictures.Size.Valid)
}

func TestPulseGroupings(t *testing.T) {
	t.Parallel()

	repository, _ := newTestRepository(t)
	from := time.Date(2020, time.January, 1, 0, 0, 0, 0, time.UTC)
	to := from.AddDate(0, 0, 2)

	for _, test := range []struct {
		name     string
		grouping PulseGrouping
		expected []schema.StatisticsPulseRow
	}{
		{name: "hour", grouping: PulseGroupHour, expected: []schema.StatisticsPulseRow{
			{UserID: 1, Date: "2020-01-01 10", Value: 2},
			{UserID: 1, Date: "2020-01-02 11", Value: 1},
			{UserID: 2, Date: "2020-01-02 11", Value: 1},
		}},
		{name: "day", grouping: PulseGroupDay, expected: []schema.StatisticsPulseRow{
			{UserID: 1, Date: "2020-01-01", Value: 2},
			{UserID: 1, Date: "2020-01-02", Value: 1},
			{UserID: 2, Date: "2020-01-02", Value: 1},
		}},
		{name: "month", grouping: PulseGroupMonth, expected: []schema.StatisticsPulseRow{
			{UserID: 1, Date: "2020-01", Value: 3}, {UserID: 2, Date: "2020-01", Value: 1},
		}},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			actual, err := repository.Pulse(t.Context(), from, to, test.grouping)
			require.NoError(t, err)
			require.ElementsMatch(
				t,
				test.expected,
				actual,
				"deleted users and the upper time boundary must be excluded",
			)
		})
	}
}

func TestReadCancellation(t *testing.T) {
	t.Parallel()

	repository, _ := newTestRepository(t)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err := repository.TotalUsers(ctx)
	require.ErrorIs(t, err, context.Canceled)
	_, err = repository.PictureStats(ctx)
	require.ErrorIs(t, err, context.Canceled)
	_, err = repository.Contributors(ctx)
	require.ErrorIs(t, err, context.Canceled)
}
