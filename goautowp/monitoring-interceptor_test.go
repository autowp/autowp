package goautowp

import (
	"context"
	"net"
	"net/http"
	"net/http/httptest"
	"net/netip"
	"testing"

	"github.com/gin-gonic/gin"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/peer"
)

type fakeRequestRecorder struct{ ips []string }

func (r *fakeRequestRecorder) Record(ip net.IP) { r.ips = append(r.ips, ip.String()) }

func TestMonitoringUnaryUsesTrustedClientIP(t *testing.T) {
	t.Parallel()

	for _, tc := range []struct{ name, peer, method, want string }{
		{"trusted proxy", "10.0.0.1", "/goautowp.Pictures/GetPictures", "192.0.2.1"},
		{"untrusted spoof", "198.51.100.1", "/goautowp.Pictures/GetPictures", "198.51.100.1"},
		{"ban status exempt", "10.0.0.1", banExemptMethod, ""},
	} {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			recorder := &fakeRequestRecorder{}
			ctx := peer.NewContext(t.Context(), &peer.Peer{Addr: &net.TCPAddr{IP: net.ParseIP(tc.peer), Port: 1000}})
			ctx = metadata.NewIncomingContext(ctx, metadata.Pairs("x-forwarded-for", "203.0.113.9, 192.0.2.1"))
			info := &grpc.UnaryServerInfo{FullMethod: tc.method}
			resolve := realip.UnaryServerInterceptorOpts(
				realip.WithTrustedPeers([]netip.Prefix{netip.MustParsePrefix("10.0.0.0/8")}),
				realip.WithHeaders([]string{realip.XForwardedFor}),
			)
			called := false
			_, err := resolve(ctx, nil, info, func(ctx context.Context, req interface{}) (interface{}, error) {
				return monitoringUnaryInterceptor(
					recorder, &fakeMonitoringAuth{},
				)(
					ctx,
					req,
					info,
					func(context.Context, interface{}) (interface{}, error) {
						called = true

						return nil, nil
					},
				)
			})
			require.NoError(t, err)
			require.True(t, called)

			if tc.want == "" {
				require.Empty(t, recorder.ips)
			} else {
				require.Equal(t, []string{tc.want}, recorder.ips)
			}
		})
	}
}

func TestMonitoringRESTSkipsPreflight(t *testing.T) {
	t.Parallel()

	recorder := &fakeRequestRecorder{}
	router := gin.New()
	require.NoError(t, router.SetTrustedProxies([]string{"10.0.0.0/8"}))
	router.Use(monitoringGinMiddleware(recorder, &fakeMonitoringAuth{}))
	router.Any("/api/test", func(c *gin.Context) { c.Status(http.StatusOK) })

	for _, method := range []string{http.MethodGet, http.MethodOptions} {
		req := httptest.NewRequestWithContext(t.Context(), method, "/api/test", nil)
		req.RemoteAddr = "10.0.0.1:1000"
		req.Header.Set("X-Forwarded-For", "192.0.2.1")
		router.ServeHTTP(httptest.NewRecorder(), req)
	}

	require.Equal(t, []string{"192.0.2.1"}, recorder.ips)
}

type fakeMonitoringAuth struct {
	user UserContext
	err  error
}

func (auth *fakeMonitoringAuth) ValidateGRPC(context.Context) (UserContext, error) {
	return auth.user, auth.err
}

func (auth *fakeMonitoringAuth) ValidateREST(*gin.Context) (UserContext, error) {
	return auth.user, auth.err
}

func TestMonitoringModeratorExemption(t *testing.T) {
	t.Parallel()

	for _, tc := range []struct {
		name    string
		user    UserContext
		err     error
		counted bool
	}{
		{name: "moderator", user: UserContext{UserID: 123, Roles: []string{"moder"}}},
		{name: "admin", user: UserContext{UserID: 123, Roles: []string{"admin"}}},
		{name: "ordinary user", user: UserContext{UserID: 123}, counted: true},
		{name: "anonymous", counted: true},
		{
			name:    "unverified roles",
			user:    UserContext{UserID: 123, Roles: []string{"moder"}},
			err:     errAuthTokenIsInvalid,
			counted: true,
		},
		{name: "roles without user", user: UserContext{Roles: []string{"moder"}}, counted: true},
	} {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			auth := &fakeMonitoringAuth{user: tc.user, err: tc.err}
			recorder := &fakeRequestRecorder{}
			ctx := peer.NewContext(
				t.Context(),
				&peer.Peer{Addr: &net.TCPAddr{IP: net.ParseIP("192.0.2.1"), Port: 1000}},
			)
			info := &grpc.UnaryServerInfo{FullMethod: "/goautowp.Pictures/GetPictures"}
			resolve := realip.UnaryServerInterceptorOpts()
			_, err := resolve(ctx, nil, info, func(ctx context.Context, req interface{}) (interface{}, error) {
				return monitoringUnaryInterceptor(
					recorder,
					auth,
				)(
					ctx,
					req,
					info,
					func(ctx context.Context, _ interface{}) (interface{}, error) {
						if tc.err == nil && tc.user.UserID > 0 {
							cached, err := new(Auth).ValidateGRPC(ctx)
							require.NoError(t, err)
							require.Equal(t, tc.user, cached)
						}

						return nil, nil
					},
				)
			})
			require.NoError(t, err)
			require.Equal(t, tc.counted, len(recorder.ips) > 0)

			restRecorder := &fakeRequestRecorder{}
			router := gin.New()
			require.NoError(t, router.SetTrustedProxies(nil))
			router.Use(monitoringGinMiddleware(restRecorder, auth))
			router.GET("/api/test", func(ctx *gin.Context) {
				if tc.err == nil && tc.user.UserID > 0 {
					cached, err := new(Auth).ValidateREST(ctx)
					require.NoError(t, err)
					require.Equal(t, tc.user, cached)
				}

				ctx.Status(http.StatusOK)
			})

			req := httptest.NewRequestWithContext(t.Context(), http.MethodGet, "/api/test", nil)
			req.RemoteAddr = "192.0.2.1:1000"
			router.ServeHTTP(httptest.NewRecorder(), req)
			require.Equal(t, tc.counted, len(restRecorder.ips) > 0)
		})
	}
}
