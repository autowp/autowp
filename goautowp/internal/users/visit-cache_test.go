package users

import (
	"context"
	"errors"
	"fmt"
	"sync"
	"sync/atomic"
	"testing"
	"testing/synctest"
	"time"

	"github.com/stretchr/testify/require"
)

var errVisitCacheTest = errors.New("visit cache test failure")

func TestVisitCacheDeadlineAndIPChanges(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		cache := &visitCache{}
		calls := 0
		visit := func(context.Context) (time.Time, error) {
			calls++

			return time.Now().Add(lastOnlineUpdateThreshold), nil
		}
		require.NoError(t, cache.register(t.Context(), 1, "A", visit))
		require.NoError(t, cache.register(t.Context(), 1, "A", visit))
		require.Equal(t, 1, calls)
		time.Sleep(4 * time.Second)
		require.NoError(t, cache.register(t.Context(), 1, "A", visit))
		require.Equal(t, 1, calls)
		time.Sleep(time.Second)
		require.NoError(t, cache.register(t.Context(), 1, "A", visit))
		require.Equal(t, 2, calls, "cache hits must not extend the deadline")
		require.NoError(t, cache.register(t.Context(), 1, "B", visit))
		require.NoError(t, cache.register(t.Context(), 1, "A", visit))
		require.Equal(t, 4, calls, "A -> B -> A must register both changes")

		ctx, cancel := context.WithCancel(t.Context())
		cancel()
		require.ErrorIs(t, cache.register(ctx, 1, "A", visit), context.Canceled)
		require.Equal(t, 4, calls)
	})
}

func TestVisitCacheOnlyCachesSuccessfulExistingVisits(t *testing.T) {
	t.Parallel()

	for _, visitErr := range []error{nil, errVisitCacheTest, context.Canceled} {
		t.Run(fmt.Sprint(visitErr), func(t *testing.T) {
			t.Parallel()

			cache := &visitCache{}
			calls := 0
			visit := func(context.Context) (time.Time, error) {
				calls++

				if visitErr == nil {
					return time.Time{}, nil // The user does not exist yet.
				}

				return time.Now().Add(lastOnlineUpdateThreshold), visitErr
			}

			for range 2 {
				require.ErrorIs(t, cache.register(t.Context(), 1, "A", visit), visitErr)
			}

			require.Equal(t, 2, calls)
			require.Empty(t, cache.entries)
		})
	}
}

func TestVisitCacheCombinesConcurrentVisits(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		const callers = 64

		cache := &visitCache{}

		var calls atomic.Int32

		release := make(chan struct{})

		unblock := sync.OnceFunc(func() { close(release) })
		defer unblock()

		visit := func(ctx context.Context) (time.Time, error) {
			calls.Add(1)

			select {
			case <-release:
				return time.Now().Add(lastOnlineUpdateThreshold), nil
			case <-ctx.Done():
				return time.Time{}, ctx.Err()
			}
		}

		results := make(chan error, callers)
		for range callers {
			go func() { results <- cache.register(t.Context(), 1, "A", visit) }()
		}

		synctest.Wait()
		require.EqualValues(t, 1, calls.Load())
		unblock()
		synctest.Wait()

		for range callers {
			require.NoError(t, <-results)
		}

		require.EqualValues(t, 1, calls.Load())
	})
}

func TestVisitCacheCancellationIsIndependent(t *testing.T) {
	t.Parallel()

	for _, cancelLeader := range []bool{true, false} {
		t.Run(fmt.Sprintf("cancel_leader_%t", cancelLeader), func(t *testing.T) {
			t.Parallel()

			synctest.Test(t, func(t *testing.T) {
				cache := &visitCache{}

				var calls atomic.Int32

				release := make(chan struct{})

				unblock := sync.OnceFunc(func() { close(release) })
				defer unblock()

				visit := func(ctx context.Context) (time.Time, error) {
					if calls.Add(1) == 1 {
						select {
						case <-release:
						case <-ctx.Done():
							return time.Time{}, ctx.Err()
						}
					}

					return time.Now().Add(lastOnlineUpdateThreshold), nil
				}

				cancelCtx, cancel := context.WithCancel(t.Context())
				defer cancel()

				leaderCtx, followerCtx := t.Context(), cancelCtx
				if cancelLeader {
					leaderCtx, followerCtx = cancelCtx, t.Context()
				}

				leader, follower := make(chan error, 1), make(chan error, 1)
				go func() { leader <- cache.register(leaderCtx, 1, "A", visit) }()

				synctest.Wait()

				go func() { follower <- cache.register(followerCtx, 1, "A", visit) }()

				synctest.Wait()
				cancel()
				synctest.Wait()

				if cancelLeader {
					require.ErrorIs(t, <-leader, context.Canceled)
					require.NoError(t, <-follower)
					require.EqualValues(t, 2, calls.Load(), "follower retries after the canceled write")
				} else {
					require.ErrorIs(t, <-follower, context.Canceled)
					require.EqualValues(t, 1, calls.Load())
					unblock()
					synctest.Wait()
					require.NoError(t, <-leader)
				}
			})
		})
	}
}

