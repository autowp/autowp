package pictures

import (
	"context"
	"database/sql"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/i18nbundle"
	"github.com/autowp/goautowp/internal/image/storage"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/textstorage"
	"github.com/autowp/goautowp/internal/transport/grpc/pictureextractor"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/lib/pq"
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

func newNewboxTestExtractor(t *testing.T) (
	*pictureextractor.Extractor, *pictures.Repository, *goqu.Database, *sql.DB, []*schema.PictureRow,
) {
	t.Helper()

	cfg := config.LoadConfig("../../../..")
	pool, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })

	db := goqu.New("postgres", pool)
	_, err = pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE picture AS SELECT * FROM public.picture WITH NO DATA;
 ALTER TABLE pg_temp.picture ADD PRIMARY KEY (id);
 INSERT INTO picture(id,identity,status,width,height,created_at,license_id,owner_id) VALUES
 (1,'one','inbox',10,20,'2020-01-01',0,7),
 (3,'three','accepted',10,20,'2020-01-01',0,7),
 (5,'five','inbox',10,20,'2020-01-01',0,8),
 (6,'six','accepted',10,20,'2020-01-01',0,8),
 (7,'seven','accepted',10,20,'2020-01-01',0,8),
 (9,'nine','removed',10,20,'2020-01-01',0,8),
 (11,'eleven','accepted',10,20,'2020-01-01',0,8),
 (13,'thirteen','inbox',10,20,'2020-01-01',0,NULL);
 CREATE TEMP TABLE picture_item (picture_id bigint,item_id bigint,type integer);
 INSERT INTO picture_item VALUES (1,100,1),(1,200,2),(3,100,1),(3,200,2),(5,200,1),
 (6,200,1),(7,300,1),(9,300,2),(11,400,1),(13,500,1);
 CREATE TEMP TABLE picture_moder_vote (picture_id bigint,user_id bigint,vote integer);
 INSERT INTO picture_moder_vote VALUES
 (2,7,0),(3,7,1),(4,7,0),(4,8,2),(5,7,-1),(6,7,NULL),(7,7,0),(7,8,1),(99,7,0);
 CREATE TEMP TABLE comment_topic_subscribe (type_id integer,item_id bigint,user_id bigint,sent boolean);
 INSERT INTO comment_topic_subscribe VALUES (1,1,7,true),(1,2,7,false),(2,3,7,true),(1,4,8,true);
 `)
	require.NoError(t, err)

	repository := pictures.NewRepository(db, nil, nil, nil, nil, cfg.RabbitMQ, cfg.DuplicateFinder, nil, nil, nil, nil)
	images, err := storage.NewStorage(db, cfg.ImageStorage)
	require.NoError(t, err)
	bundle, err := i18nbundle.New()
	require.NoError(t, err)

	commentsRepository := comments.NewRepository(db, nil, nil, nil, nil)
	texts := textstorage.New(db)
	extractor := pictureextractor.New(pictureextractor.Dependencies{
		PicturesRepository:    func(_ context.Context) (*pictures.Repository, error) { return repository, nil },
		CommentsRepository:    func(_ context.Context) (*comments.Repository, error) { return commentsRepository, nil },
		ImageStorage:          func(_ context.Context) (*storage.Storage, error) { return images, nil },
		I18n:                  func() (*i18nbundle.I18n, error) { return bundle, nil },
		TextStorageRepository: func(_ context.Context) (*textstorage.Repository, error) { return texts, nil },
	})
	rows, _, err := repository.Pictures(t.Context(), &query.PictureListOptions{}, nil, pictures.OrderByIDAsc, false)
	require.NoError(t, err)

	rows = append(rows, rows[1], &schema.PictureRow{ID: 15, Status: schema.PictureStatusAccepted})

	return extractor, repository, db, pool, rows
}
