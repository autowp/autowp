package pictures

import (
	"net/http"

	"github.com/autowp/goautowp/internal/logging"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/util"
	"github.com/gin-gonic/gin"
	"github.com/gorilla/websocket"
)

// Server exposes the /ws/pictures endpoint: an unauthenticated, content-free
// notification channel that tells the frontend to refetch the "new pictures" list.
// Unlike /ws/messages, no auth is needed — accepted pictures are public information.
type Server struct {
	hub      *pictures.Hub
	upgrader websocket.Upgrader
}

func NewServer(hub *pictures.Hub, corsOrigins []string) *Server {
	return &Server{
		hub: hub,
		upgrader: websocket.Upgrader{
			CheckOrigin: func(req *http.Request) bool {
				origin := req.Header.Get("Origin")

				return origin == "" || util.Contains(corsOrigins, origin)
			},
		},
	}
}

func (s *Server) SetupRouter(router *gin.Engine) {
	router.GET("/ws/pictures", func(ctx *gin.Context) {
		s.serveWS(ctx)
	})
}

func (s *Server) serveWS(ctx *gin.Context) {
	conn, err := s.upgrader.Upgrade(ctx.Writer, ctx.Request, nil)
	if err != nil {
		logging.Error(err.Error())

		return
	}

	client := s.hub.Register(conn)

	go client.WritePump()

	client.ReadPump()
}
