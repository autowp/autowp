package goautowp

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"log/slog"
	"testing"
	"testing/synctest"
	"time"

	grpclogging "github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/logging"
	"github.com/lib/pq"
	dto "github.com/prometheus/client_model/go"
	"github.com/stretchr/testify/require"
	"golang.org/x/sync/errgroup"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

func TestRequestContextErrorNormalization(t *testing.T) {
	t.Parallel()

	realError := status.Error(codes.Internal, "image conversion failed")
	pgCanceled := &pq.Error{Code: "57014", Message: "canceling statement due to user request"}

	tests := []struct {
		name       string
		requestErr error
		returned   error
		code       codes.Code
		normalized bool
	}{
		{"raw canceled", context.Canceled, context.Canceled, codes.Canceled, true},
		{"wrapped canceled", context.Canceled, fmt.Errorf("read: %w", context.Canceled), codes.Canceled, true},
		{"postgres canceled", context.Canceled, pgCanceled, codes.Canceled, true},
		{"wrapped postgres", context.Canceled, fmt.Errorf("query: %w", pgCanceled), codes.Canceled, true},
		{"internal canceled", context.Canceled, status.Error(codes.Internal, "context canceled"), codes.Canceled, true},
		{
			"unknown postgres",
			context.Canceled,
			status.Error(codes.Unknown, "pq: canceling statement due to user request (57014)"),
			codes.Canceled,
			true,
		},
		{"internal postgres", context.Canceled, status.Error(codes.Internal, pgCanceled.Error()), codes.Canceled, true},
		{
			"prefixed postgres",
			context.Canceled,
			status.Error(codes.Internal, "Pictures(): pq: canceling statement due to user request (57014)"),
			codes.Canceled,
			true,
		},
		{"grpc canceled", context.Canceled, status.Error(codes.Canceled, "client left"), codes.Canceled, true},
		{"deadline postgres", context.DeadlineExceeded, pgCanceled, codes.DeadlineExceeded, true},
		{"deadline driver cancellation", context.DeadlineExceeded, context.Canceled, codes.DeadlineExceeded, true},
		{"raw deadline", context.DeadlineExceeded, context.DeadlineExceeded, codes.DeadlineExceeded, true},
		{
			"internal deadline",
			context.DeadlineExceeded,
			status.Error(codes.Internal, "context deadline exceeded"),
			codes.DeadlineExceeded,
			true,
		},
		{
			"grpc deadline",
			context.DeadlineExceeded,
			status.Error(codes.DeadlineExceeded, "expired"),
			codes.DeadlineExceeded,
			true,
		},
		{"live parent child canceled", nil, context.Canceled, codes.Unknown, false},
		{"live parent postgres", nil, pgCanceled, codes.Unknown, false},
		{"live parent internal canceled", nil, status.Error(codes.Internal, "context canceled"), codes.Internal, false},
		{
			"live parent deadline",
			nil,
			status.Error(codes.DeadlineExceeded, "dependency expired"),
			codes.DeadlineExceeded,
			false,
		},
		{"real error with canceled parent", context.Canceled, realError, codes.Internal, false},
		{"real error with expired parent", context.DeadlineExceeded, realError, codes.Internal, false},
		{
			"statement timeout",
			context.Canceled,
			&pq.Error{Code: "57014", Message: "canceling statement due to statement timeout"},
			codes.Unknown,
			false,
		},
		{
			"internal statement timeout",
			context.Canceled,
			status.Error(codes.Internal, "pq: canceling statement due to statement timeout (57014)"),
			codes.Internal,
			false,
		},
		{"other sql error", context.Canceled, &pq.Error{Code: "42601", Message: "syntax error"}, codes.Unknown, false},
		{
			"cancellation words in real error",
			context.Canceled,
			status.Error(codes.Internal, "context canceled while recovering a corrupted image"),
			codes.Internal,
			false,
		},
		{"joined real error", context.Canceled, errors.Join(realError, context.Canceled), codes.Internal, false},
		{"multiple canceled causes", context.Canceled, errors.Join(pgCanceled, context.Canceled), codes.Canceled, true},
		{
			"independent deadline",
			context.Canceled,
			status.Error(codes.DeadlineExceeded, "dependency expired"),
			codes.DeadlineExceeded,
			false,
		},
		{"success after cancel", context.Canceled, nil, codes.OK, false},
	}
	for _, test := range tests {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()
			ctx := t.Context()

			switch {
			case errors.Is(test.requestErr, context.Canceled):
				var cancel context.CancelFunc

				ctx, cancel = context.WithCancel(ctx)
				cancel()
			case errors.Is(test.requestErr, context.DeadlineExceeded):
				var cancel context.CancelFunc

				ctx, cancel = context.WithDeadline(ctx, time.Now().Add(-time.Second))
				defer cancel()
			}

			method := "/review.Context/" + test.name
			counter := grpcRequestTerminations.WithLabelValues(method, test.code.String())
			before := &dto.Metric{}
			require.NoError(t, counter.Write(before))

			response, err := RequestContextUnaryServerInterceptor()(
				ctx,
				"response",
				&grpc.UnaryServerInfo{FullMethod: method},
				func(_ context.Context, req interface{}) (interface{}, error) { return req, test.returned },
			)
			require.Equal(t, "response", response)
			require.Equal(t, test.code, status.Code(err))

			after := &dto.Metric{}
			require.NoError(t, counter.Write(after))

			if test.normalized {
				require.InDelta(t, before.GetCounter().GetValue()+1, after.GetCounter().GetValue(), 0)
			} else {
				require.Equal(t, test.returned, err, "unrelated errors and successful responses must remain unchanged")
				require.InDelta(t, before.GetCounter().GetValue(), after.GetCounter().GetValue(), 0)
			}
		})
	}
}

