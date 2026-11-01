package messaging

import (
	"sync"
	"time"

	"github.com/gorilla/websocket"
)

const (
	sendBufferSize = 8
	writeWait      = 10 * time.Second
	pongWait       = 60 * time.Second
	pingPeriod     = pongWait * 9 / 10
	// readLimit caps inbound frame size; clients never legitimately send anything.
	readLimit = 512
)

// messagesChangedFrame is the only payload ever pushed to clients: a content-free signal
// telling the frontend to refetch its message list. It never carries message text or IDs.
var messagesChangedFrame = []byte(`{"type":"messages"}`)

// Client is a single live WebSocket connection belonging to userID.
type Client struct {
	hub    *Hub
	userID int64
	conn   *websocket.Conn
	send   chan []byte
}

// Hub is the in-process registry of live connections for this server instance.
type Hub struct {
	mu      sync.Mutex
	clients map[int64]map[*Client]struct{}
}

func NewHub() *Hub {
	return &Hub{
		clients: make(map[int64]map[*Client]struct{}),
	}
}

// Register creates and tracks a Client for userID. Callers must invoke ReadPump (blocking)
// and WritePump (in its own goroutine) on the returned Client.
func (h *Hub) Register(userID int64, conn *websocket.Conn) *Client {
	client := &Client{
		hub:    h,
		userID: userID,
		conn:   conn,
		send:   make(chan []byte, sendBufferSize),
	}

	h.mu.Lock()
	defer h.mu.Unlock()

	if h.clients[userID] == nil {
		h.clients[userID] = make(map[*Client]struct{})
	}

	h.clients[userID][client] = struct{}{}

	return client
}

// Broadcast pushes a content-free "messages changed" frame to every live connection
// belonging to any of userIDs, on this server instance only.
func (h *Hub) Broadcast(userIDs []int64) {
	h.mu.Lock()
	defer h.mu.Unlock()

	for _, userID := range userIDs {
		for client := range h.clients[userID] {
			select {
			case client.send <- messagesChangedFrame:
			default:
				// Slow consumer: close it instead of blocking the hub. ReadPump will
				// observe the close and unregister the client.
				go client.conn.Close()
			}
		}
	}
}

func (h *Hub) unregister(client *Client) {
	h.mu.Lock()
	defer h.mu.Unlock()

	conns, ok := h.clients[client.userID]
	if !ok {
		return
	}

	delete(conns, client)

	if len(conns) == 0 {
		delete(h.clients, client.userID)
	}
}

// WritePump owns all writes to the connection (gorilla/websocket connections do not
// support concurrent writers) and keeps it alive with periodic pings. Run in its own
// goroutine; returns when the connection is closed.
func (c *Client) WritePump() {
	ticker := time.NewTicker(pingPeriod)

	defer func() {
		ticker.Stop()
		c.conn.Close()
	}()

	for {
		select {
		case message, ok := <-c.send:
			_ = c.conn.SetWriteDeadline(time.Now().Add(writeWait))

			if !ok {
				_ = c.conn.WriteMessage(websocket.CloseMessage, []byte{})

				return
			}

			if err := c.conn.WriteMessage(websocket.TextMessage, message); err != nil {
				return
			}
		case <-ticker.C:
			_ = c.conn.SetWriteDeadline(time.Now().Add(writeWait))

			if err := c.conn.WriteMessage(websocket.PingMessage, nil); err != nil {
				return
			}
		}
	}
}

// ReadPump discards any client-sent frames (the browser never sends anything meaningful)
// and only exists to detect disconnects/pong keepalives. Blocks until the connection
// closes, then unregisters the client. Call directly (not in a goroutine).
func (c *Client) ReadPump() {
	defer func() {
		c.hub.unregister(c)
		c.conn.Close()
	}()

	c.conn.SetReadLimit(readLimit)
	_ = c.conn.SetReadDeadline(time.Now().Add(pongWait))
	c.conn.SetPongHandler(func(string) error {
		return c.conn.SetReadDeadline(time.Now().Add(pongWait))
	})

	for {
		if _, _, err := c.conn.ReadMessage(); err != nil {
			return
		}
	}
}
