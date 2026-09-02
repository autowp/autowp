package goautowp

import (
	"context"
	"errors"
	"net"
	"net/http"
	"sync"
	"time"

	"github.com/autowp/goautowp/logging"
	"github.com/gin-gonic/gin"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

const errBannedMessage = "banned"

// banExemptMethod stays reachable from a banned address so the Access denied page can call it to
// show the visitor why they were blocked. It only ever reports the caller's own ban status.
const banExemptMethod = "/goautowp.Autowp/GetIP"

const (
	// How long one address' ban lookup is reused before the database is asked again. This check
	// runs ahead of *every* gRPC call and REST request, and a single server-side rendered page
	// fans out into dozens of those, so uncached it spends dozens of ip_ban SELECTs - each one
	// taking a connection out of the bounded pool - re-learning what the previous request learned
	// milliseconds earlier. The price is that a fresh ban (or unban) takes up to this long to be
	// noticed by a pod that has already seen the address; bans are issued on a minutes-to-hours
	// scale (see the autoban worker), so that lag doesn't change what they accomplish.
	banCacheTTL = 10 * time.Second

	// Cap on cached addresses, so a flood of distinct client IPs can't grow the map without bound.
	// Reaching it drops the expired entries, and failing that the whole map - either way the next
	// request for a dropped address just pays for one lookup again.
	banCacheMaxEntries = 10000
)

type banCacheEntry struct {
	banned    bool
	expiresAt time.Time
}

// banRepository is the slice of ban.Repository BanChecker needs, named as an interface so tests
// can count how often the cache below actually reaches the database.
type banRepository interface {
	Exists(ctx context.Context, ip net.IP) (bool, error)
}

// BanChecker answers "is this address banned?" for the interceptors and middleware below, backed
// by ban.Repository and a short-lived per-address cache (see banCacheTTL). One instance is shared
// by the gRPC interceptors and the REST middleware so they share that cache.
type BanChecker struct {
	repository banRepository
	ttl        time.Duration
	mutex      sync.Mutex
	entries    map[string]banCacheEntry
}

// NewBanChecker constructor.
func NewBanChecker(repository banRepository) *BanChecker {
	return &BanChecker{
		repository: repository,
		ttl:        banCacheTTL,
		entries:    make(map[string]banCacheEntry),
	}
}

// IsBanned reports whether requests from ip must be rejected. A lookup failure is reported as
// "not banned": the ban list is a spam control, not an authorization boundary, and failing closed
// would turn a database hiccup into a site-wide outage.
func (s *BanChecker) IsBanned(ctx context.Context, ip net.IP) bool {
	// A loopback or unspecified peer is never a real client. What reaches us over loopback is the
	// SSR frontend container, which shares this pod's network namespace and forwards no
	// X-Forwarded-For, so realip resolves every server-side render to ::1 - a lookup that can only
	// ever answer "not banned", once per gRPC call the render makes.
	if ip == nil || ip.IsLoopback() || ip.IsUnspecified() {
		return false
	}

	key := ip.String()

	if banned, ok := s.cached(key); ok {
		return banned
	}

	banned, err := s.repository.Exists(ctx, ip)
	if err != nil {
		// A canceled context means the caller already walked away (SSR gave up, browser navigated
		// on) - that's a normal outcome under load, not a fault worth a line in the log each time.
		if !errors.Is(err, context.Canceled) {
			logging.Errorf("ban check failed: %s", err.Error())
		}

		// Deliberately not cached: a failed lookup says nothing about this address.
		return false
	}

	s.store(key, banned)

	return banned
}

func (s *BanChecker) cached(key string) (bool, bool) {
	s.mutex.Lock()
	defer s.mutex.Unlock()

	entry, ok := s.entries[key]
	if !ok || time.Now().After(entry.expiresAt) {
		return false, false
	}

	return entry.banned, true
}

func (s *BanChecker) store(key string, banned bool) {
	now := time.Now()

	s.mutex.Lock()
	defer s.mutex.Unlock()

	if len(s.entries) >= banCacheMaxEntries {
		for cached, entry := range s.entries {
			if now.After(entry.expiresAt) {
				delete(s.entries, cached)
			}
		}

		if len(s.entries) >= banCacheMaxEntries {
			s.entries = make(map[string]banCacheEntry)
		}
	}

	s.entries[key] = banCacheEntry{banned: banned, expiresAt: now.Add(s.ttl)}
}

// BanUnaryServerInterceptor rejects gRPC calls coming from a banned IP address.
// It must be chained after the realip interceptor so realip.FromContext is populated.
func BanUnaryServerInterceptor(banChecker *BanChecker) grpc.UnaryServerInterceptor {
	return func(
		ctx context.Context,
		req interface{},
		info *grpc.UnaryServerInfo,
		handler grpc.UnaryHandler,
	) (interface{}, error) {
		if info.FullMethod != banExemptMethod {
			if p, ok := realip.FromContext(ctx); ok && banChecker.IsBanned(ctx, net.ParseIP(p.String())) {
				return nil, status.Error(codes.PermissionDenied, errBannedMessage)
			}
		}

		return handler(ctx, req)
	}
}

// BanStreamServerInterceptor rejects gRPC streams coming from a banned IP address.
// It must be chained after the realip interceptor so realip.FromContext is populated.
func BanStreamServerInterceptor(banChecker *BanChecker) grpc.StreamServerInterceptor {
	return func(
		srv interface{},
		ss grpc.ServerStream,
		_ *grpc.StreamServerInfo,
		handler grpc.StreamHandler,
	) error {
		ctx := ss.Context()

		if p, ok := realip.FromContext(ctx); ok && banChecker.IsBanned(ctx, net.ParseIP(p.String())) {
			return status.Error(codes.PermissionDenied, errBannedMessage)
		}

		return handler(srv, ss)
	}
}

// BanGinMiddleware rejects REST requests coming from a banned IP address.
// It must be registered after ginEngine.SetTrustedProxies so ClientIP() is trusted-proxy-aware.
func BanGinMiddleware(banChecker *BanChecker) gin.HandlerFunc {
	return func(ctx *gin.Context) {
		ip := net.ParseIP(ctx.ClientIP())

		if banChecker.IsBanned(ctx.Request.Context(), ip) {
			ctx.AbortWithStatus(http.StatusForbidden)

			return
		}

		ctx.Next()
	}
}
