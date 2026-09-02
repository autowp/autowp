package goautowp

import (
	"context"
	"runtime"

	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/recovery"
	"github.com/sirupsen/logrus"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

// panicStackBufSize caps the goroutine stack dump captured for a recovered panic.
const panicStackBufSize = 64 << 10

// recoveryHandler turns a panic that escaped a gRPC handler into a logged event and a
// codes.Internal response. Without it the panic unwinds through the serving goroutine and takes
// the whole process down, dropping every in-flight call on that replica; a nil dereference in one
// handler should fail one request, not the server.
func recoveryHandler(ctx context.Context, panicValue any) error {
	stack := make([]byte, panicStackBufSize)
	stack = stack[:runtime.Stack(stack, false)]

	method, _ := grpc.Method(ctx)

	logrus.WithFields(logrus.Fields{
		"panic":       panicValue,
		"grpc.method": method,
		"stack":       string(stack),
	}).Error("recovered from panic in gRPC handler")

	return status.Error(codes.Internal, "internal error")
}

// recoveryOpts is the shared recovery configuration for the unary and stream interceptors.
func recoveryOpts() []recovery.Option {
	return []recovery.Option{
		recovery.WithRecoveryHandlerContext(recoveryHandler),
	}
}
