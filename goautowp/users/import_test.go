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

var errImportTest = errors.New("user import test failure")

type importTestResult struct {
	id  int64
	err error
}

func TestUserImportsSharesConcurrentResultsOnly(t *testing.T) {
	t.Parallel()

	for _, importErr := range []error{nil, errImportTest} {
		t.Run(fmt.Sprint(importErr), func(t *testing.T) {
			t.Parallel()

			synctest.Test(t, func(t *testing.T) {
				const callers = 64

				imports := &userImports{}
				input := userImportInput{uuid: "one", ip: "192.0.2.1"}

				var calls atomic.Int32

				release := make(chan struct{})

				unblock := sync.OnceFunc(func() { close(release) })
				defer unblock()

				importUser := func(ctx context.Context, _ userImportInput) (int64, error) {
					if calls.Add(1) == 1 {
						select {
						case <-release:
						case <-ctx.Done():
							return 0, ctx.Err()
						}

						return 42, importErr
					}

					return 42, ctx.Err()
				}

				results := make(chan importTestResult, callers)
				for range callers {
					go func() {
						id, err := imports.ensure(t.Context(), input, importUser)
						results <- importTestResult{id: id, err: err}
					}()
				}

				synctest.Wait()
				require.EqualValues(t, 1, calls.Load())
				unblock()
				synctest.Wait()

				for range callers {
					result := <-results
					require.EqualValues(t, 42, result.id)
					require.ErrorIs(t, result.err, importErr)
				}

				id, err := imports.ensure(t.Context(), input, importUser)
				require.NoError(t, err)
				require.EqualValues(t, 42, id)
				require.EqualValues(
					t,
					2,
					calls.Load(),
					"a sequential call must execute again, including after an error",
				)
			})
		})
	}
}

func TestUserImportsPreservesDifferentInputs(t *testing.T) {
	t.Parallel()

	initial := userImportInput{
		uuid: "one", email: "old@example.com", name: "Old", ip: "192.0.2.1",
		locale: "en", timezone: "UTC",
	}

	for _, field := range []string{"email", "name", "ip", "green", "locale", "timezone"} {
		t.Run(field, func(t *testing.T) {
			t.Parallel()

			synctest.Test(t, func(t *testing.T) {
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

				imports := &userImports{}

				var calls atomic.Int32

				release := make(chan struct{})

				unblock := sync.OnceFunc(func() { close(release) })
				defer unblock()

				inputs := make(chan userImportInput, 2)
				importUser := func(ctx context.Context, input userImportInput) (int64, error) {
					inputs <- input

					if calls.Add(1) == 1 {
						select {
						case <-release:
						case <-ctx.Done():
							return 0, ctx.Err()
						}

						return 0, errImportTest
					}

					return 42, ctx.Err()
				}
				leader, follower := make(chan importTestResult, 1), make(chan importTestResult, 1)

				go func() {
					id, err := imports.ensure(t.Context(), initial, importUser)
					leader <- importTestResult{id: id, err: err}
				}()

				synctest.Wait()

				go func() {
					id, err := imports.ensure(t.Context(), changed, importUser)
					follower <- importTestResult{id: id, err: err}
				}()

				synctest.Wait()
				require.EqualValues(t, 1, calls.Load(), "different inputs must wait for the active mutation")
				unblock()
				synctest.Wait()
				require.ErrorIs(t, (<-leader).err, errImportTest)

				result := <-follower
				require.NoError(t, result.err)
				require.EqualValues(t, 42, result.id)
				require.EqualValues(t, 2, calls.Load())
				require.Equal(t, initial, <-inputs)
				require.Equal(t, changed, <-inputs)
			})
		})
	}
}

