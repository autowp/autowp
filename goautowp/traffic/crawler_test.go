package traffic

import (
	"context"
	"errors"
	"net"
	"testing"

	"github.com/stretchr/testify/require"
)

var errTestResolverUnavailable = errors.New("resolver unavailable")

type fakeCrawlerResolver struct {
	hosts        []string
	addresses    []net.IPAddr
	err          error
	forwardCalls int
}

func (r *fakeCrawlerResolver) LookupAddr(context.Context, string) ([]string, error) {
	return r.hosts, r.err
}

func (r *fakeCrawlerResolver) LookupIPAddr(context.Context, string) ([]net.IPAddr, error) {
	r.forwardCalls++

	return r.addresses, r.err
}

func TestCrawlerVerification(t *testing.T) {
	t.Parallel()

	for _, tc := range []struct {
		name, host, address string
		valid               bool
	}{
		{"google", "crawl-any-name.googlebot.com.", "66.249.73.139", true},
		{"bing", "arbitrary.search.msn.com.", "66.249.73.139", true},
		{"yandex", "spider.yandex.com.", "66.249.73.139", true},
		{"spoofed PTR", "crawl.googlebot.com.", "192.0.2.1", false},
		{"suffix trick", "googlebot.com.attacker.example.", "66.249.73.139", false},
		{"missing domain boundary", "fakegooglebot.com.", "66.249.73.139", false},
		{"ipv6", "crawler.googlebot.com.", "2001:db8::1", true},
	} {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			resolver := &fakeCrawlerResolver{
				hosts:     []string{tc.host},
				addresses: []net.IPAddr{{IP: net.ParseIP(tc.address)}},
			}

			ip := net.ParseIP("66.249.73.139")
			if tc.name == "ipv6" {
				ip = net.ParseIP(tc.address)
			}

			valid, _, err := matchCrawler(t.Context(), resolver, ip)
			require.NoError(t, err)
			require.Equal(t, tc.valid, valid)
		})
	}
}

func TestCrawlerDNSError(t *testing.T) {
	t.Parallel()

	resolver := &fakeCrawlerResolver{err: errTestResolverUnavailable}
	valid, _, err := matchCrawler(t.Context(), resolver, net.ParseIP("66.249.73.139"))
	require.Error(t, err)
	require.False(t, valid)
}
