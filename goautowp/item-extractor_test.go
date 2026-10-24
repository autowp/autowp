package goautowp

import (
	"context"
	"sync/atomic"
	"testing"
	"time"

	"github.com/stretchr/testify/require"
)

// The caller may leave the fields out entirely, and proto.Clone of an absent message gives back a
// zero message that must not be written to - so the nil case is the one worth pinning down.
func TestPreviewPictureFieldsWithoutRequestedFields(t *testing.T) {
	t.Parallel()

	medium := previewPictureFields(nil, false)
	require.True(t, medium.GetNameText())
	require.True(t, medium.GetThumbMedium())
	require.False(t, medium.GetThumbLarge())

	large := previewPictureFields(nil, true)
	require.True(t, large.GetThumbLarge())
	require.False(t, large.GetThumbMedium())
}

// What the caller did ask for survives, and their message is not modified.
func TestPreviewPictureFieldsKeepsRequestedFields(t *testing.T) {
	t.Parallel()

	requested := &PictureFields{Image: true, ThumbLarge: true}

	fields := previewPictureFields(requested, false)

	require.True(t, fields.GetImage())
	require.True(t, fields.GetThumbMedium())
	require.False(t, fields.GetThumbLarge())

	require.True(t, requested.GetThumbLarge())
	require.False(t, requested.GetNameText())
}

func TestRunItemLookupsCancelsSiblingsAndWaits(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
	defer cancel()

	started := make(chan struct{})

	var joined atomic.Bool

	err := runItemLookups(ctx, []func(context.Context) error{
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

func TestRunItemLookupsHonorsCancellation(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithCancel(t.Context())
	started := make(chan struct{})
	done := make(chan error, 1)

	go func() {
		done <- runItemLookups(ctx, []func(context.Context) error{func(ctx context.Context) error {
			close(started)
			<-ctx.Done()

			return ctx.Err()
		}})
	}()

	<-started
	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
}

func TestRunItemLookupsBoundsConcurrency(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
	defer cancel()

	const count = 2 * itemExtractorParallelism

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

	go func() { done <- runItemLookups(ctx, lookups) }()

	for range itemExtractorParallelism {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("item lookups did not reach the concurrency limit")
		}
	}

	close(release)
	require.NoError(t, <-done)
	require.EqualValues(t, itemExtractorParallelism, peak.Load())
	require.Zero(t, inFlight.Load())
	require.Len(t, started, count-itemExtractorParallelism)
}
