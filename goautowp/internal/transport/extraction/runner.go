// Package extraction shares concurrency limits across nested transport lookups.
package extraction

import (
	"context"

	"golang.org/x/sync/errgroup"
)

// DefaultParallelism bounds independent reads within a request's extraction group.
// Keep it below the database pool size because several requests may run concurrently.
const DefaultParallelism = 4

type (
	lookupContextKey      struct{}
	pictureParallelismKey struct{}
)

// WithInlineLookups marks a branch that already occupies a lookup slot.
// Nested Run calls execute sequentially within that slot.
func WithInlineLookups(ctx context.Context) context.Context {
	return context.WithValue(ctx, lookupContextKey{}, true)
}

// WithPictureParallelism passes the remaining picture budget to nested extraction.
func WithPictureParallelism(ctx context.Context, limit int) context.Context {
	return context.WithValue(ctx, pictureParallelismKey{}, limit)
}

func PictureParallelism(ctx context.Context) int {
	if limit, ok := ctx.Value(pictureParallelismKey{}).(int); ok {
		return limit
	}

	return DefaultParallelism
}

// Run cancels sibling reads on the first failure and joins all tasks.
// Nested groups run inline: acquiring another slot while a parent waits could
// deadlock, and creating a fresh group would multiply concurrency at every level.
func Run(ctx context.Context, lookups []func(context.Context) error) error {
	if ctx.Value(lookupContextKey{}) != nil {
		for _, lookup := range lookups {
			if err := ctx.Err(); err != nil {
				return err
			}

			if err := lookup(ctx); err != nil {
				return err
			}
		}

		return nil
	}

	ctx = WithInlineLookups(ctx)
	// Each branch owns one slot, including any nested picture extraction.
	ctx = WithPictureParallelism(ctx, 1)
	group, lookupCtx := errgroup.WithContext(ctx)
	group.SetLimit(DefaultParallelism)

	for _, lookup := range lookups {
		group.Go(func() error {
			if err := lookupCtx.Err(); err != nil {
				return err
			}

			return lookup(lookupCtx)
		})
	}

	return group.Wait()
}
