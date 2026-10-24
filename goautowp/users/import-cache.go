package users

import (
	"context"
	"time"
)

// synchronize runs under the per-UUID singleflight, including cache hits. A
// different profile/IP must finish saving before the previous input can hit
// the cache again, so A -> B -> A always persists both address changes.
func (s *userImports) synchronize(
	ctx context.Context, input userImportInput,
	importUser func(context.Context, userImportInput) (int64, error),
) (userImportResult, error) {
	s.mutex.Lock()

	revision := s.revision
	if entry, ok := s.entries[input.uuid]; ok && entry.input == input && time.Now().Before(entry.until) {
		s.mutex.Unlock()

		return userImportResult{input: input, id: entry.id, revision: revision}, nil
	}

	// Never keep the previous profile as a hit while a new one is being saved,
	// including when that save fails and its actual database outcome is unknown.
	delete(s.entries, input.uuid)
	s.mutex.Unlock()

	// The mutation and required post-insert initialization share one bounded
	// context, independent of any individual waiting client's cancellation.
	importCtx, cancel := context.WithTimeout(context.WithoutCancel(ctx), userImportTimeout)
	defer cancel()

	id, err := importUser(importCtx, input)

	s.mutex.Lock()
	defer s.mutex.Unlock()

	if err == nil && id > 0 && revision == s.revision {
		now := time.Now()
		s.makeCacheRoom(now)

		if s.entries == nil {
			s.entries = make(map[string]userImportCacheEntry)
		}

		s.entries[input.uuid] = userImportCacheEntry{input: input, id: id, until: now.Add(userImportCacheTTL)}
	}

	return userImportResult{input: input, id: id, revision: revision}, err
}

// Called under mutex. The cache has no background workers and a fixed size cap.
func (s *userImports) makeCacheRoom(now time.Time) {
	if len(s.entries) < userImportCacheMaxEntries {
		return
	}

	for uuid, entry := range s.entries {
		if !now.Before(entry.until) {
			delete(s.entries, uuid)
		}
	}

	if len(s.entries) < userImportCacheMaxEntries {
		return
	}

	for uuid := range s.entries {
		delete(s.entries, uuid)

		return
	}
}

// Invalidation cannot be canceled by the client after a persistent mutation.
// Advance the revision even without a cache hit: a pending import may still be
// holding the old UUID or user ID. Unrelated completed cache entries remain valid.
func (s *userImports) invalidate(userID int64, uuid string) {
	s.mutex.Lock()
	defer s.mutex.Unlock()

	s.revision++
	for key, entry := range s.entries {
		if entry.id == userID || key == uuid {
			delete(s.entries, key)
		}
	}
}
