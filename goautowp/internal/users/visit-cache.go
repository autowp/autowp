package users

import (
	"context"
	"sync"
	"time"
)

const visitCacheMaxEntries = 10000

type visitCacheEntry struct {
	ip      string
	until   time.Time
	pending chan struct{}
}

// visitCache suppresses only visits already checked successfully. One entry per
// user tracks the latest IP, so switching A -> B -> A cannot reuse a stale A hit.
// Pending checks serialize visits for that user without holding the global lock
// during database work. A canceled leader releases waiters to retry independently.
type visitCache struct {
	mutex   sync.Mutex
	entries map[int64]*visitCacheEntry
}

func (s *visitCache) register(
	ctx context.Context, userID int64, ip string, visit func(context.Context) (time.Time, error),
) error {
	for {
		if err := ctx.Err(); err != nil {
			return err
		}

		s.mutex.Lock()

		entry := s.entries[userID]
		if entry != nil && entry.pending != nil {
			pending := entry.pending
			s.mutex.Unlock()

			select {
			case <-ctx.Done():
				return ctx.Err()
			case <-pending:
				continue
			}
		}

		now := time.Now()
		if entry != nil && entry.ip == ip && now.Before(entry.until) {
			s.mutex.Unlock()

			return nil
		}

		if entry == nil {
			s.makeRoom(now)

			if len(s.entries) >= visitCacheMaxEntries {
				// All slots are in flight. Keep memory bounded; the database path
				// remains available even when this optimization is saturated.
				s.mutex.Unlock()

				_, err := visit(ctx)

				return err
			}

			if s.entries == nil {
				s.entries = make(map[int64]*visitCacheEntry)
			}

			entry = &visitCacheEntry{}
			s.entries[userID] = entry
		}

		entry.pending = make(chan struct{})
		s.mutex.Unlock()

		return s.check(ctx, userID, ip, entry, visit)
	}
}

func (s *visitCache) check(
	ctx context.Context, userID int64, ip string, entry *visitCacheEntry,
	visit func(context.Context) (time.Time, error),
) error {
	var (
		until time.Time
		err   error
	)

	// Release waiters even if the database callback panics and an outer request
	// interceptor recovers it. A failed or incomplete check is never cached.
	defer func() {
		s.mutex.Lock()
		defer s.mutex.Unlock()

		if err == nil && time.Now().Before(until) {
			entry.ip = ip
			entry.until = until
		} else {
			delete(s.entries, userID)
		}

		close(entry.pending)
		entry.pending = nil
	}()

	until, err = visit(ctx)

	return err
}

// Called only under mutex. Never evict pending checks, or a second writer could
// start for the same user while the first one is still saving their IP.
func (s *visitCache) makeRoom(now time.Time) {
	if len(s.entries) < visitCacheMaxEntries {
		return
	}

	for id, entry := range s.entries {
		if entry.pending == nil && !now.Before(entry.until) {
			delete(s.entries, id)
		}
	}

	if len(s.entries) < visitCacheMaxEntries {
		return
	}

	for id, entry := range s.entries {
		if entry.pending == nil {
			delete(s.entries, id)

			return
		}
	}
}
