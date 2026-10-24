package traffic

import (
	"context"
	"errors"
	"net"
	"testing"
	"time"

	"github.com/stretchr/testify/require"
)

var errTestResolverUnavailable = errors.New("resolver unavailable")

type fakeCrawlerResolver struct {
	hosts        []string
	addresses    []net.IPAddr
	err          error
	forwardErr   error
	cancel       context.CancelFunc
	forwardCalls int
}

func (r *fakeCrawlerResolver) LookupAddr(context.Context, string) ([]string, error) {
	if r.cancel != nil {
		r.cancel()
	}

	return r.hosts, r.err
}

func (r *fakeCrawlerResolver) LookupIPAddr(context.Context, string) ([]net.IPAddr, error) {
	r.forwardCalls++

	return r.addresses, r.forwardErr
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

func TestCrawlerDNSFailureSkipsIP(t *testing.T) {
	t.Parallel()

	for _, forward := range []bool{false, true} {
		for _, dnsErr := range []*net.DNSError{
			{Err: "server misbehaving", IsTemporary: true},
			{Err: "i/o timeout", IsTimeout: true},
			{Err: "no such host", IsNotFound: true},
		} {
			resolver := &fakeCrawlerResolver{hosts: []string{"crawl.googlebot.com."}}
			if forward {
				resolver.forwardErr = dnsErr
			} else {
				resolver.err = dnsErr
			}

			valid, desc, err := matchCrawler(t.Context(), resolver, net.ParseIP("216.73.217.127"))
			require.NoError(t, err)
			require.False(t, valid)
			require.Empty(t, desc)

			// The next IP can still be verified after a DNS failure.
			resolver.err = nil
			resolver.forwardErr = nil
			resolver.addresses = []net.IPAddr{{IP: net.ParseIP("66.249.73.139")}}
			valid, _, err = matchCrawler(t.Context(), resolver, net.ParseIP("66.249.73.139"))
			require.NoError(t, err)
			require.True(t, valid)
		}
	}
}

func TestCrawlerDNSFailurePreservesCallerCancellation(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithCancel(t.Context())
	defer cancel()

	resolver := &fakeCrawlerResolver{
		err:    &net.DNSError{Err: "operation was canceled"},
		cancel: cancel,
	}
	valid, _, err := matchCrawler(ctx, resolver, net.ParseIP("216.73.217.127"))
	require.ErrorIs(t, err, context.Canceled)
	require.False(t, valid)
}

func TestCrawlerDNSFailurePreservesCallerDeadline(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithDeadline(t.Context(), time.Now().Add(-time.Second))
	defer cancel()

	resolver := &fakeCrawlerResolver{err: &net.DNSError{Err: "i/o timeout", IsTimeout: true}}
	valid, _, err := matchCrawler(ctx, resolver, net.ParseIP("216.73.217.127"))
	require.ErrorIs(t, err, context.DeadlineExceeded)
	require.False(t, valid)
}
