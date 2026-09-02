package goautowp

import (
	"context"
	"log/slog"
	"time"

	"google.golang.org/grpc"
)

// SlowCallUnaryServerInterceptor logs calls that took longer than threshold, at warn level.
//
// The middleware logger already reports every finished call, but at info - and production runs at
// warn, so the only calls visible there are the ones that failed. That leaves the interesting case
// invisible: a call that succeeds, slowly, which is what a caller gives up on (the "context
// canceled" errors that do show up are the symptom, logged against whatever call happened to be
// running when the client walked away, not the cause). Anything over the threshold is reported
// here with the method and how long it took, so the slow ones can be ranked from the same logs.
//
// A threshold of 0 disables it.
func SlowCallUnaryServerInterceptor(threshold time.Duration) grpc.UnaryServerInterceptor {
	return func(
		ctx context.Context,
		req interface{},
		info *grpc.UnaryServerInfo,
		handler grpc.UnaryHandler,
	) (interface{}, error) {
		if threshold <= 0 {
			return handler(ctx, req)
		}

		start := time.Now()

		resp, err := handler(ctx, req)

		elapsed := time.Since(start)
		if elapsed >= threshold {
			slog.WarnContext(ctx, "slow call",
				"grpc.duration_ms", elapsed.Milliseconds(),
				"grpc.method", info.FullMethod,
				// Distinguishes "slow, and the caller was still there" from "slow, and it was
				// abandoned" without needing the error itself.
				"grpc.failed", err != nil,
			)
		}

		return resp, err
	}
}
