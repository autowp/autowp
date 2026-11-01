package messaging

import (
	"net"
	"net/http"

	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/logging"
	"github.com/autowp/goautowp/internal/messaging"
	"github.com/autowp/goautowp/internal/util"
	"github.com/gin-gonic/gin"
	"github.com/gorilla/websocket"
)

const wsAccessTokenParam = "access_token"

// Server exposes the /ws/messages endpoint: an authenticated, content-free
// notification channel that tells the frontend to refetch its personal-messages list.
type Server struct {
	hub      *messaging.Hub
	auth     *authn.Service
	upgrader websocket.Upgrader
}

func NewServer(hub *messaging.Hub, auth *authn.Service, corsOrigins []string) *Server {
	return &Server{
		hub:  hub,
		auth: auth,
		upgrader: websocket.Upgrader{
			CheckOrigin: func(req *http.Request) bool {
				origin := req.Header.Get("Origin")

				return origin == "" || util.Contains(corsOrigins, origin)
			},
		},
	}
}

func (s *Server) SetupRouter(router *gin.Engine) {
	router.GET("/ws/messages", func(ctx *gin.Context) {
		s.serveWS(ctx)
	})
}

func (s *Server) serveWS(ctx *gin.Context) {
	// Browsers can't set custom headers on a WebSocket upgrade request, so the Keycloak
	// access token travels as a query param instead of the usual Authorization header.
	tokenString := ctx.Query(wsAccessTokenParam)

	remoteAddr := ctx.ClientIP()
	if remoteAddr == "" {
		remoteAddr = authn.DefaultRemoteAddr
	}

	ip := net.ParseIP(remoteAddr)

	userCtx, err := s.auth.ValidateToken(ctx.Request.Context(), tokenString, ip)
	if err != nil || userCtx.UserID == 0 {
		ctx.AbortWithStatus(http.StatusUnauthorized)

		return
	}

	conn, err := s.upgrader.Upgrade(ctx.Writer, ctx.Request, nil)
	if err != nil {
		logging.Error(err.Error())

		return
	}

	client := s.hub.Register(userCtx.UserID, conn)

	go client.WritePump()

	client.ReadPump()
}
