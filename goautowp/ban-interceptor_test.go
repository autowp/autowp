package goautowp

import (
	"context"
	"errors"
	"net"
	"sync"
	"testing"
	"time"

	"github.com/stretchr/testify/require"
)

type fakeBanRepository struct {
	mutex  sync.Mutex
	calls  int
	banned bool
	err    error
}

func (s *fakeBanRepository) Exists(_ context.Context, _ net.IP) (bool, error) {
	s.mutex.Lock()
	defer s.mutex.Unlock()

	s.calls++

	return s.banned, s.err
}

func (s *fakeBanRepository) callCount() int {
	s.mutex.Lock()
	defer s.mutex.Unlock()

	return s.calls
}

func TestBanCheckerSkipsAddressesThatCannotBeClients(t *testing.T) {
	t.Parallel()

	repository := &fakeBanRepository{banned: true}
	checker := NewBanChecker(repository)

	// A loopback peer is the SSR frontend container sharing this pod's network namespace, not a
	// visitor - checking it costs a query per server-side rendered gRPC call and can only ever
	// answer "not banned".
	for _, ip := range []net.IP{nil, net.ParseIP("127.0.0.1"), net.ParseIP("::1"), net.IPv4zero} {
		require.False(t, checker.IsBanned(t.Context(), ip))
	}

	require.Zero(t, repository.callCount())
}

func TestBanCheckerCachesLookupsPerAddress(t *testing.T) {
	t.Parallel()

	repository := &fakeBanRepository{banned: true}
	checker := NewBanChecker(repository)

	for range 5 {
		require.True(t, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.1")))
	}

	require.Equal(t, 1, repository.callCount())

	// A different address is a different cache key, so it costs its own single lookup.
	require.True(t, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.2")))
	require.Equal(t, 2, repository.callCount())
}

func TestBanCheckerRefetchesAfterTTL(t *testing.T) {
	t.Parallel()

	repository := &fakeBanRepository{banned: true}
	checker := NewBanChecker(repository)
	checker.ttl = time.Nanosecond

	require.True(t, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.3")))
	time.Sleep(time.Millisecond)
	require.True(t, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.3")))

	require.Equal(t, 2, repository.callCount())
}

func TestBanCheckerTreatsLookupFailureAsNotBannedAndDoesNotCacheIt(t *testing.T) {
	t.Parallel()

	repository := &fakeBanRepository{banned: true, err: errors.New("database is down")} //nolint: err113

	checker := NewBanChecker(repository)

	require.False(t, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.4")))

	repository.mutex.Lock()
	repository.err = nil
	repository.mutex.Unlock()

	require.True(t, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.4")))
	require.Equal(t, 2, repository.callCount())
}
