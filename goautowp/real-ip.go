package goautowp

import (
	"net/netip"

	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
)

// Only the ingress network and the SSR process in this pod may supply the client address.
// Strip those same proxy hops from the right of X-Forwarded-For, so a visitor cannot choose
// their address by prepending a forged value to the chain.
func clientIPOptions(trustedNetwork string) []realip.Option {
	trusted := []netip.Prefix{
		netip.MustParsePrefix(trustedNetwork),
		netip.MustParsePrefix("127.0.0.0/8"),
		netip.MustParsePrefix("::1/128"),
	}

	return []realip.Option{
		realip.WithTrustedPeers(trusted),
		realip.WithTrustedProxies(trusted),
		realip.WithHeaders([]string{realip.XForwardedFor}),
	}
}
