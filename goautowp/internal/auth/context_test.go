package auth_test

import (
	"net"
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/autowp/goautowp/internal/auth"
	"github.com/gin-gonic/gin"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func TestWithUser(t *testing.T) {
	t.Parallel()

	parent := t.Context()
	_, ok := auth.FromContext(parent)
	require.False(t, ok)

	user := auth.User{UserID: 7, Roles: []string{"moder"}, IP: net.ParseIP("192.0.2.1")}
	ctx := auth.WithUser(parent, user)
	actual, ok := auth.FromContext(ctx)
	require.True(t, ok)
	require.Equal(t, user, actual)

	child := auth.WithUser(ctx, auth.User{})
	actual, ok = auth.FromContext(child)
	require.True(t, ok, "an explicitly cached anonymous identity is present")
	require.Empty(t, actual)
	actual, ok = auth.FromContext(ctx)
	require.True(t, ok)
	require.Equal(t, user, actual, "overriding a child must not change the parent")
}

func TestServiceUsesCachedIdentity(t *testing.T) {
	t.Parallel()

	for _, user := range []auth.User{{}, {UserID: 7, Roles: []string{"moder"}, IP: net.ParseIP("192.0.2.1")}} {
		ctx := auth.WithUser(t.Context(), user)
		service := new(auth.Service)

		actual, err := service.ValidateGRPC(ctx)
		require.NoError(t, err)
		require.Equal(t, user, actual)

		recorder := httptest.NewRecorder()
		rest, _ := gin.CreateTestContext(recorder)
		rest.Request = httptest.NewRequestWithContext(ctx, http.MethodGet, "/", nil)
		rest.Request.Header.Set(auth.AuthorizationHeader, "Bearer invalid-token")
		actual, err = service.ValidateREST(rest)
		require.NoError(t, err)
		require.Equal(t, user, actual, "cached identity takes precedence over credentials")
	}
}

func TestServiceMissingCredentials(t *testing.T) {
	t.Parallel()

	service := new(auth.Service)
	_, err := service.ValidateGRPC(t.Context())
	require.ErrorIs(t, err, auth.ErrMissingMetadata)

	ctx := metadata.NewIncomingContext(t.Context(), metadata.MD{})
	user, err := service.ValidateGRPC(ctx)
	require.NoError(t, err)
	require.Empty(t, user)

	ctx = metadata.NewIncomingContext(t.Context(), metadata.Pairs(auth.AuthorizationHeader, "Bearer "))
	_, err = service.ValidateGRPC(ctx)
	require.ErrorIs(t, err, auth.ErrInvalidToken)

	rest, _ := gin.CreateTestContext(httptest.NewRecorder())
	rest.Request = httptest.NewRequestWithContext(t.Context(), http.MethodGet, "/", nil)
	user, err = service.ValidateREST(rest)
	require.NoError(t, err)
	require.Empty(t, user)
}
