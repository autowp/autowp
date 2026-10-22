package traffic

import (
	"context"
	"net"
	"sync"
	"time"

	"github.com/autowp/goautowp/logging"
)

const (
	recorderMaxBuckets    = 10000
	recorderBatchSize     = 500
	recorderFlushInterval = 5 * time.Second
)

type countWriter interface {
	AddCounts(ctx context.Context, counts []MonitoringCount) error
}

type whitelistReader interface {
	IPSet(ctx context.Context) (map[string]struct{}, error)
}

type minuteKey struct {
	ip     string
	minute time.Time
}

// Recorder keeps request handling independent of database latency. Its memory is
// bounded; overload and failed writes are reported instead of blocking visitors.
type Recorder struct {
	mutex       sync.Mutex
	counts      map[minuteKey]int64
	dropped     int64
	writer      countWriter
	whitelist   whitelistReader
	whitelisted map[string]struct{}
}

func NewRecorder(ctx context.Context, writer countWriter, whitelist whitelistReader) *Recorder {
	r := &Recorder{writer: writer, whitelist: whitelist, counts: make(map[minuteKey]int64)}

	r.refreshWhitelist(ctx)
	go r.run(ctx)

	return r
}

func (r *Recorder) Record(ip net.IP) {
	if ip == nil || ip.IsLoopback() || ip.IsUnspecified() {
		return
	}

	address := ip.String()

	r.mutex.Lock()
	defer r.mutex.Unlock()

	if _, exempt := r.whitelisted[address]; exempt {
		return
	}

	key := minuteKey{ip: address, minute: time.Now().UTC().Truncate(time.Minute)}

	if _, exists := r.counts[key]; !exists && len(r.counts) >= recorderMaxBuckets {
		r.dropped++

		return
	}

	r.counts[key]++
}

// Refresh once per flush interval, retaining the last good snapshot on database errors.
func (r *Recorder) refreshWhitelist(parent context.Context) {
	ctx, cancel := context.WithTimeout(parent, recorderFlushInterval)
	defer cancel()

	snapshot, err := r.whitelist.IPSet(ctx)
	if err != nil {
		logging.Errorf("traffic monitoring: whitelist refresh failed: %v", err)

		return
	}

	r.mutex.Lock()
	defer r.mutex.Unlock()

	r.whitelisted = snapshot
	for key := range r.counts {
		if _, exempt := snapshot[key.ip]; exempt {
			delete(r.counts, key)
		}
	}
}

func (r *Recorder) flush(parent context.Context) {
	r.mutex.Lock()
	counts, dropped := r.counts, r.dropped
	r.counts, r.dropped = make(map[minuteKey]int64), 0
	r.mutex.Unlock()

	if dropped > 0 {
		logging.Errorf("traffic monitoring: dropped %d requests: buffer full", dropped)
	}

	batch := make([]MonitoringCount, 0, recorderBatchSize)

	ctx, cancel := context.WithTimeout(parent, recorderFlushInterval)
	defer cancel()

	write := func() {
		if err := r.writer.AddCounts(ctx, batch); err != nil {
			logging.Errorf("traffic monitoring: lost %d minute counters: %v", len(batch), err)
		}

		batch = batch[:0]
	}

	for key, count := range counts {
		batch = append(batch, MonitoringCount{IP: key.ip, Timestamp: key.minute, Count: count})
		if len(batch) == cap(batch) {
			write()
		}
	}

	if len(batch) > 0 {
		write()
	}
}

func (r *Recorder) run(ctx context.Context) {
	ticker := time.NewTicker(recorderFlushInterval)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			r.refreshWhitelist(ctx)
			r.flush(ctx)
		case <-ctx.Done():
			r.flush(context.WithoutCancel(ctx))

			return
		}
	}
}
