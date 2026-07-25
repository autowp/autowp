package pictures

import (
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"
	"time"

	"github.com/gorilla/websocket"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

const (
	testWaitTimeout  = time.Second
	testPollInterval = 10 * time.Millisecond
)

func TestHubBroadcastReachesAllClients(t *testing.T) {
	t.Parallel()

	hub := NewHub()

	upgrader := websocket.Upgrader{}

	server := httptest.NewServer(http.HandlerFunc(func(resp http.ResponseWriter, req *http.Request) {
		conn, err := upgrader.Upgrade(resp, req, nil)
		if !assert.NoError(t, err) {
			return
		}

		client := hub.Register(conn)

		go client.WritePump()

		client.ReadPump()
	}))
	defer server.Close()

	wsURL := "ws" + strings.TrimPrefix(server.URL, "http")

	connA, respA, err := websocket.DefaultDialer.Dial(wsURL, nil)
	require.NoError(t, err)

	defer connA.Close()
	defer respA.Body.Close()

	connB, respB, err := websocket.DefaultDialer.Dial(wsURL, nil)
	require.NoError(t, err)

	defer connB.Close()
	defer respB.Body.Close()

	require.Eventually(t, func() bool {
		hub.mu.Lock()
		defer hub.mu.Unlock()

		return len(hub.clients) == 2
	}, testWaitTimeout, testPollInterval)

	hub.Broadcast()

	for _, conn := range []*websocket.Conn{connA, connB} {
		require.NoError(t, conn.SetReadDeadline(time.Now().Add(testWaitTimeout)))

		_, msg, err := conn.ReadMessage()
		require.NoError(t, err)
		require.Equal(t, newPictureFrame, msg)
	}
}
