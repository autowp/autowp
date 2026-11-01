package auth

import (
	"context"
	"net"
)

type userKey struct{}

// User is the authenticated identity attached to one request.
type User struct {
	UserID int64
	Roles  []string
	IP     net.IP
}

// WithUser caches a verified identity for downstream handlers and middleware.
func WithUser(ctx context.Context, user User) context.Context {
	return context.WithValue(ctx, userKey{}, user)
}

func FromContext(ctx context.Context) (User, bool) {
	user, ok := ctx.Value(userKey{}).(User)

	return user, ok
}
