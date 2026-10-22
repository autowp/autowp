package traffic

import (
	"context"
	"net"
	"sync"
	"testing"

	"github.com/stretchr/testify/require"
)

type fakeCountWriter struct{ counts []MonitoringCount }

func (writer *fakeCountWriter) AddCounts(_ context.Context, counts []MonitoringCount) error {
	writer.counts = append(writer.counts, counts...)

	return nil
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
	addresses map[string]struct{}
	calls     int
	err       error
}

func (reader *fakeWhitelistReader) IPSet(context.Context) (map[string]struct{}, error) {
	reader.calls++

	return reader.addresses, reader.err
}

func TestRecorderSkipsWhitelistedAddressesWithoutPerRequestQueries(t *testing.T) {
	t.Parallel()

	writer := &fakeCountWriter{}
	whitelist := &fakeWhitelistReader{addresses: map[string]struct{}{"192.0.2.1": {}}}
	recorder := &Recorder{writer: writer, whitelist: whitelist, counts: make(map[minuteKey]int64)}
	recorder.refreshWhitelist(t.Context())

	for range 1000 {
		recorder.Record(net.ParseIP("192.0.2.1"))
		recorder.Record(net.ParseIP("192.0.2.2"))
	}

	require.Equal(t, 1, whitelist.calls)
	require.Len(t, recorder.counts, 1)
	recorder.flush(t.Context())
	require.Len(t, writer.counts, 1)
	require.Equal(t, "192.0.2.2", writer.counts[0].IP)
	require.EqualValues(t, 1000, writer.counts[0].Count)

	recorder.Record(net.ParseIP("192.0.2.2"))

	whitelist.addresses = map[string]struct{}{"192.0.2.1": {}, "192.0.2.2": {}}

	recorder.refreshWhitelist(t.Context())
	require.Empty(t, recorder.counts)

	whitelist.addresses = map[string]struct{}{}

	recorder.refreshWhitelist(t.Context())
	recorder.Record(net.ParseIP("192.0.2.1"))
	require.Len(t, recorder.counts, 1)
}
