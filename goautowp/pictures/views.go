package pictures

import (
	"context"
	"errors"
	"maps"
	"math"
	"slices"
	"strings"
	"sync"
	"time"

	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

const (
	viewRecorderMaxPictures   = 10000
	viewRecorderBatchSize     = 500
	viewRecorderFlushInterval = time.Minute
	viewRecorderWriteTimeout  = 5 * time.Second
)

var (
	errInvalidViewPictureID = errors.New("invalid picture id for view")
	errViewBufferFull       = errors.New("picture view buffer is full")
)

type pictureViewCount struct {
	id    int64
	views int64
}

type viewCountWriter interface {
	addViewCounts(ctx context.Context, counts []pictureViewCount) error
}

type viewRecorder struct {
	mutex  sync.Mutex
	counts map[int64]int64
	writer viewCountWriter
}

func newViewRecorder(writer viewCountWriter) *viewRecorder {
	return &viewRecorder{counts: make(map[int64]int64), writer: writer}
}

func (r *viewRecorder) record(id int64) error {
	if id <= 0 || id > math.MaxInt32 {
		return errInvalidViewPictureID
	}

	r.mutex.Lock()
	defer r.mutex.Unlock()

	if _, exists := r.counts[id]; !exists && len(r.counts) >= viewRecorderMaxPictures {
		return errViewBufferFull
	}

	r.counts[id]++

	return nil
}

func (r *viewRecorder) flush(parent context.Context) error {
	r.mutex.Lock()
	counts := r.counts
	r.counts = make(map[int64]int64)
	r.mutex.Unlock()

	ids := slices.Sorted(maps.Keys(counts))

	ctx, cancel := context.WithTimeout(parent, viewRecorderWriteTimeout)
	defer cancel()

	for offset := 0; offset < len(ids); offset += viewRecorderBatchSize {
		batchIDs := ids[offset:min(offset+viewRecorderBatchSize, len(ids))]

		batch := make([]pictureViewCount, 0, len(batchIDs))
		for _, id := range batchIDs {
			batch = append(batch, pictureViewCount{id: id, views: counts[id]})
		}

		if err := r.writer.addViewCounts(ctx, batch); err != nil {
			r.restore(counts, ids[offset:])

			return err
		}
	}

	return nil
}

func (r *viewRecorder) restore(counts map[int64]int64, ids []int64) {
	var dropped int64

	r.mutex.Lock()
	for _, id := range ids {
		if _, exists := r.counts[id]; !exists && len(r.counts) >= viewRecorderMaxPictures {
			dropped += counts[id]

			continue
		}

		r.counts[id] += counts[id]
	}
	r.mutex.Unlock()

	if dropped > 0 {
		logging.Errorf("picture views: dropped %d views while restoring a full buffer", dropped)
	}
}

func (r *viewRecorder) run(ctx context.Context) {
	ticker := time.NewTicker(viewRecorderFlushInterval)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			if err := r.flush(ctx); err != nil {
				logging.Errorf("picture views: flush failed: %v", err)
			}
		case <-ctx.Done():
			if err := r.flush(context.WithoutCancel(ctx)); err != nil {
				logging.Errorf("picture views: final flush failed: %v", err)
			}

			return
		}
	}
}

// RunViewRecorder persists buffered views until shutdown and flushes the final batch.
func (s *Repository) RunViewRecorder(ctx context.Context) {
	s.viewRecorder.run(ctx)
}

func (s *Repository) addViewCounts(ctx context.Context, counts []pictureViewCount) error {
	if len(counts) == 0 {
		return nil
	}

	const fieldsPerCount = 2

	rows := make([]string, 0, len(counts))

	values := make([]interface{}, 0, len(counts)*fieldsPerCount)
	for _, count := range counts {
		rows = append(rows, "(?::integer, ?::integer)")
		values = append(values, count.id, count.views)
	}

	incoming := goqu.T("incoming")
	selectCounts := s.db.From(goqu.L(
		"(VALUES "+strings.Join(rows, ",")+") AS incoming(picture_id, views)", values...,
	)).InnerJoin(schema.PictureTable, goqu.On(
		schema.PictureTableIDCol.Eq(incoming.Col(schema.PictureViewTablePictureIDColName)),
	)).Select(schema.PictureTableIDCol, incoming.Col(schema.PictureViewTableViewsColName)).
		Order(schema.PictureTableIDCol.Asc()).
		ForKeyShare(goqu.Wait, schema.PictureTable)

	_, err := s.db.Insert(schema.PictureViewTable).Cols(
		schema.PictureViewTablePictureIDColName, schema.PictureViewTableViewsColName,
	).FromQuery(selectCounts).OnConflict(goqu.DoUpdate(schema.PictureViewTablePictureIDColName, goqu.Record{
		schema.PictureViewTableViewsColName: goqu.L("? + ?",
			schema.PictureViewTableViewsCol, schema.Excluded(schema.PictureViewTableViewsColName)),
	})).Executor().ExecContext(ctx)

	return err
}
