package goautowp

import (
	"context"
	"errors"
	"fmt"
	"net"
	"sync"
	"testing"
	"testing/synctest"
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

type controlledBanRepository struct {
	fakeBanRepository

	started chan string
	release chan struct{}
}

func (s *controlledBanRepository) Exists(ctx context.Context, ip net.IP) (bool, error) {
	banned, err := s.fakeBanRepository.Exists(ctx, ip)
	s.started <- ip.String()

	select {
	case <-s.release:
		return banned, err
	case <-ctx.Done():
		return false, ctx.Err()
	}
}

func (s *controlledBanRepository) unblock() {
	select {
	case <-s.release:
	default:
		close(s.release)
	}
}

func TestBanCheckerCombinesConcurrentMisses(t *testing.T) {
	t.Parallel()

	for _, banned := range []bool{false, true} {
		t.Run(fmt.Sprintf("banned_%t", banned), func(t *testing.T) {
			t.Parallel()

			synctest.Test(t, func(t *testing.T) {
				const callers = 64

				repository := &controlledBanRepository{
					fakeBanRepository: fakeBanRepository{banned: banned},
					started:           make(chan string, callers), release: make(chan struct{}),
				}
				defer repository.unblock()

				checker := NewBanChecker(repository)

				results := make(chan bool, callers)
				for range callers {
					go func() { results <- checker.IsBanned(t.Context(), net.ParseIP("192.0.2.10")) }()
				}

				synctest.Wait()
				require.Equal(t, 1, repository.callCount())
				repository.unblock()
				synctest.Wait()

				for range callers {
					require.Equal(t, banned, <-results)
				}

				require.Equal(t, banned, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.10")))
				require.Equal(t, 1, repository.callCount())
			})
		})
	}
}

func TestBanCheckerChecksDifferentAddressesIndependently(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		repository := &controlledBanRepository{
			fakeBanRepository: fakeBanRepository{banned: true},
			started:           make(chan string, 2), release: make(chan struct{}),
		}
		checker := NewBanChecker(repository)
		results := make(chan bool, 2)

		defer repository.unblock()

		for _, address := range []string{"192.0.2.10", "192.0.2.11"} {
			go func() { results <- checker.IsBanned(t.Context(), net.ParseIP(address)) }()
		}

		synctest.Wait()
		require.Equal(t, 2, repository.callCount())
		require.Len(t, repository.started, 2)
		repository.unblock()
		synctest.Wait()
		require.True(t, <-results)
		require.True(t, <-results)
	})
}

func TestBanCheckerWaitersCanCancelIndependently(t *testing.T) {
	t.Parallel()

	for _, cancelLeader := range []bool{true, false} {
		t.Run(fmt.Sprintf("cancel_leader_%t", cancelLeader), func(t *testing.T) {
			t.Parallel()

			synctest.Test(t, func(t *testing.T) {
				repository := &controlledBanRepository{
					fakeBanRepository: fakeBanRepository{banned: true},
					started:           make(chan string, 2), release: make(chan struct{}),
				}

				checker := NewBanChecker(repository)
				defer repository.unblock()

				cancelCtx, cancel := context.WithCancel(t.Context())
				defer cancel()

				leaderCtx, followerCtx := t.Context(), cancelCtx
				if cancelLeader {
					leaderCtx, followerCtx = cancelCtx, t.Context()
				}

				leader := make(chan bool, 1)
				follower := make(chan bool, 1)

				go func() { leader <- checker.IsBanned(leaderCtx, net.ParseIP("192.0.2.10")) }()

				synctest.Wait()

				go func() { follower <- checker.IsBanned(followerCtx, net.ParseIP("192.0.2.10")) }()

				synctest.Wait()
				cancel()
				synctest.Wait()

				if cancelLeader {
					require.False(t, <-leader)
				} else {
					require.False(t, <-follower)
				}

				require.Equal(t, 1, repository.callCount())
				repository.unblock()
				synctest.Wait()

				if cancelLeader {
					require.True(t, <-follower)
				} else {
					require.True(t, <-leader)
				}

				require.True(t, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.10")))
				require.Equal(t, 1, repository.callCount())
			})
		})
	}
}

func TestBanCheckerBoundsSharedLookupAndDoesNotCacheTimeout(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		repository := &controlledBanRepository{
			fakeBanRepository: fakeBanRepository{banned: true},
			started:           make(chan string, 2), release: make(chan struct{}),
		}
		checker := NewBanChecker(repository)
		result := make(chan bool, 1)

		defer repository.unblock()

		go func() { result <- checker.IsBanned(t.Context(), net.ParseIP("192.0.2.10")) }()

		synctest.Wait()
		time.Sleep(banLookupTimeout)
		synctest.Wait()
		require.False(t, <-result)
		repository.unblock()
		require.True(t, checker.IsBanned(t.Context(), net.ParseIP("192.0.2.10")))
		require.Equal(t, 2, repository.callCount())
	})
}

func TestBanCheckerSkipsLookupForCanceledCaller(t *testing.T) {
	t.Parallel()

	repository := &fakeBanRepository{banned: true}
	checker := NewBanChecker(repository)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()
	require.False(t, checker.IsBanned(ctx, net.ParseIP("192.0.2.10")))
	require.Zero(t, repository.callCount())
}
