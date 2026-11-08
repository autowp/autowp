package traffic

import (
	"context"
	"net"
	"slices"
	"sync"
	"testing"
	"testing/synctest"
	"time"

	"github.com/stretchr/testify/require"
)

type fakeCountWriter struct {
	mutex  sync.Mutex
	counts []MonitoringCount
}

func (writer *fakeCountWriter) AddCounts(_ context.Context, counts []MonitoringCount) error {
	writer.mutex.Lock()
	defer writer.mutex.Unlock()

	writer.counts = append(writer.counts, counts...)

	return nil
}

func (writer *fakeCountWriter) snapshot() []MonitoringCount {
	writer.mutex.Lock()
	defer writer.mutex.Unlock()

	return slices.Clone(writer.counts)
}

func pendingTrafficCounts(recorder *Recorder) int {
	recorder.mutex.Lock()
	defer recorder.mutex.Unlock()

	return len(recorder.counts)
}

func TestRecorderAggregatesAndSkipsInternalPeers(t *testing.T) {
	t.Parallel()

	writer := &fakeCountWriter{}

	recorder := &Recorder{writer: writer, counts: make(map[minuteKey]int64)}

	var workers sync.WaitGroup
	for range 20 {
		workers.Go(func() {
			for range 100 {
				recorder.Record(net.ParseIP("192.0.2.1"))
			}
		})
	}

	workers.Wait()

	recorder.Record(net.IPv6loopback)
	recorder.Record(net.IPv4zero)
	recorder.Record(nil)
	recorder.flush(t.Context())

	var total int64

	for _, count := range writer.counts {
		require.Equal(t, "192.0.2.1", count.IP)
		total += count.Count
	}

	require.EqualValues(t, 2000, total)
	require.Empty(t, recorder.counts)
	recorder.flush(t.Context())

	var after int64
	for _, count := range writer.counts {
		after += count.Count
	}

	require.Equal(t, total, after)
}

func TestRecorderFlushesOnTimerAndShutdown(t *testing.T) {
	t.Parallel()
	synctest.Test(t, func(t *testing.T) {
		ctx, cancel := context.WithCancel(t.Context())
		defer cancel()

		writer := &fakeCountWriter{}
		recorder := NewRecorder(ctx, writer)

		synctest.Wait()
		recorder.Record(net.ParseIP("40.77.167.1"))
		time.Sleep(recorderFlushInterval)
		synctest.Wait()
		require.Len(t, writer.snapshot(), 1)
		recorder.Record(net.ParseIP("40.77.167.2"))
		cancel()
		synctest.Wait()
		require.Len(t, writer.snapshot(), 2)
		require.Zero(t, pendingTrafficCounts(recorder))
	})
}
