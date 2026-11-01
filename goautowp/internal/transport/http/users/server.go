package users

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

const userPhotoFileField = "photo"

type Server struct {
	auth       *authn.Service
	repository *users.Repository
}

func NewServer(auth *authn.Service, repository *users.Repository) *Server {
	return &Server{
		auth:       auth,
		repository: repository,
	}
}

func (s *Server) SetupRouter(router *gin.Engine) {
	router.POST("/api/user/:id/photo", func(ctx *gin.Context) {
		s.postPhotoAction(ctx)
	})
}

func (s *Server) postPhotoAction(ctx *gin.Context) {
	userCtx, err := s.auth.ValidateREST(ctx)
	if err != nil {
		s.auth.RESTError(ctx, err)

		return
	}

	userIDStr := ctx.Param("id")

	updateUserID, err := strconv.ParseInt(userIDStr, 10, 64)
	if err != nil {
		ctx.String(http.StatusBadRequest, err.Error())

		return
	}

	if updateUserID != userCtx.UserID {
		ctx.Status(http.StatusForbidden)

		return
	}

	file, err := ctx.FormFile(userPhotoFileField)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	if file.Size > users.UserPhotoMaxFileSize {
		ctx.JSON(http.StatusBadRequest, httperrors.BadRequestResponse{
			InvalidParams: map[string]map[string]string{userPhotoFileField: {
				httperrors.FileFilesSizeTooBig: fmt.Sprintf(
					"All files in sum should have a maximum size of '%d' but '%d' were detected",
					users.UserPhotoMaxFileSize, file.Size,
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

	allowedMimes := []string{
		sampler.ContentTypeImageBMP,
		sampler.ContentTypeImageGIF,
		sampler.ContentTypeImageJPEG,
		sampler.ContentTypeImagePNG,
		sampler.ContentTypeImageWebP,
		sampler.ContentTypeImageAVIF,
		sampler.ContentTypeImageXPNG,
	}

	mimeIsAllowed := false

	for _, allowedMime := range allowedMimes {
		if mime.Is(allowedMime) {
			mimeIsAllowed = true

			break
		}
	}

	if !mimeIsAllowed {
		ctx.JSON(http.StatusBadRequest, httperrors.BadRequestResponse{
			InvalidParams: map[string]map[string]string{userPhotoFileField: {
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

	if imageConfig.Width < users.UserPhotoMinWidth ||
		imageConfig.Height < users.UserPhotoMinHeight {
		ctx.JSON(http.StatusBadRequest, httperrors.BadRequestResponse{
			InvalidParams: map[string]map[string]string{userPhotoFileField: {
				"fileImageSizeTooSmall": fmt.Sprintf(
					"Minimum expected size for image should be '%dx%d' but '%dx%d' detected",
					users.UserPhotoMinWidth,
					users.UserPhotoMinHeight,
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

	err = s.repository.SetUserPhoto(ctxWithoutCancel, userCtx.UserID, handle)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			ctx.Status(http.StatusNotFound)

			return
		}

		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	ctx.Status(http.StatusOK)
}