func requestContextLogRecords(t *testing.T, data *bytes.Buffer) []map[string]interface{} {
	t.Helper()

	lines := bytes.Split(bytes.TrimSpace(data.Bytes()), []byte("\n"))
	records := make([]map[string]interface{}, 0, len(lines))

	for _, line := range lines {
		var record map[string]interface{}
		require.NoError(t, json.Unmarshal(line, &record))
		records = append(records, record)
	}

	return records
}

func TestRequestContextLogging(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		name     string
		cancel   bool
		returned error
		level    string
		code     codes.Code
	}{
		{"canceled client", true, status.Error(codes.Internal, "context canceled"), "DEBUG", codes.Canceled},
		{
			"unrelated failure after cancel", true,
			status.Error(codes.Internal, "image conversion failed"), "ERROR", codes.Internal,
		},
		{"child cancellation", false, status.Error(codes.Internal, "context canceled"), "ERROR", codes.Internal},
		{
			"dependency deadline", false,
			status.Error(codes.DeadlineExceeded, "dependency expired"), "WARN", codes.DeadlineExceeded,
		},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			var output bytes.Buffer

			logger := slog.New(slog.NewJSONHandler(&output, &slog.HandlerOptions{Level: slog.LevelDebug}))

			ctx, cancel := context.WithCancel(t.Context())
			defer cancel()

			info := &grpc.UnaryServerInfo{FullMethod: "/review.Logging/Call"}
			_, err := grpclogging.UnaryServerInterceptor(
				InterceptorLogger(logger),
			)(
				ctx,
				nil,
				info,
				func(ctx context.Context, req interface{}) (interface{}, error) {
					return RequestContextUnaryServerInterceptor()(
						ctx,
						req,
						info,
						func(context.Context, interface{}) (interface{}, error) {
							if test.cancel {
								cancel()
							}

							return nil, test.returned
						},
					)
				},
			)
			require.Equal(t, test.code, status.Code(err))
			records := requestContextLogRecords(t, &output)
			require.Equal(t, "finished call", records[len(records)-1]["msg"])
			require.Equal(t, test.level, records[len(records)-1]["level"])
			require.Equal(t, test.code.String(), records[len(records)-1]["grpc.code"])
		})
	}
}

func TestRequestContextPreservesErrgroupFailure(t *testing.T) {
	t.Parallel()

	failure := status.Error(codes.Internal, "database unavailable")
	_, err := RequestContextUnaryServerInterceptor()(
		t.Context(),
		nil,
		&grpc.UnaryServerInfo{FullMethod: "/review.Context/Errgroup"},
		func(ctx context.Context, _ interface{}) (interface{}, error) {
			group, childCtx := errgroup.WithContext(ctx)
			group.Go(func() error { return failure })
			group.Go(func() error {
				<-childCtx.Done()

				return childCtx.Err()
			})

			return nil, group.Wait()
		},
	)
	require.Equal(t, failure, err)
	require.NoError(t, t.Context().Err())
}

type requestContextTestStream struct {
	grpc.ServerStream

	getContext func() context.Context
}

func (s *requestContextTestStream) Context() context.Context { return s.getContext() }

func TestRequestContextStreamCancellation(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithCancel(t.Context())
	defer cancel()

	err := RequestContextStreamServerInterceptor()(
		nil,
		&requestContextTestStream{getContext: func() context.Context { return ctx }},
		&grpc.StreamServerInfo{FullMethod: "/review.Context/Stream"},
		func(interface{}, grpc.ServerStream) error {
			cancel()

			return status.Error(codes.Internal, "pq: canceling statement due to user request (57014)")
		},
	)
	require.Equal(t, codes.Canceled, status.Code(err))
}

func TestSlowCanceledRequestRemainsVisible(t *testing.T) {
	t.Parallel()
	synctest.Test(t, func(t *testing.T) {
		var output bytes.Buffer

		logger := slog.New(slog.NewJSONHandler(&output, &slog.HandlerOptions{Level: slog.LevelDebug}))

		ctx, cancel := context.WithCancel(t.Context())
		defer cancel()

		info := &grpc.UnaryServerInfo{FullMethod: "/review.Context/SlowCanceled"}
		_, err := slowCallUnaryServerInterceptor(
			time.Second,
			logger,
		)(
			ctx,
			nil,
			info,
			func(ctx context.Context, req interface{}) (interface{}, error) {
				return RequestContextUnaryServerInterceptor()(
					ctx,
					req,
					info,
					func(context.Context, interface{}) (interface{}, error) {
						time.Sleep(2 * time.Second)
						cancel()

						return nil, context.Canceled
					},
				)
			},
		)
		require.Equal(t, codes.Canceled, status.Code(err))
		records := requestContextLogRecords(t, &output)
		require.Len(t, records, 1)
		require.Equal(t, "slow call", records[0]["msg"])
		require.Equal(t, "WARN", records[0]["level"])
		require.Equal(t, "Canceled", records[0]["grpc.code"])
	})
}