func TestVisitCacheSerializesIPChanges(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		cache := &visitCache{}

		var calls atomic.Int32

		release := make(chan struct{})

		unblock := sync.OnceFunc(func() { close(release) })
		defer unblock()

		visit := func(ctx context.Context) (time.Time, error) {
			if calls.Add(1) == 2 {
				<-release
			}

			return time.Now().Add(lastOnlineUpdateThreshold), ctx.Err()
		}
		require.NoError(t, cache.register(t.Context(), 1, "A", visit))

		results := make(chan error, 2)
		go func() { results <- cache.register(t.Context(), 1, "B", visit) }()

		synctest.Wait()

		go func() { results <- cache.register(t.Context(), 1, "A", visit) }()

		synctest.Wait()
		require.Empty(t, results, "cached A must wait while B is being saved")
		require.EqualValues(t, 2, calls.Load())
		unblock()
		synctest.Wait()
		require.NoError(t, <-results)
		require.NoError(t, <-results)
		require.EqualValues(t, 3, calls.Load())
		require.Equal(t, "A", cache.entries[1].ip)
	})
}

func TestVisitCacheDifferentUsersDoNotBlockEachOther(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		cache := &visitCache{}

		var calls atomic.Int32

		release := make(chan struct{})

		unblock := sync.OnceFunc(func() { close(release) })
		defer unblock()

		visit := func(ctx context.Context) (time.Time, error) {
			calls.Add(1)
			<-release

			return time.Now().Add(lastOnlineUpdateThreshold), ctx.Err()
		}

		results := make(chan error, 2)
		go func() { results <- cache.register(t.Context(), 1, "A", visit) }()
		go func() { results <- cache.register(t.Context(), 2, "A", visit) }()

		synctest.Wait()
		require.EqualValues(t, 2, calls.Load())
		unblock()
		synctest.Wait()
		require.NoError(t, <-results)
		require.NoError(t, <-results)
	})
}

func TestVisitCacheBoundsMemoryAndRetainsPendingEntries(t *testing.T) {
	t.Parallel()

	for _, mode := range []string{"idle", "expired", "pending"} {
		t.Run(mode, func(t *testing.T) {
			t.Parallel()

			cache := &visitCache{entries: make(map[int64]*visitCacheEntry)}

			for id := int64(1); id <= visitCacheMaxEntries; id++ {
				entry := &visitCacheEntry{ip: "A", until: time.Now().Add(time.Minute)}
				if mode == "expired" {
					entry.until = time.Now().Add(-time.Second)
				}

				if id == 1 || mode == "pending" {
					entry.pending = make(chan struct{})
				}

				cache.entries[id] = entry
			}

			calls := 0
			visit := func(context.Context) (time.Time, error) {
				calls++

				return time.Now().Add(lastOnlineUpdateThreshold), nil
			}
			require.NoError(t, cache.register(t.Context(), visitCacheMaxEntries+1, "B", visit))
			require.Equal(t, 1, calls)
			require.LessOrEqual(t, len(cache.entries), visitCacheMaxEntries)
			require.NotNil(t, cache.entries[1].pending)

			if mode == "pending" {
				require.NotContains(t, cache.entries, int64(visitCacheMaxEntries+1))
			} else {
				require.Contains(t, cache.entries, int64(visitCacheMaxEntries+1))
			}
		})
	}
}

func TestVisitCacheUsesExistingDeadlineAndReleasesAfterPanic(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		cache := &visitCache{}
		calls := 0
		deadline := time.Now().Add(time.Second)
		visit := func(ctx context.Context) (time.Time, error) {
			calls++

			return deadline, ctx.Err()
		}
		require.NoError(t, cache.register(t.Context(), 1, "A", visit))
		time.Sleep(time.Second)
		require.NoError(t, cache.register(t.Context(), 1, "A", visit))
		require.Equal(t, 2, calls, "a recent database timestamp expires before a fresh five-second interval")
		require.Empty(t, cache.entries, "an already expired deadline cannot suppress the next visit")

		require.Panics(t, func() {
			_ = cache.register(t.Context(), 1, "A", func(context.Context) (time.Time, error) {
				panic(errVisitCacheTest)
			})
		})
		require.Empty(t, cache.entries)

		deadline = time.Now().Add(lastOnlineUpdateThreshold)

		require.NoError(t, cache.register(t.Context(), 1, "A", visit))
		require.Equal(t, 3, calls)
	})
}
