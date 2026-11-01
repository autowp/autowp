package goautowp

import (
	"context"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/transport/extraction"
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

	lookups := make([]func(context.Context) error, 0, extraction.DefaultParallelism)
	for range extraction.DefaultParallelism {
		lookups = append(lookups, func(ctx context.Context) error {
			_, err := extractor.ExtractRows(
				ctx,
				rows[:1],
				&pb.PictureFields{Rights: true, Copyrights: true, Subscribed: true},
				"en",
				authn.User{UserID: 7},
			)

			return err
		})
	}

	done := make(chan error, 1)
	go func() { done <- extraction.Run(ctx, lookups) }()

	for range extraction.DefaultParallelism {
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
		int64(extraction.DefaultParallelism),
		"nested pictures exceeded shared item budget",
	)
}
