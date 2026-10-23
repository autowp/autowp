package traffic

import (
	"context"
	"errors"
	"net"
	"slices"
	"sync"
	"testing"
	"testing/synctest"
	"time"

	"github.com/stretchr/testify/require"
)

var errTestWhitelistRead = errors.New("database unavailable")

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

type fakeWhitelistReader struct {
	mutex     sync.Mutex
	addresses map[string]struct{}
	calls     int
	err       error
	delay     time.Duration
}

func (reader *fakeWhitelistReader) IPSet(ctx context.Context) (map[string]struct{}, error) {
	reader.mutex.Lock()
	reader.calls++
	addresses, err, delay := reader.addresses, reader.err, reader.delay
	reader.mutex.Unlock()

	if delay > 0 {
		timer := time.NewTimer(delay)
		defer timer.Stop()

		select {
		case <-timer.C:
		case <-ctx.Done():
			return nil, ctx.Err()
		}
	}

	return addresses, err
}

func (reader *fakeWhitelistReader) callCount() int {
	reader.mutex.Lock()
	defer reader.mutex.Unlock()

	return reader.calls
}

func (reader *fakeWhitelistReader) setAddresses(addresses map[string]struct{}) {
	reader.mutex.Lock()
	defer reader.mutex.Unlock()

	reader.addresses = addresses
}

func TestRecorderRefreshesWhitelistOncePerMinute(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		ctx, cancel := context.WithCancel(t.Context())
		defer cancel()

		writer := &fakeCountWriter{}
		whitelist := &fakeWhitelistReader{addresses: map[string]struct{}{"192.0.2.1": {}}}
		recorder := NewRecorder(ctx, writer, whitelist)

		synctest.Wait()
		require.Equal(t, 1, whitelist.callCount())

		recorder.Record(net.ParseIP("192.0.2.2"))
		time.Sleep(59 * time.Second)
		synctest.Wait()
		require.Equal(t, 1, whitelist.callCount())
		require.Len(t, writer.snapshot(), 1)

		whitelist.setAddresses(map[string]struct{}{"192.0.2.1": {}, "192.0.2.2": {}})
		time.Sleep(time.Second)
		synctest.Wait()
		require.Equal(t, 2, whitelist.callCount())
		recorder.Record(net.ParseIP("192.0.2.2"))
		require.Zero(t, pendingTrafficCounts(recorder))

		whitelist.setAddresses(map[string]struct{}{})
		time.Sleep(time.Minute)
		synctest.Wait()
		require.Equal(t, 3, whitelist.callCount())
		recorder.Record(net.ParseIP("192.0.2.2"))
		require.Equal(t, 1, pendingTrafficCounts(recorder))
	})
}

func TestRecorderFlushDoesNotWaitForWhitelistRefresh(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		ctx, cancel := context.WithCancel(t.Context())
		defer cancel()

		writer := &fakeCountWriter{}
		whitelist := &fakeWhitelistReader{}
		recorder := NewRecorder(ctx, writer, whitelist)

		synctest.Wait()
		whitelist.mutex.Lock()
		whitelist.delay = 4 * time.Second
		whitelist.mutex.Unlock()
		time.Sleep(59 * time.Second)
		recorder.Record(net.ParseIP("192.0.2.2"))
		time.Sleep(time.Second)
		synctest.Wait()
		require.Equal(t, 2, whitelist.callCount())
		require.Len(t, writer.snapshot(), 1)
	})
}

func TestRecorderRetainsWhitelistOnRefreshError(t *testing.T) {
	t.Parallel()

	whitelist := &fakeWhitelistReader{addresses: map[string]struct{}{"192.0.2.1": {}}}
	recorder := &Recorder{whitelist: whitelist, counts: make(map[minuteKey]int64)}
	recorder.refreshWhitelist(t.Context())

	whitelist.addresses = nil
	whitelist.err = errTestWhitelistRead

	recorder.refreshWhitelist(t.Context())
	recorder.Record(net.ParseIP("192.0.2.1"))
	require.Empty(t, recorder.counts)
}

func TestRecorderSkipsWhitelistedAddressesWithoutPerRequestQueries(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		writer := &fakeCountWriter{}
		whitelist := &fakeWhitelistReader{addresses: map[string]struct{}{"192.0.2.1": {}}}
		recorder := &Recorder{writer: writer, whitelist: whitelist, counts: make(map[minuteKey]int64)}
		recorder.refreshWhitelist(t.Context())

		for i := range 1000 {
			if i == 500 {
				time.Sleep(time.Minute)
			}

			recorder.Record(net.ParseIP("192.0.2.1"))
			recorder.Record(net.ParseIP("192.0.2.2"))
		}

		require.Equal(t, 1, whitelist.calls)
		require.Len(t, recorder.counts, 2)

		for key := range recorder.counts {
			require.Equal(t, "192.0.2.2", key.ip)
		}

		recorder.flush(t.Context())

		var total int64

		for _, count := range writer.counts {
			require.Equal(t, "192.0.2.2", count.IP)
			total += count.Count
		}

		require.EqualValues(t, 1000, total)

		recorder.Record(net.ParseIP("192.0.2.2"))

		whitelist.addresses = map[string]struct{}{"192.0.2.1": {}, "192.0.2.2": {}}

		recorder.refreshWhitelist(t.Context())
		require.Empty(t, recorder.counts)

		whitelist.addresses = map[string]struct{}{}

		recorder.refreshWhitelist(t.Context())
		recorder.Record(net.ParseIP("192.0.2.1"))
		require.Len(t, recorder.counts, 1)
	})
}
