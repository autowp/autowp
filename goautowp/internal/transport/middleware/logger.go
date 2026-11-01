package middleware

import (
	"context"
	"errors"
	"log/slog"

	grpclogging "github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/logging"
	"google.golang.org/grpc/codes"
)

func InterceptorLogger(logger *slog.Logger) grpclogging.Logger {
	return grpclogging.LoggerFunc(
		func(ctx context.Context, lvl grpclogging.Level, msg string, fields ...any) {
			var level slog.Level

			switch lvl {
			case grpclogging.LevelDebug:
				level = slog.LevelDebug
			case grpclogging.LevelInfo:
				level = slog.LevelInfo
			case grpclogging.LevelWarn:
				level = slog.LevelWarn
			case grpclogging.LevelError:
				level = slog.LevelError
			default:
				level = slog.LevelInfo
			}

			// Only root-request cancellation is routine. A child task's canceled
			// context must not downgrade a genuine failure of the whole RPC.
			if errors.Is(ctx.Err(), context.Canceled) {
				for index := 0; index+1 < len(fields); index += 2 {
					if fields[index] == "grpc.code" && fields[index+1] == codes.Canceled.String() {
						level = slog.LevelDebug

						break
					}
				}
			}

			// grpc-middleware hands fields as alternating key/value pairs, which is exactly what
			// slog.Log accepts as its trailing args.
			logger.Log(ctx, level, msg, fields...)
		},
	)
}
