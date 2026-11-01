package pictures

import (
	"sync"
	"time"

	"github.com/gorilla/websocket"
)

const (
	wsSendBufferSize = 8
	wsWriteWait      = 10 * time.Second
	wsPongWait       = 60 * time.Second
	wsPingPeriod     = wsPongWait * 9 / 10
	// wsReadLimit caps inbound frame size; clients never legitimately send anything.
	wsReadLimit = 512
)

// newPictureFrame is the only payload ever pushed to clients: a content-free signal
// telling the frontend to refetch the "new pictures" list. It never carries picture data.
var newPictureFrame = []byte(`{"type":"new_picture"}`)

// WSClient is a single live WebSocket connection subscribed to accept notifications.
type WSClient struct {
	hub  *Hub
	conn *websocket.Conn
	send chan []byte
}

// Hub is the in-process registry of live /ws/pictures connections for this server
// instance. Unlike messaging's per-user Hub, this one broadcasts to every connection:
// "new picture accepted" is public information, not scoped to a user.
type Hub struct {
	mu      sync.Mutex
	clients map[*WSClient]struct{}
}

func NewHub() *Hub {
	return &Hub{
		clients: make(map[*WSClient]struct{}),
	}
}

// Register creates and tracks a WSClient. Callers must invoke ReadPump (blocking) and
// WritePump (in its own goroutine) on the returned client.
func (h *Hub) Register(conn *websocket.Conn) *WSClient {
	client := &WSClient{
		hub:  h,
		conn: conn,
		send: make(chan []byte, wsSendBufferSize),
	}

	h.mu.Lock()
	defer h.mu.Unlock()

	h.clients[client] = struct{}{}

	return client
}

// Broadcast pushes a content-free "new picture" frame to every live connection on this
// server instance.
func (h *Hub) Broadcast() {
	h.mu.Lock()
	defer h.mu.Unlock()

	for client := range h.clients {
		select {
		case client.send <- newPictureFrame:
		default:
			// Slow consumer: close it instead of blocking the hub. ReadPump will
			// observe the close and unregister the client.
			go client.conn.Close()
		}
	}
}

func (h *Hub) unregister(client *WSClient) {
	h.mu.Lock()
	defer h.mu.Unlock()

	delete(h.clients, client)
}

// WritePump owns all writes to the connection (gorilla/websocket connections do not
// support concurrent writers) and keeps it alive with periodic pings. Run in its own
// goroutine; returns when the connection is closed.
func (c *WSClient) WritePump() {
	ticker := time.NewTicker(wsPingPeriod)

	defer func() {
		ticker.Stop()
		c.conn.Close()
	}()

	for {
		select {
		case message, ok := <-c.send:
			_ = c.conn.SetWriteDeadline(time.Now().Add(wsWriteWait))

			if !ok {
				_ = c.conn.WriteMessage(websocket.CloseMessage, []byte{})

				return
			}

			if err := c.conn.WriteMessage(websocket.TextMessage, message); err != nil {
				return
			}
		case <-ticker.C:
			_ = c.conn.SetWriteDeadline(time.Now().Add(wsWriteWait))

			if err := c.conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}

// ReadPump discards any client-sent frames (the browser never sends anything meaningful)
// and only exists to detect disconnects/pong keepalives. Blocks until the connection
// closes, then unregisters the client. Call directly (not in a goroutine).
func (c *WSClient) ReadPump() {
	defer func() {
		c.hub.unregister(c)
		c.conn.Close()
	}()

	c.conn.SetReadLimit(wsReadLimit)
	_ = c.conn.SetReadDeadline(time.Now().Add(wsPongWait))
	c.conn.SetPongHandler(func(string) error {
		return c.conn.SetReadDeadline(time.Now().Add(wsPongWait))
	})

	for {
		if _, _, err := c.conn.ReadMessage(); err != nil {
			return
		}
	}
}
