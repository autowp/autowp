package pictures

import (
	"context"
	"database/sql"
	"errors"
	"maps"
	"math"
	"sync"
	"testing"
	"testing/synctest"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

var errTestViewWrite = errors.New("test view write failure")

type fakeViewCountWriter struct {
	mutex   sync.Mutex
	counts  map[int64]int64
	calls   int
	failAt  int
	onWrite func()
}

func (w *fakeViewCountWriter) addViewCounts(ctx context.Context, counts []pictureViewCount) error {
	if err := ctx.Err(); err != nil {
		return err
	}

	w.mutex.Lock()
	defer w.mutex.Unlock()

	w.calls++
	if w.calls == w.failAt {
		return errTestViewWrite
	}

	if w.onWrite != nil {
		w.onWrite()
	}

	if w.counts == nil {
		w.counts = make(map[int64]int64)
	}

	for _, count := range counts {
		w.counts[count.id] += count.views
	}

	return nil
}

func (w *fakeViewCountWriter) snapshot() (map[int64]int64, int) {
	w.mutex.Lock()
	defer w.mutex.Unlock()

	return maps.Clone(w.counts), w.calls
}

func TestViewRecorderAggregatesConcurrentViews(t *testing.T) {
	t.Parallel()

	writer := &fakeViewCountWriter{}
	recorder := newViewRecorder(writer)

	var workers sync.WaitGroup
	for range 20 {
		workers.Go(func() {
			for range 100 {
				if err := recorder.record(1); err != nil {
					t.Error(err)
				}
			}
		})
	}

	workers.Wait()
	require.Zero(t, writer.calls)
	require.NoError(t, recorder.flush(t.Context()))
	require.Equal(t, map[int64]int64{1: 2000}, writer.counts)
	require.Equal(t, 1, writer.calls)
	require.NoError(t, recorder.flush(t.Context()))
	require.Equal(t, 1, writer.calls)
}

func TestViewRecorderBoundsMemoryAndRejectsInvalidIDs(t *testing.T) {
	t.Parallel()

	recorder := newViewRecorder(&fakeViewCountWriter{})
	for _, id := range []int64{-1, 0, math.MaxInt64} {
		require.ErrorIs(t, recorder.record(id), errInvalidViewPictureID)
	}

	for id := int64(1); id <= viewRecorderMaxPictures; id++ {
		require.NoError(t, recorder.record(id))
	}

	require.ErrorIs(t, recorder.record(viewRecorderMaxPictures+1), errViewBufferFull)
	require.NoError(t, recorder.record(1))
	require.EqualValues(t, 2, recorder.counts[1])
	require.Len(t, recorder.counts, viewRecorderMaxPictures)
}

func TestViewRecorderRetriesOnlyUnwrittenBatches(t *testing.T) {
	t.Parallel()

	writer := &fakeViewCountWriter{failAt: 2}

	recorder := newViewRecorder(writer)
	for id := int64(1); id <= viewRecorderBatchSize+1; id++ {
		require.NoError(t, recorder.record(id))
	}

	require.ErrorIs(t, recorder.flush(t.Context()), errTestViewWrite)
	require.Len(t, writer.counts, viewRecorderBatchSize)
	require.Len(t, recorder.counts, 1)
	require.NoError(t, recorder.record(viewRecorderBatchSize+1))
	require.NoError(t, recorder.flush(t.Context()))
	require.Len(t, writer.counts, viewRecorderBatchSize+1)
	require.EqualValues(t, 1, writer.counts[1])
	require.EqualValues(t, 2, writer.counts[viewRecorderBatchSize+1])
	require.Empty(t, recorder.counts)
}

func TestViewRecorderAcceptsViewsDuringFlush(t *testing.T) {
	t.Parallel()

	writer := &fakeViewCountWriter{}
	recorder := newViewRecorder(writer)
	writer.onWrite = func() { require.NoError(t, recorder.record(1)) }

	require.NoError(t, recorder.record(1))
	require.NoError(t, recorder.flush(t.Context()))
	require.EqualValues(t, 1, writer.counts[1])
	require.EqualValues(t, 1, recorder.counts[1])
}

func TestViewRecorderFlushesEveryMinuteAndOnShutdown(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		ctx, cancel := context.WithCancel(t.Context())
		defer cancel()

		writer := &fakeViewCountWriter{}

		recorder := newViewRecorder(writer)
		go recorder.run(ctx)

		synctest.Wait()
		require.NoError(t, recorder.record(1))
		time.Sleep(59 * time.Second)
		synctest.Wait()

		_, calls := writer.snapshot()
		require.Zero(t, calls)
		time.Sleep(time.Second)
		synctest.Wait()

		counts, calls := writer.snapshot()
		require.Equal(t, 1, calls)
		require.EqualValues(t, 1, counts[1])
		require.NoError(t, recorder.record(1))
		cancel()
		synctest.Wait()

		counts, calls = writer.snapshot()
		require.Equal(t, 2, calls)
		require.EqualValues(t, 2, counts[1])
	})
}

func TestBufferedPictureViewsPersistAndSkipDeletedPictures(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("..")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, db.Close()) })
	_, err = db.ExecContext(t.Context(), `
		CREATE TEMP TABLE picture (id integer PRIMARY KEY);
		CREATE TEMP TABLE picture_view (
			picture_id integer PRIMARY KEY REFERENCES picture (id) ON DELETE CASCADE,
			views integer NOT NULL
		);
		INSERT INTO picture VALUES (1), (2), (3);
		INSERT INTO picture_view VALUES (1, 10);
	`)
	require.NoError(t, err)

	repository := &Repository{db: goqu.New("postgres", db)}
	repository.viewRecorder = newViewRecorder(repository)

	requestCtx, cancelRequest := context.WithCancel(t.Context())
	defer cancelRequest()

	for _, id := range []int64{1, 1, 2, 3, 999} {
		require.NoError(t, repository.IncView(requestCtx, id))
	}

	cancelRequest()

	views, err := repository.PictureViews(t.Context(), 1)
	require.NoError(t, err)
	require.EqualValues(t, 10, views)
	_, err = db.ExecContext(t.Context(), "DELETE FROM picture WHERE id = 2")
	require.NoError(t, err)
	require.NoError(t, repository.viewRecorder.flush(t.Context()))
	counts, err := repository.PictureViewsBatch(t.Context(), []int64{1, 2, 3, 999})
	require.NoError(t, err)
	require.Equal(t, map[int64]int32{1: 12, 3: 1}, counts)
}
