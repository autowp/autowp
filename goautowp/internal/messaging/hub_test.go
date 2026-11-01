package messaging

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
	testWaitTimeout   = time.Second
	testPollInterval  = 10 * time.Millisecond
	testNoMsgDeadline = 200 * time.Millisecond
)

func TestHubBroadcastFiltersByUserID(t *testing.T) {
	t.Parallel()

	hub := NewHub()

	upgrader := websocket.Upgrader{}

	server := httptest.NewServer(http.HandlerFunc(func(resp http.ResponseWriter, req *http.Request) {
		userID := int64(1)
		if strings.HasSuffix(req.URL.Path, "/2") {
			userID = 2
		}

		conn, err := upgrader.Upgrade(resp, req, nil)
		if !assert.NoError(t, err) {
			return
		}

		client := hub.Register(userID, conn)

		go client.WritePump()

		client.ReadPump()
	}))
	defer server.Close()

	wsURL := "ws" + strings.TrimPrefix(server.URL, "http")

	connA, respA, err := websocket.DefaultDialer.Dial(wsURL+"/1", nil)
	require.NoError(t, err)

	defer connA.Close()
	defer respA.Body.Close()

	connB, respB, err := websocket.DefaultDialer.Dial(wsURL+"/2", nil)
	require.NoError(t, err)

	defer connB.Close()
	defer respB.Body.Close()

	require.Eventually(t, func() bool {
		hub.mu.Lock()
		defer hub.mu.Unlock()

		return len(hub.clients[1]) == 1 && len(hub.clients[2]) == 1
	}, testWaitTimeout, testPollInterval)

	hub.Broadcast([]int64{1})

	require.NoError(t, connA.SetReadDeadline(time.Now().Add(testWaitTimeout)))

	_, msg, err := connA.ReadMessage()
	require.NoError(t, err)
	require.Equal(t, messagesChangedFrame, msg)

	require.NoError(t, connB.SetReadDeadline(time.Now().Add(testNoMsgDeadline)))
	_, _, err = connB.ReadMessage()
	require.Error(t, err, "user 2 was not targeted by the broadcast and should receive nothing")
}
