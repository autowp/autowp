package goautowp

import (
	"database/sql"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/attrs"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

type itemBatchQueryLogger struct {
	queries     atomic.Int64
	beforeQuery func()
}

func (s *itemBatchQueryLogger) Printf(_ string, _ ...interface{}) {
	s.queries.Add(1)

	if s.beforeQuery != nil {
		s.beforeQuery()
	}
}

func newItemBatchTestExtractor(t *testing.T) (*itemExtractorFixture, *itemBatchQueryLogger) {
	t.Helper()

	cfg := config.LoadConfig(".")
	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	_, err = database.ExecContext(t.Context(), `
		CREATE TEMP TABLE item_language (item_id bigint, language text, name text, full_text_id bigint);
		CREATE TEMP TABLE textstorage_text (id bigint, text text);
		CREATE TEMP TABLE item_link (item_id bigint);
		CREATE TEMP TABLE user_item_subscribe (item_id bigint, user_id bigint);
		CREATE TEMP TABLE attrs_values (item_id bigint, attribute_id bigint);
		CREATE TEMP TABLE comment_topic (item_id bigint, type_id integer, messages integer);
		INSERT INTO textstorage_text VALUES (1, 'Full text'), (2, ''), (3, NULL);
		INSERT INTO item_language VALUES
			(1, 'xx', 'First', 2), (1, 'en', 'First EN', 1), (1, 'ru', 'First RU', 1),
			(2, 'xx', 'Second', 2), (2, 'ru', 'Second RU', 3), (100, 'xx', 'Outside', 1);
		INSERT INTO item_link VALUES (1), (1), (2), (100);
		INSERT INTO user_item_subscribe VALUES (1, 7), (2, 8), (100, 7);
		INSERT INTO attrs_values VALUES (1, 11), (1, 12), (2, 11), (100, 11);
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO comment_topic VALUES
		(1, $1, 12), (2, $1, 4), (100, $1, 99), (1, $2, 500)`,
		schema.CommentMessageTypeIDItems, schema.CommentMessageTypeIDPictures)
	require.NoError(t, err)

	logger := &itemBatchQueryLogger{}
	goquDB := goqu.New("postgres", database)
	goquDB.Logger(logger)
	container := &Container{
		itemsRepository:    items.NewRepository(goquDB, 0, nil, nil, nil),
		attrsRepository:    attrs.NewRepository(goquDB, nil, nil, nil, nil, nil, nil),
		commentsRepository: comments.NewRepository(goquDB, nil, nil, nil, nil),
	}

	return newItemExtractorFixture(container), logger
}

func blockItemExtractionQueries(t *testing.T, logger *itemBatchQueryLogger, count int) func() {
	t.Helper()

	started := make(chan struct{}, count)
	release := make(chan struct{})

	var once sync.Once

	unblock := func() { once.Do(func() { close(release) }) }
	t.Cleanup(unblock)

	var observed atomic.Int64

	logger.beforeQuery = func() {
		if observed.Add(1) > int64(count) {
			return
		}

		started <- struct{}{}

		<-release
	}

	return func() {
		t.Helper()

		defer unblock()

		timer := time.NewTimer(5 * time.Second)
		defer timer.Stop()

		for range count {
			select {
			case <-started:
			case <-timer.C:
				t.Fatal("independent item lookups did not start together")
			}
		}
	}
}
