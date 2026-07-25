package goautowp

import (
	"net/http"

	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/util"
	"github.com/gin-gonic/gin"
	"github.com/gorilla/websocket"
	"github.com/sirupsen/logrus"
)

// PicturesWS exposes the /ws/pictures endpoint: an unauthenticated, content-free
// notification channel that tells the frontend to refetch the "new pictures" list.
// Unlike /ws/messages, no auth is needed — accepted pictures are public information.
type PicturesWS struct {
	hub      *pictures.Hub
	upgrader websocket.Upgrader
}

func NewPicturesWS(hub *pictures.Hub, corsOrigins []string) *PicturesWS {
	return &PicturesWS{
		hub: hub,
		upgrader: websocket.Upgrader{
			CheckOrigin: func(req *http.Request) bool {
				origin := req.Header.Get("Origin")

				return origin == "" || util.Contains(corsOrigins, origin)
			},
		},
	}
}

func (s *PicturesWS) SetupRouter(router *gin.Engine) {
	router.GET("/ws/pictures", func(ctx *gin.Context) {
		s.serveWS(ctx)
	})
}

func (s *PicturesWS) serveWS(ctx *gin.Context) {
	conn, err := s.upgrader.Upgrade(ctx.Writer, ctx.Request, nil)
	if err != nil {
		logrus.Error(err.Error())

		return
	}

	client := s.hub.Register(conn)

	go client.WritePump()

	client.ReadPump()
}
