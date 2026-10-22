package goautowp

import (
	"context"
	"net"
	"testing"

	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/peer"
	"google.golang.org/grpc/status"
)

func TestSSRForwardedClientIP(t *testing.T) {
	t.Parallel()

	for _, tc := range []struct {
		name      string
		peer      string
		forwarded string
		want      string
		internal  bool
	}{
		{name: "SSR IPv4", peer: "127.0.0.1", forwarded: "192.0.2.1", want: "192.0.2.1"},
		{name: "SSR IPv6", peer: "::1", forwarded: "2001:db8::1", want: "2001:db8::1"},
		{name: "SSR proxy chain", peer: "::1", forwarded: "203.0.113.9, 192.0.2.1, 10.244.0.5", want: "192.0.2.1"},
		{name: "direct forged header", peer: "198.51.100.1", forwarded: "192.0.2.1", want: "198.51.100.1"},
		{name: "ingress chain", peer: "10.244.0.5", forwarded: "203.0.113.9, 192.0.2.1", want: "192.0.2.1"},
		{name: "internal call", peer: "::1", want: "::1", internal: true},
	} {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			for _, banned := range []bool{false, true} {
				repository := &fakeBanRepository{banned: banned}
				checker := NewBanChecker(repository)
				recorder := &fakeRequestRecorder{}
				ctx := peer.NewContext(
					t.Context(),
					&peer.Peer{Addr: &net.TCPAddr{IP: net.ParseIP(tc.peer), Port: 1000}},
				)
				ctx = metadata.NewIncomingContext(ctx, metadata.Pairs("x-forwarded-for", tc.forwarded))
				info := &grpc.UnaryServerInfo{FullMethod: "/goautowp.Pictures/GetPicture"}
				resolve := realip.UnaryServerInterceptorOpts(clientIPOptions("10.244.0.0/16")...)
				called := false

				_, err := resolve(ctx, nil, info, func(ctx context.Context, req interface{}) (interface{}, error) {
					ip, ok := realip.FromContext(ctx)
					require.True(t, ok)
					require.Equal(t, tc.want, ip.String())

					return BanUnaryServerInterceptor(
						checker,
					)(
						ctx,
						req,
						info,
						func(ctx context.Context, req interface{}) (interface{}, error) {
							return monitoringUnaryInterceptor(
								recorder,
								&fakeMonitoringAuth{},
							)(
								ctx,
								req,
								info,
								func(context.Context, interface{}) (interface{}, error) {
									called = true

									return nil, nil
								},
							)
						},
					)
				})
				if banned && !tc.internal {
					require.Equal(t, codes.PermissionDenied, status.Code(err))
					require.False(t, called)
					require.Empty(t, recorder.ips)
				} else {
					require.NoError(t, err)
					require.True(t, called)
					require.Equal(t, []string{tc.want}, recorder.ips)
				}

				if tc.internal {
					require.Zero(t, repository.callCount())
				} else {
					require.Equal(t, 1, repository.callCount())
				}
			}
		})
	}
}
