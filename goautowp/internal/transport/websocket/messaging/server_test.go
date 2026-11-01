package messaging

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/messaging"
	"github.com/gin-gonic/gin"
	"github.com/stretchr/testify/require"
)

func TestMissingToken(t *testing.T) {
	t.Parallel()

	router := gin.New()
	NewServer(messaging.NewHub(), new(auth.Service), nil).SetupRouter(router)

	request := httptest.NewRequestWithContext(t.Context(), http.MethodGet, "/ws/messages", nil)
	response := httptest.NewRecorder()
	router.ServeHTTP(response, request)
	require.Equal(t, http.StatusUnauthorized, response.Code)
}
