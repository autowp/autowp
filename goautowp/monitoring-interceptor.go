package goautowp

import (
	"context"
	"net"
	"net/http"
	"slices"

	"github.com/autowp/goautowp/users"
	"github.com/gin-gonic/gin"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"google.golang.org/grpc"
)

type requestRecorder interface{ Record(ip net.IP) }

type monitoringAuth interface {
	ValidateGRPC(ctx context.Context) (UserContext, error)
	ValidateREST(ctx *gin.Context) (UserContext, error)
}

func monitoringUser(ctx context.Context, auth monitoringAuth) (context.Context, bool) {
	user, err := auth.ValidateGRPC(ctx)
	if err != nil || user.UserID == 0 {
		return ctx, false
	}

	return context.WithValue(ctx, authenticatedUserKey{}, user), isMonitoringExempt(user)
}

func isMonitoringExempt(user UserContext) bool {
	return user.UserID > 0 &&
		(slices.Contains(user.Roles, users.RoleModer) || slices.Contains(user.Roles, users.RoleAdmin))
}

type monitoringServerStream struct {
	grpc.ServerStream

	ctx context.Context //nolint:containedctx // Adapts the context exposed by grpc.ServerStream.
}

func (stream *monitoringServerStream) Context() context.Context { return stream.ctx }

func monitoringUnaryInterceptor(recorder requestRecorder, auth monitoringAuth) grpc.UnaryServerInterceptor {
	return func(
		ctx context.Context,
		req interface{},
		info *grpc.UnaryServerInfo,
		handler grpc.UnaryHandler,
	) (interface{}, error) {
		var exempt bool

		ctx, exempt = monitoringUser(ctx, auth)
		if !exempt && info.FullMethod != banExemptMethod {
			if ip, ok := realip.FromContext(ctx); ok {
				recorder.Record(net.ParseIP(ip.String()))
			}
		}

		return handler(ctx, req)
	}
}

func monitoringStreamInterceptor(recorder requestRecorder, auth monitoringAuth) grpc.StreamServerInterceptor {
	return func(srv interface{}, stream grpc.ServerStream, _ *grpc.StreamServerInfo, handler grpc.StreamHandler) error {
		ctx, exempt := monitoringUser(stream.Context(), auth)
		if !exempt {
			if ip, ok := realip.FromContext(ctx); ok {
				recorder.Record(net.ParseIP(ip.String()))
			}
		}

		return handler(srv, &monitoringServerStream{ServerStream: stream, ctx: ctx})
	}
}

func monitoringGinMiddleware(recorder requestRecorder, auth monitoringAuth) gin.HandlerFunc {
	return func(ctx *gin.Context) {
		if ctx.Request.Method != http.MethodOptions {
			user, err := auth.ValidateREST(ctx)
			if err == nil && user.UserID > 0 {
				ctx.Request = ctx.Request.WithContext(
					context.WithValue(ctx.Request.Context(), authenticatedUserKey{}, user),
				)
			}

			if err != nil || !isMonitoringExempt(user) {
				recorder.Record(net.ParseIP(ctx.ClientIP()))
			}
		}

		ctx.Next()
	}
}
