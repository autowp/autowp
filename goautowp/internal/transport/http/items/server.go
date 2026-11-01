package items

import (
	"context"
	"errors"
	"fmt"
	"image"
	_ "image/gif"  // GIF support
	_ "image/jpeg" // JPEG support
	_ "image/png"  // PNG support
	"net/http"
	"strconv"

	"github.com/autowp/goautowp/internal/audit"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/image/sampler"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/transport/http/httperrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/gabriel-vasile/mimetype"
	_ "github.com/gen2brain/avif" // AVIF support
	"github.com/gin-gonic/gin"
	_ "golang.org/x/image/bmp"  // BMP support
	_ "golang.org/x/image/webp" // WEBP support
)

const itemLogoFileField = "file"

type Server struct {
	auth       *authn.Service
	repository *items.Repository
	events     *audit.Recorder
}

func NewServer(auth *authn.Service, repository *items.Repository, events *audit.Recorder) *Server {
	return &Server{
		auth:       auth,
		repository: repository,
		events:     events,
	}
}

func (s *Server) SetupRouter(router *gin.Engine) {
	router.POST("/api/item/:id/logo", func(ctx *gin.Context) {
		s.postLogoAction(ctx)
	})
}

func (s *Server) postLogoAction(ctx *gin.Context) {
	userCtx, err := s.auth.ValidateREST(ctx)
	if err != nil {
		s.auth.RESTError(ctx, err)

		return
	}

	if !util.Contains(userCtx.Roles, users.RoleBrandsModer) {
		ctx.Status(http.StatusForbidden)

		return
	}

	itemIDStr := ctx.Param("id")

	itemID, err := strconv.ParseInt(itemIDStr, 10, 64)
	if err != nil {
		ctx.String(http.StatusBadRequest, err.Error())

		return
	}

	file, err := ctx.FormFile(itemLogoFileField)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	if file.Size > items.ItemLogoMaxFileSize {
		ctx.JSON(http.StatusBadRequest, gin.H{
			httperrors.InvalidParams: gin.H{itemLogoFileField: map[string]string{
				httperrors.FileFilesSizeTooBig: fmt.Sprintf(
					"All files in sum should have a maximum size of '%d' but '%d' were detected",
					items.ItemLogoMaxFileSize, file.Size,
				),
			}},
		})

		return
	}

	handle, err := file.Open()
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	defer util.Close(handle)

	mime, err := mimetype.DetectReader(handle)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	if !mime.Is(sampler.ContentTypeImagePNG) {
		ctx.JSON(http.StatusBadRequest, gin.H{
			httperrors.InvalidParams: gin.H{itemLogoFileField: map[string]string{
				httperrors.FileIsImageFalseType: fmt.Sprintf(
					"File is no image, '%s' detected",
					mime.String(),
				),
			}},
		})

		return
	}

	_, err = handle.Seek(0, 0)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	imageConfig, _, err := image.DecodeConfig(handle)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	if imageConfig.Width < items.ItemLogoMinWidth || imageConfig.Height < items.ItemLogoMinHeight {
		ctx.JSON(http.StatusBadRequest, gin.H{
			httperrors.InvalidParams: gin.H{itemLogoFileField: map[string]string{
				"fileImageSizeTooSmall": fmt.Sprintf(
					"Minimum expected size for image should be '%dx%d' but '%dx%d' detected",
					items.ItemLogoMinWidth,
					items.ItemLogoMinHeight,
					imageConfig.Width,
					imageConfig.Height,
				),
			}},
		})

		return
	}

	_, err = handle.Seek(0, 0)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	ctxWithoutCancel := context.WithoutCancel(ctx)

	err = s.repository.SetItemLogo(ctxWithoutCancel, itemID, handle)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			ctx.Status(http.StatusNotFound)

			return
		}

		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	err = s.events.Add(ctxWithoutCancel, audit.Event{
		UserID:  userCtx.UserID,
		Message: "Закачен логотип",
		Items:   []int64{itemID},
	})
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	ctx.Status(http.StatusOK)
}
