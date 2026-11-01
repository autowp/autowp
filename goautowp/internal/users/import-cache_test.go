package users

import (
	"context"
	"strconv"
	"sync"
	"sync/atomic"
	"testing"
	"testing/synctest"
	"time"

	"github.com/stretchr/testify/require"
)

func TestUserImportCacheDoesNotExtendDeadline(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		imports := &userImports{}
		input := userImportInput{uuid: "one"}
		calls := int64(0)
		importUser := func(ctx context.Context, _ userImportInput) (int64, error) {
			calls++

			return calls, ctx.Err()
		}
		id, err := imports.ensure(t.Context(), input, importUser)
		require.NoError(t, err)
		require.EqualValues(t, 1, id)
		time.Sleep(userImportCacheTTL - time.Second)

		id, err = imports.ensure(t.Context(), input, importUser)
		require.NoError(t, err)
		require.EqualValues(t, 1, id)
		time.Sleep(time.Second)

		id, err = imports.ensure(t.Context(), input, importUser)
		require.NoError(t, err)
		require.EqualValues(t, 2, id, "cache hits must not postpone synchronization")
	})
}

func TestUserImportCacheStoresOnlyLatestProfile(t *testing.T) {
	t.Parallel()

	for _, field := range []string{"email", "name", "ip", "green", "locale", "timezone"} {
		t.Run(field, func(t *testing.T) {
			t.Parallel()

			imports := &userImports{}
			initial := userImportInput{uuid: "one", email: "old@example.com", name: "Old", ip: "192.0.2.1"}
			changed := initial

			switch field {
			case "email":
				changed.email = "new@example.com"
			case "name":
				changed.name = "New"
			case "ip":
				changed.ip = "2001:db8::1"
			case "green":
				changed.green = true
			case "locale":
				changed.locale = "ru"
			case "timezone":
				changed.timezone = "Europe/Moscow"
			}

			calls := int64(0)

			importUser := func(ctx context.Context, _ userImportInput) (int64, error) {
				calls++

				return calls, ctx.Err()
			}
			for index, input := range []userImportInput{initial, changed, initial} {
				id, err := imports.ensure(t.Context(), input, importUser)
				require.NoError(t, err)
				require.EqualValues(t, index+1, id, "returning to the previous profile must write again")
			}
		})
	}
}

func TestUserImportCacheInvalidationClearsIDsAndUUIDs(t *testing.T) {
	t.Parallel()

	imports := &userImports{}
	calls := make(map[string]int)

	importUser := func(ctx context.Context, input userImportInput) (int64, error) {
		calls[input.uuid]++
		if input.uuid == "one" {
			return 1, ctx.Err()
		}

		if input.uuid == "new" {
			return 99, ctx.Err()
		}

		return 2, ctx.Err()
	}
	for _, uuid := range []string{"one", "two", "new"} {
		_, err := imports.ensure(t.Context(), userImportInput{uuid: uuid}, importUser)
		require.NoError(t, err)
	}

	imports.invalidate(1, "new")

	for _, uuid := range []string{"one", "two", "new"} {
		_, err := imports.ensure(t.Context(), userImportInput{uuid: uuid}, importUser)
		require.NoError(t, err)
	}

	require.Equal(t, map[string]int{"one": 2, "two": 1, "new": 2}, calls,
		"invalidate by user ID and new UUID, while retaining other users")
}

func TestUserImportCacheInvalidationDiscardsPendingResult(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		imports := &userImports{}
		input := userImportInput{uuid: "one"}

		var calls atomic.Int32

		release := make(chan struct{})

		unblock := sync.OnceFunc(func() { close(release) })
		defer unblock()

		importUser := func(ctx context.Context, _ userImportInput) (int64, error) {
			call := calls.Add(1)
			if call == 1 {
				select {
				case <-release:
				case <-ctx.Done():
					return 0, ctx.Err()
				}
			}

			return int64(call), ctx.Err()
		}
		results := make(chan importTestResult, 2)

		for range 2 {
			go func() {
				id, err := imports.ensure(t.Context(), input, importUser)
				results <- importTestResult{id: id, err: err}
			}()
		}

		synctest.Wait()
		imports.invalidate(1, "one")
		unblock()
		synctest.Wait()

		for range 2 {
			result := <-results
			require.NoError(t, result.err)
			require.EqualValues(t, 2, result.id, "the result predating invalidation must not escape")
		}

		id, err := imports.ensure(t.Context(), input, importUser)
		require.NoError(t, err)
		require.EqualValues(t, 2, id)
		require.EqualValues(t, 2, calls.Load())
	})
}

func TestUserImportCacheBoundsMemory(t *testing.T) {
	t.Parallel()

	for _, expired := range []bool{false, true} {
		t.Run(strconv.FormatBool(expired), func(t *testing.T) {
			t.Parallel()

			imports := &userImports{entries: make(map[string]userImportCacheEntry)}

			until := time.Now().Add(time.Minute)
			if expired {
				until = time.Now().Add(-time.Second)
			}

			for id := range userImportCacheMaxEntries {
				uuid := strconv.Itoa(id)
				imports.entries[uuid] = userImportCacheEntry{
					input: userImportInput{uuid: uuid},
					id:    int64(id + 1),
					until: until,
				}
			}

			id, err := imports.ensure(t.Context(), userImportInput{uuid: "new"},
				func(ctx context.Context, _ userImportInput) (int64, error) { return 42, ctx.Err() })
			require.NoError(t, err)
			require.EqualValues(t, 42, id)
			require.LessOrEqual(t, len(imports.entries), userImportCacheMaxEntries)
			require.Contains(t, imports.entries, "new")

			if expired {
				require.Len(t, imports.entries, 1)
			}
		})
	}
}
