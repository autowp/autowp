package goautowp

import (
	"context"
	"net"
	"net/http"

	"github.com/autowp/goautowp/ban"
	"github.com/gin-gonic/gin"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"github.com/sirupsen/logrus"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

const errBannedMessage = "banned"

func isIPBanned(ctx context.Context, banRepository *ban.Repository, ip net.IP) bool {
	if ip == nil {
		return false
	}

	banned, err := banRepository.Exists(ctx, ip)
	if err != nil {
		logrus.Errorf("ban check failed: %s", err.Error())

		return false
	}

	return banned
}

// BanUnaryServerInterceptor rejects gRPC calls coming from a banned IP address.
// It must be chained after the realip interceptor so realip.FromContext is populated.
func BanUnaryServerInterceptor(banRepository *ban.Repository) grpc.UnaryServerInterceptor {
	return func(
		ctx context.Context,
		req interface{},
		_ *grpc.UnaryServerInfo,
		handler grpc.UnaryHandler,
	) (interface{}, error) {
		if p, ok := realip.FromContext(ctx); ok && isIPBanned(ctx, banRepository, net.ParseIP(p.String())) {
			return nil, status.Error(codes.PermissionDenied, errBannedMessage)
		}

		return handler(ctx, req)
	}
}

// BanStreamServerInterceptor rejects gRPC streams coming from a banned IP address.
// It must be chained after the realip interceptor so realip.FromContext is populated.
func BanStreamServerInterceptor(banRepository *ban.Repository) grpc.StreamServerInterceptor {
	return func(
		srv interface{},
		ss grpc.ServerStream,
		_ *grpc.StreamServerInfo,
		handler grpc.StreamHandler,
	) error {
		ctx := ss.Context()

		if p, ok := realip.FromContext(ctx); ok && isIPBanned(ctx, banRepository, net.ParseIP(p.String())) {
			return status.Error(codes.PermissionDenied, errBannedMessage)
		}

		return handler(srv, ss)
	}
}

// BanGinMiddleware rejects REST requests coming from a banned IP address.
// It must be registered after ginEngine.SetTrustedProxies so ClientIP() is trusted-proxy-aware.
func BanGinMiddleware(banRepository *ban.Repository) gin.HandlerFunc {
	return func(ctx *gin.Context) {
		ip := net.ParseIP(ctx.ClientIP())

		if isIPBanned(ctx.Request.Context(), banRepository, ip) {
			ctx.AbortWithStatus(http.StatusForbidden)

			return
		}

		ctx.Next()
	}
}
