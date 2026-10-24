package goautowp

import (
	"context"
	"sync/atomic"
	"testing"
	"time"

	"github.com/stretchr/testify/require"
)

func TestNestedPictureExtractionSharesItemBudget(t *testing.T) {
	t.Parallel()
	extractor, db, _, rows := newPictureFieldBatchExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 5*time.Second)
	defer cancel()

	started := make(chan struct{}, 32)
	release := make(chan struct{})

	var active, maximum atomic.Int64

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() {
		current := active.Add(1)
		defer active.Add(-1)

		for old := maximum.Load(); current > old; old = maximum.Load() {
			if maximum.CompareAndSwap(old, current) {
				break
			}
		}

		started <- struct{}{}

		select {
		case <-release:
		case <-ctx.Done():
		}
	}})

	lookups := make([]func(context.Context) error, 0, itemExtractorParallelism)
	for range itemExtractorParallelism {
		lookups = append(lookups, func(ctx context.Context) error {
			_, err := extractor.ExtractRows(
				ctx,
				rows[:1],
				&PictureFields{Rights: true, Copyrights: true, Subscribed: true},
				"en",
				UserContext{UserID: 7},
			)

			return err
		})
	}

	done := make(chan error, 1)
	go func() { done <- runItemLookups(ctx, lookups) }()

	for range itemExtractorParallelism {
		select {
		case <-started:
		case <-ctx.Done():
		}
	}
	// All outer slots have work in progress. Further nested tasks must not start.
	select {
	case <-started:
		t.Error("nested extraction started beyond the shared budget")
	case <-time.After(50 * time.Millisecond):
	}

	close(release)
	require.NoError(t, <-done)
	require.LessOrEqual(
		t,
		maximum.Load(),
		int64(itemExtractorParallelism),
		"nested pictures exceeded shared item budget",
	)
}

func TestEmptyPicturePathsDoNotLoadDependencies(t *testing.T) {
	t.Parallel()

	extractor := &PictureExtractor{}
	paths, err := extractor.preloadPaths(t.Context(), nil, 123)
	require.NoError(t, err)
	require.Empty(t, paths)
	pictures, err := extractor.ExtractRows(
		t.Context(),
		nil,
		&PictureFields{Path: &PicturePathRequest{}},
		"en",
		UserContext{},
	)
	require.NoError(t, err)
	require.NotNil(t, pictures)
	require.Empty(t, pictures)
}
