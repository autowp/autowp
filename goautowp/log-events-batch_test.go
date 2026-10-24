package goautowp

import (
	"context"
	"database/sql"
	"testing"

	eventlog "github.com/autowp/goautowp/log"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func newLogEventsBatchRepository(t *testing.T) (*eventlog.Repository, *goqu.Database, *sql.DB) {
	t.Helper()
	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE log_event (id bigint PRIMARY KEY,user_id bigint,created_at timestamptz,description text);
 CREATE TEMP TABLE log_event_item (log_event_id bigint,item_id bigint);
 CREATE TEMP TABLE log_event_picture (log_event_id bigint,picture_id bigint);
 CREATE TEMP TABLE log_event_article (log_event_id bigint,article_id bigint);
 INSERT INTO log_event
 SELECT i,i%3,TIMESTAMPTZ '2020-01-01 00:00:00+00' + (i/3)*INTERVAL '1 SECOND','event ' || i
 FROM generate_series(1,65) AS i;
 INSERT INTO log_event_item SELECT id,id+900 FROM log_event WHERE id%4=1;
 INSERT INTO log_event_item SELECT id,id+100 FROM log_event WHERE id%4=1;
 INSERT INTO log_event_item SELECT id,id+900 FROM log_event WHERE id%4=1;
 INSERT INTO log_event_item SELECT id,id+300 FROM log_event WHERE id%4=2;
 INSERT INTO log_event_item SELECT id,42 FROM log_event WHERE id%4 IN (2,3);
 INSERT INTO log_event_picture SELECT id,id+800 FROM log_event WHERE id%5<>0;
 INSERT INTO log_event_picture SELECT id,id+400 FROM log_event WHERE id%5<>0;
 INSERT INTO log_event_picture SELECT id,7 FROM log_event WHERE id%2=0;
 INSERT INTO log_event_article SELECT id,1 FROM log_event WHERE id%2=0;
 INSERT INTO log_event_article VALUES (2,1),(2,2),(3,2);
 `)
	require.NoError(t, err)
	db.Logger(nil)

	return eventlog.NewRepository(db), db, pool
}

func TestLogEventsBatchMatchesIndividual(t *testing.T) {
	t.Parallel()

	for _, mode := range []string{"heap", "indexes"} {
		t.Run(mode, func(t *testing.T) {
			t.Parallel()

			repository, db, pool := newLogEventsBatchRepository(t)
			if mode == "indexes" {
				_, err := pool.ExecContext(t.Context(), `
    CREATE INDEX ON log_event_item(log_event_id,item_id);
    CREATE INDEX ON log_event_picture(log_event_id,picture_id);
    ANALYZE pg_temp.log_event_item;
    ANALYZE pg_temp.log_event_picture;
    SET enable_seqscan=off;
   `)
				require.NoError(t, err)
			}

			for _, options := range []eventlog.ListOptions{
				{},
				{Page: 2},
				{Page: 99},
				{UserID: 1},
				{ItemID: 42},
				{PictureID: 7},
				{ArticleID: 1},
				{ArticleID: 1, PictureID: 7, ItemID: 42, UserID: 1},
				{ArticleID: 2},
				{UserID: -1},
				{ItemID: 9999},
			} {
				logger := &itemBatchQueryLogger{}
				db.Logger(logger)
				expected, pages, err := legacyLogEvents(t.Context(), db, options)
				require.NoError(t, err)
				require.EqualValues(t, 2+2*len(expected), logger.queries.Load())
				logger.queries.Store(0)

				actual, actualPages, err := repository.Events(t.Context(), options)
				require.NoError(t, err)
				db.Logger(nil)
				require.Equal(t, expected, actual, "options %+v", options)
				require.Equal(t, pages, actualPages)

				queries := 2
				if len(expected) > 0 {
					queries += 2
				}

				require.EqualValues(
					t,
					queries,
					logger.queries.Load(),
					"related reads are batched, independent of page size",
				)
			}
		})
	}
}

func TestLogEventsBatchParallelLinks(t *testing.T) {
	t.Parallel()
	repository, db, _ := newLogEventsBatchRepository(t)
	links := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, links, 2)
	logger := &itemBatchQueryLogger{}
	logger.beforeQuery = func() {
		if logger.queries.Load() > 2 {
			links.Printf("")
		}
	}
	db.Logger(logger)

	joined := make(chan error, 1)

	go func() {
		_, _, err := repository.Events(t.Context(), eventlog.ListOptions{})
		joined <- err
	}()

	unblock()
	require.NoError(t, <-joined)
	require.EqualValues(t, 4, logger.queries.Load())
}

func TestLogEventsBatchFailureAndEmpty(t *testing.T) {
	t.Parallel()

	for _, statement := range []string{
		"ALTER TABLE pg_temp.log_event_item RENAME COLUMN item_id TO broken",
		"ALTER TABLE pg_temp.log_event_picture RENAME COLUMN picture_id TO broken",
		"INSERT INTO pg_temp.log_event_item VALUES (65,NULL)",
		"INSERT INTO pg_temp.log_event_picture VALUES (65,NULL)",
	} {
		t.Run(statement, func(t *testing.T) {
			t.Parallel()
			repository, db, pool := newLogEventsBatchRepository(t)
			_, err := pool.ExecContext(t.Context(), statement)
			require.NoError(t, err)

			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			empty, pages, err := repository.Events(t.Context(), eventlog.ListOptions{UserID: 999})
			require.NoError(t, err)
			require.Empty(t, empty)
			require.NotNil(t, pages)
			require.Zero(t, pages.TotalItemCount)
			require.EqualValues(t, 2, logger.queries.Load(), "empty pages do not read related tables")
			actual, pages, err := repository.Events(t.Context(), eventlog.ListOptions{})
			require.Error(t, err)
			require.Nil(t, actual)
			require.Nil(t, pages)
		})
	}

	repository, _, _ := newLogEventsBatchRepository(t)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	actual, pages, err := repository.Events(ctx, eventlog.ListOptions{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, actual)
	require.Nil(t, pages)
}
