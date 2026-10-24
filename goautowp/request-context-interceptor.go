package goautowp

import (
	"context"
	"errors"
	"strings"

	"github.com/lib/pq"
	"github.com/prometheus/client_golang/prometheus"
	"github.com/prometheus/client_golang/prometheus/promauto"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

var grpcRequestTerminations = promauto.NewCounterVec(prometheus.CounterOpts{
	Name: "goautowp_grpc_request_terminations_total",
	Help: "gRPC requests ending because their caller canceled or their deadline expired.",
}, []string{"method", "code"})

// RequestContextUnaryServerInterceptor restores context status codes even when
// handlers have translated a driver/context error to Internal. Install inside
// the logging interceptor so both the caller and the logger see the same code.
func RequestContextUnaryServerInterceptor() grpc.UnaryServerInterceptor {
	return func(
		ctx context.Context, req interface{}, info *grpc.UnaryServerInfo, handler grpc.UnaryHandler,
	) (interface{}, error) {
		response, err := handler(ctx, req)

		return response, finishRequestContext(ctx, info.FullMethod, err)
	}
}

func RequestContextStreamServerInterceptor() grpc.StreamServerInterceptor {
	return func(srv interface{}, stream grpc.ServerStream, info *grpc.StreamServerInfo, handler grpc.StreamHandler) error {
		return finishRequestContext(stream.Context(), info.FullMethod, handler(srv, stream))
	}
}

func finishRequestContext(ctx context.Context, method string, err error) error {
	requestErr := ctx.Err()
	if err == nil || requestErr == nil || !isRequestContextError(err, requestErr) {
		return err
	}

	result := status.FromContextError(requestErr)
	grpcRequestTerminations.WithLabelValues(method, result.Code().String()).Inc()

	return result.Err()
}

func isRequestContextError(err, requestErr error) bool {
	// Do not discard a real error joined with a cancellation. Child cancellation
	// alone also cannot qualify: finishRequestContext requires the root to end.
	if joined, ok := err.(interface{ Unwrap() []error }); ok {
		children := joined.Unwrap()
		if len(children) == 0 {
			return false
		}

		for _, child := range children {
			if !isRequestContextError(child, requestErr) {
				return false
			}
		}

		return true
	}

	if wrapped := errors.Unwrap(err); wrapped != nil {
		return isRequestContextError(wrapped, requestErr)
	}

	if errors.Is(err, context.Canceled) {
		return true
	}

	if errors.Is(err, context.DeadlineExceeded) {
		return errors.Is(requestErr, context.DeadlineExceeded)
	}

	var pgErr *pq.Error
	if errors.As(err, &pgErr) {
		return pgErr.Code == "57014" && pgErr.Message == "canceling statement due to user request"
	}

	grpcStatus := status.Convert(err)
	switch grpcStatus.Code() { //nolint:exhaustive // Only cancellation-related status codes qualify.
	case codes.Canceled:
		return true
	case codes.DeadlineExceeded:
		return errors.Is(requestErr, context.DeadlineExceeded)
	case codes.Internal, codes.Unknown:
		// Legacy handlers discard the error chain with status.Error(..., err.Error()).
		// Recognize only the known terminal cause, not arbitrary occurrences of these
		// words in an error or every SQLSTATE 57014 (e.g. statement_timeout).
		causes := []string{
			context.Canceled.Error(),
			"pq: canceling statement due to user request",
			"pq: canceling statement due to user request (57014)",
		}
		if errors.Is(requestErr, context.DeadlineExceeded) {
			causes = append(causes, context.DeadlineExceeded.Error())
		}

		for _, cause := range causes {
			if grpcStatus.Message() == cause || strings.HasSuffix(grpcStatus.Message(), ": "+cause) {
				return true
			}
		}
	}

	return false
}