func TestUserImportsDifferentUsersDoNotBlock(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		imports := &userImports{}

		var calls atomic.Int32

		release := make(chan struct{})

		unblock := sync.OnceFunc(func() { close(release) })
		defer unblock()

		importUser := func(ctx context.Context, _ userImportInput) (int64, error) {
			calls.Add(1)

			select {
			case <-release:
				return 42, nil
			case <-ctx.Done():
				return 0, ctx.Err()
			}
		}
		results := make(chan error, 2)

		for _, uuid := range []string{"one", "two"} {
			go func() {
				_, err := imports.ensure(t.Context(), userImportInput{uuid: uuid}, importUser)
				results <- err
			}()
		}

		synctest.Wait()
		require.EqualValues(t, 2, calls.Load())
		unblock()
		synctest.Wait()
		require.NoError(t, <-results)
		require.NoError(t, <-results)
	})
}

func TestUserImportsCallersCancelIndependently(t *testing.T) {
	t.Parallel()

	for _, cancelLeader := range []bool{true, false} {
		t.Run(fmt.Sprintf("cancel_leader_%t", cancelLeader), func(t *testing.T) {
			t.Parallel()

			synctest.Test(t, func(t *testing.T) {
				imports := &userImports{}
				input := userImportInput{uuid: "one"}

				var calls atomic.Int32

				release := make(chan struct{})

				unblock := sync.OnceFunc(func() { close(release) })
				defer unblock()

				importUser := func(ctx context.Context, _ userImportInput) (int64, error) {
					calls.Add(1)

					select {
					case <-release:
						return 42, nil
					case <-ctx.Done():
						return 0, ctx.Err()
					}
				}

				cancelCtx, cancel := context.WithCancel(t.Context())
				defer cancel()

				leaderCtx, followerCtx := t.Context(), cancelCtx
				if cancelLeader {
					leaderCtx, followerCtx = cancelCtx, t.Context()
				}

				leader, follower := make(chan error, 1), make(chan error, 1)

				go func() { _, err := imports.ensure(leaderCtx, input, importUser); leader <- err }()

				synctest.Wait()

				go func() { _, err := imports.ensure(followerCtx, input, importUser); follower <- err }()

				synctest.Wait()
				cancel()
				synctest.Wait()

				if cancelLeader {
					require.ErrorIs(t, <-leader, context.Canceled)
				} else {
					require.ErrorIs(t, <-follower, context.Canceled)
				}

				require.EqualValues(t, 1, calls.Load())
				unblock()
				synctest.Wait()

				if cancelLeader {
					require.NoError(t, <-follower)
				} else {
					require.NoError(t, <-leader)
				}

				require.EqualValues(t, 1, calls.Load())
			})
		})
	}
}

func TestUserImportsBoundsDetachedWork(t *testing.T) {
	t.Parallel()

	synctest.Test(t, func(t *testing.T) {
		imports := &userImports{}
		input := userImportInput{uuid: "one"}

		var calls atomic.Int32

		importUser := func(ctx context.Context, _ userImportInput) (int64, error) {
			calls.Add(1)
			<-ctx.Done()

			return 0, ctx.Err()
		}
		results := make(chan error, 2)

		for range 2 {
			go func() { _, err := imports.ensure(t.Context(), input, importUser); results <- err }()
		}

		synctest.Wait()
		time.Sleep(userImportTimeout)
		synctest.Wait()
		require.ErrorIs(t, <-results, context.DeadlineExceeded)
		require.ErrorIs(t, <-results, context.DeadlineExceeded)
		require.EqualValues(t, 1, calls.Load())

		_, err := imports.ensure(t.Context(), input, func(ctx context.Context, _ userImportInput) (int64, error) {
			calls.Add(1)

			return 42, ctx.Err()
		})
		require.NoError(t, err)
		require.EqualValues(t, 2, calls.Load())

		ctx, cancel := context.WithCancel(t.Context())
		cancel()

		_, err = imports.ensure(ctx, input, importUser)
		require.ErrorIs(t, err, context.Canceled)
		require.EqualValues(t, 2, calls.Load())
	})
}
