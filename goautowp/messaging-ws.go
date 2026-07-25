package goautowp

import (
	"net"
	"net/http"

	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/util"
	"github.com/gin-gonic/gin"
	"github.com/gorilla/websocket"
	"github.com/sirupsen/logrus"
)

const wsAccessTokenParam = "access_token"

// MessagingWS exposes the /ws/messages endpoint: an authenticated, content-free
// notification channel that tells the frontend to refetch its personal-messages list.
type MessagingWS struct {
	hub      *messaging.Hub
	auth     *Auth
	upgrader websocket.Upgrader
}

func NewMessagingWS(hub *messaging.Hub, auth *Auth, corsOrigins []string) *MessagingWS {
	return &MessagingWS{
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

func (s *MessagingWS) SetupRouter(router *gin.Engine) {
	router.GET("/ws/messages", func(ctx *gin.Context) {
		s.serveWS(ctx)
	})
}

func (s *MessagingWS) serveWS(ctx *gin.Context) {
	// Browsers can't set custom headers on a WebSocket upgrade request, so the Keycloak
	// access token travels as a query param instead of the usual Authorization header.
	tokenString := ctx.Query(wsAccessTokenParam)

	remoteAddr := ctx.ClientIP()
	if remoteAddr == "" {
		remoteAddr = defaultRemoteAddr
	}

	ip := net.ParseIP(remoteAddr)

	userCtx, err := s.auth.ValidateToken(ctx.Request.Context(), tokenString, ip)
	if err != nil || userCtx.UserID == 0 {
		ctx.AbortWithStatus(http.StatusUnauthorized)

		return
	}

	conn, err := s.upgrader.Upgrade(ctx.Writer, ctx.Request, nil)
	if err != nil {
		logrus.Error(err.Error())

		return
	}

	client := s.hub.Register(userCtx.UserID, conn)

	go client.WritePump()

	client.ReadPump()
}
