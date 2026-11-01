package extraction

import (
	"context"
	"sync/atomic"
	"testing"
	"time"

	"github.com/stretchr/testify/require"
)

func TestRunCancelsSiblingsAndWaits(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
	defer cancel()

	started := make(chan struct{})

	var joined atomic.Bool

	err := Run(ctx, []func(context.Context) error{
		func(ctx context.Context) error {
			close(started)
			<-ctx.Done()
			joined.Store(true)

			return ctx.Err()
		},
		func(context.Context) error {
			<-started

			return context.DeadlineExceeded
		},
	})
	require.ErrorIs(t, err, context.DeadlineExceeded)
	require.NoError(t, ctx.Err(), "failure cancels siblings without waiting for the parent deadline")
	require.True(t, joined.Load(), "canceled siblings must finish before returning")
}

func TestRunHonorsCancellation(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithCancel(t.Context())
	started := make(chan struct{})
	done := make(chan error, 1)

	go func() {
		done <- Run(ctx, []func(context.Context) error{func(ctx context.Context) error {
			close(started)
			<-ctx.Done()

			return ctx.Err()
		}})
	}()

	<-started
	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
}

func TestRunBoundsConcurrency(t *testing.T) {
	t.Parallel()

	for _, testCase := range []struct {
		name   string
		nested bool
	}{
		{name: "flat"},
		{name: "nested", nested: true},
	} {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
			defer cancel()

			const count = 2 * DefaultParallelism

			started := make(chan struct{}, count)
			release := make(chan struct{})

			var inFlight, peak atomic.Int32

			lookups := make([]func(context.Context) error, count)
			for index := range lookups {
				lookups[index] = func(ctx context.Context) error {
					active := inFlight.Add(1)
					defer inFlight.Add(-1)

					for previous := peak.Load(); active > previous; previous = peak.Load() {
						if peak.CompareAndSwap(previous, active) {
							break
						}
					}

					started <- struct{}{}

					select {
					case <-release:
						return nil
					case <-ctx.Done():
						return ctx.Err()
					}
				}
			}

			done := make(chan error, 1)

			if testCase.nested {
				outer := make([]func(context.Context) error, DefaultParallelism)
				for index := range outer {
					branch := lookups[index*2 : (index+1)*2]
					outer[index] = func(ctx context.Context) error { return Run(ctx, branch) }
				}

				lookups = outer
			}

			go func() { done <- Run(ctx, lookups) }()

			for range DefaultParallelism {
				select {
				case <-started:
				case <-ctx.Done():
					t.Fatal("item lookups did not reach the concurrency limit")
				}
			}

			close(release)
			require.NoError(t, <-done)
			require.EqualValues(t, DefaultParallelism, peak.Load())
			require.Zero(t, inFlight.Load())
			require.Len(t, started, count-DefaultParallelism)
		})
	}
}
