package pictures

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/autowp/goautowp/internal/pictures"
	"github.com/gin-gonic/gin"
	"github.com/gorilla/websocket"
	"github.com/stretchr/testify/require"
)

func TestPublicUpgradeOrigin(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		name   string
		origin string
		status int
	}{
		{name: "allowed", origin: "https://wheelsage.org", status: http.StatusSwitchingProtocols},
		{name: "no origin", status: http.StatusSwitchingProtocols},
		{name: "forbidden", origin: "https://example.org", status: http.StatusForbidden},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			router := gin.New()
			NewServer(pictures.NewHub(), []string{"https://wheelsage.org"}).SetupRouter(router)

			server := httptest.NewServer(router)
			defer server.Close()

			headers := http.Header{}
			if test.origin != "" {
				headers.Set("Origin", test.origin)
			}

			conn, response, err := websocket.DefaultDialer.DialContext(
				t.Context(), "ws"+strings.TrimPrefix(server.URL, "http")+"/ws/pictures", headers,
			)
			require.NotNil(t, response)
			require.Equal(t, test.status, response.StatusCode)

			if test.status == http.StatusSwitchingProtocols {
				require.NoError(t, err)
				require.NoError(t, conn.Close())
			} else {
				require.Error(t, err)
				require.Nil(t, conn)
				require.NoError(t, response.Body.Close())
			}
		})
	}
}
