package items

import (
	"context"
	"database/sql"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/i18nbundle"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/textstorage"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
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

func newRelatedGroupTestDB(t *testing.T) (*goqu.Database, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	database, err := sql.Open("postgres", config.LoadConfig("../../../..").PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	logger := &itemBatchQueryLogger{}
	db := goqu.New("postgres", database)
	db.Logger(logger)

	return db, database, logger
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

func newTestServer(t *testing.T, db *goqu.Database) *Server {
	t.Helper()

	cfg := config.LoadConfig("../../../..")
	bundle, err := i18nbundle.New()
	require.NoError(t, err)

	texts := textstorage.New(db)
	parentLanguage := items.NewItemParentLanguageRepository(db, cfg.ContentLanguages)
	repository := items.NewRepository(db, cfg.MostsMinCarsCount, parentLanguage, texts, nil)

	return &Server{
		repository: repository,
		i18n:       bundle,
		picturesRepository: pictures.NewRepository(
			db,
			nil,
			texts,
			repository,
			nil,
			cfg.DuplicateFinder,
			nil,
			nil,
			nil,
			nil,
		),
	}
}

func createTestItem(t *testing.T, server *Server, db *goqu.Database, item *pb.Item) int64 {
	t.Helper()

	catname := item.GetCatname()
	if catname == "" {
		catname = "items-server-" + uuid.NewString()
	}

	id, err := server.repository.CreateItem(t.Context(), schema.ItemRow{
		ItemTypeID: itemmapper.TypeFromProto(item.GetItemTypeId()),
		Catname:    sql.NullString{String: catname, Valid: true},
		IsGroup:    item.GetIsGroup(), IsConcept: item.GetIsConcept(),
		BeginYear: sql.NullInt32{Int32: item.GetBeginYear(), Valid: item.GetBeginYear() != 0},
		EndYear:   sql.NullInt32{Int32: item.GetEndYear(), Valid: item.GetEndYear() != 0},
	}, item.GetName(), 0)
	require.NoError(t, err)
	t.Cleanup(func() {
		db.Logger(nil)

		ctx, cancel := context.WithTimeout(context.WithoutCancel(t.Context()), 10*time.Second)
		defer cancel()

		_, err := db.Delete(schema.ItemParentLanguageTable).Where(goqu.Or(
			schema.ItemParentLanguageTableItemIDCol.Eq(id), schema.ItemParentLanguageTableParentIDCol.Eq(id),
		)).Executor().ExecContext(ctx)
		require.NoError(t, err)
		_, err = db.Delete(schema.ItemParentTable).Where(goqu.Or(
			schema.ItemParentTableItemIDCol.Eq(id), schema.ItemParentTableParentIDCol.Eq(id),
		)).Executor().ExecContext(ctx)
		require.NoError(t, err)
		_, err = db.Delete(goqu.S("public").Table(schema.ItemTableName)).
			Where(schema.ItemTableIDCol.Eq(id)).
			Executor().
			ExecContext(ctx)
		require.NoError(t, err)
	})

	return id
}
