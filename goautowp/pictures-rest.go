package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"mime/multipart"
	"net/http"
	"strconv"

	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/image/sampler"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/itemofday"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/telegram"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"github.com/gabriel-vasile/mimetype"
	"github.com/gin-gonic/gin"
	"github.com/gin-gonic/gin/binding"
	"golang.org/x/text/language"
)

const (
	pictureFileField             = "file"
	pictureCommentField          = "comment"
	pictureItemIDField           = "item_id"
	pictureReplacePictureIDField = "replace_picture_id"
	picturePerspectiveID         = "perspective_id"
)

type PicturesREST struct {
	auth                 *Auth
	picturesRepository   *pictures.Repository
	pictureNameFormatter *pictures.PictureNameFormatter
	hostManager          *hosts.Manager
	imageStorage         *storage.Storage
	itemOfDay            *itemofday.Repository
	itemsRepository      *items.Repository
	usersRepository      *users.Repository
	commentsRepository   *comments.Repository
	duplicateFinder      *DuplicateFinder
	telegramService      *telegram.Service
	itemOfDayCached      *ItemOfDayCached
}

func NewPicturesREST(
	auth *Auth,
	picturesRepository *pictures.Repository,
	pictureNameFormatter *pictures.PictureNameFormatter,
	hostManager *hosts.Manager,
	imageStorage *storage.Storage,
	itemOfDay *itemofday.Repository,
	itemsRepository *items.Repository,
	usersRepository *users.Repository,
	commentsRepository *comments.Repository,
	duplicateFinder *DuplicateFinder,
	telegramService *telegram.Service,
	itemOfDayCached *ItemOfDayCached,
) *PicturesREST {
	return &PicturesREST{
		auth:                 auth,
		picturesRepository:   picturesRepository,
		pictureNameFormatter: pictureNameFormatter,
		hostManager:          hostManager,
		imageStorage:         imageStorage,
		itemOfDay:            itemOfDay,
		itemsRepository:      itemsRepository,
		usersRepository:      usersRepository,
		commentsRepository:   commentsRepository,
		duplicateFinder:      duplicateFinder,
		telegramService:      telegramService,
		itemOfDayCached:      itemOfDayCached,
	}
}

type PicturePostForm struct {
	File             *multipart.FileHeader `form:"file"`
	Comment          string                `form:"comment"            json:"comment"`
	ItemID           int64                 `form:"item_id"            json:"item_id"`
	ReplacePictureID int64                 `form:"replace_picture_id" json:"replace_picture_id"`
	PerspectiveID    int32                 `form:"perspective_id"     json:"perspective_id"`
}

func (s *PicturePostForm) Validate() (map[string]map[string]string, error) {
	var (
		result   = make(map[string]map[string]string)
		problems []string
		err      error
	)

	commentInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Max: comments.MaxMessageLength},
		},
	}

	s.Comment, problems, err = commentInputFilter.IsValidString(s.Comment)
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result["comment"]["commentInvalid"] = fv
	}

	return result, nil
}

func (s *PicturesREST) SetupRouter(router *gin.Engine) {
	router.GET("/api/picture/random-picture", func(ctx *gin.Context) {
		s.handlePicture(ctx, pictures.OrderByRandom)
	})

	router.GET("/api/picture/new-picture", func(ctx *gin.Context) {
		s.handlePicture(ctx, pictures.OrderByAcceptDatetimeDesc)
	})

	router.GET("/api/picture/car-of-day-picture", func(ctx *gin.Context) {
		s.handleItemOfDayPicture(ctx)
	})

	router.POST("/api/picture", func(ctx *gin.Context) {
		s.handlePicturePOST(ctx)
	})
}

func (s *PicturesREST) handlePicturePOST(ctx *gin.Context) {
	userCtx, err := s.auth.ValidateREST(ctx)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	if userCtx.UserID == 0 {
		ctx.Status(http.StatusForbidden)

		return
	}

	var values PicturePostForm
	if err := ctx.ShouldBindWith(&values, binding.FormMultipart); err != nil {
		ctx.JSON(http.StatusBadRequest, BadRequestResponse{
			InvalidParams: map[string]map[string]string{"comment": {
				"invalid": err.Error(),
			}},
		})

		return
	}

	if values.ItemID == 0 && values.ReplacePictureID == 0 {
		ctx.JSON(http.StatusBadRequest, BadRequestResponse{
			InvalidParams: map[string]map[string]string{"item_id": {
				"invalid": "item_id or replace_picture_id is required",
			}},
		})

		return
	}

	if values.File == nil {
		ctx.String(http.StatusInternalServerError, "file not provided")

		return
	}

	if values.File.Size > pictures.ImageMaxFileSize {
		ctx.JSON(http.StatusBadRequest, BadRequestResponse{
			InvalidParams: map[string]map[string]string{pictureFileField: {
				"fileFilesSizeTooBig": fmt.Sprintf(
					"All files in sum should have a maximum size of '%d' but '%d' were detected",
					pictures.ImageMaxFileSize, values.File.Size,
				),
			}},
		})

		return
	}

	handle, err := values.File.Open()
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
		sampler.ContentTypeImageJPEG,
		sampler.ContentTypeImagePNG,
	}

	mimeIsAllowed := false

	for _, allowedMime := range allowedMimes {
		if mime.Is(allowedMime) {
			mimeIsAllowed = true

			break
		}
	}

	if !mimeIsAllowed {
		ctx.JSON(http.StatusBadRequest, BadRequestResponse{
			InvalidParams: map[string]map[string]string{pictureFileField: {
				"fileIsImageFalseType": fmt.Sprintf(
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

	problems, err := values.Validate()
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	if len(problems) > 0 {
		ctx.JSON(http.StatusBadRequest, BadRequestResponse{
			InvalidParams: problems,
		})

		return
	}

	ctxWithoutCancel := context.WithoutCancel(ctx)

	pictureID, err := s.picturesRepository.AddPictureFromReader(
		ctxWithoutCancel,
		handle,
		userCtx.UserID,
		ctx.RemoteIP(),
		values.ItemID,
		values.PerspectiveID,
		values.ReplacePictureID,
	)
	if err != nil {
		if errors.Is(err, pictures.ErrInvalidImage) {
			ctx.JSON(http.StatusBadRequest, BadRequestResponse{
				InvalidParams: map[string]map[string]string{pictureFileField: {
					"invalidImage": err.Error(),
				}},
			})

			return
		}

		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	// increment uploads counter
	err = s.usersRepository.IncrementUploads(ctxWithoutCancel, userCtx.UserID)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	// add comment
	if len(values.Comment) > 0 {
		_, err = s.commentsRepository.Add(
			ctx,
			schema.CommentMessageTypeIDPictures,
			pictureID,
			0,
			userCtx.UserID,
			values.Comment,
			ctx.RemoteIP(),
			false,
		)
		if err != nil {
			ctx.String(http.StatusInternalServerError, err.Error())

			return
		}
	}

	err = s.commentsRepository.Subscribe(
		ctx,
		userCtx.UserID,
		schema.CommentMessageTypeIDPictures,
		pictureID,
	)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	err = s.telegramService.NotifyInbox(ctx, pictureID)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, pictureID)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())
	}

	ctx.JSON(http.StatusCreated, gin.H{
		"id": strconv.FormatInt(pictureID, 10),
	})
}

func (s *PicturesREST) detectLanguage(ctx *gin.Context) (string, error) {
	tags, _, err := language.ParseAcceptLanguage(ctx.Request.Header.Get("Accept-Language"))
	if err != nil {
		return "", err
	}

	lang := "en"

	if len(tags) > 0 {
		base, _ := tags[0].Base()
		lang = base.String()
	}

	return lang, nil
}

func (s *PicturesREST) handlePicture(ctx *gin.Context, orderBy pictures.OrderBy) {
	lang, err := s.detectLanguage(ctx)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	row, err := s.picturesRepository.Picture(ctx, &query.PictureListOptions{
		Status: schema.PictureStatusAccepted,
	}, &pictures.PictureFields{NameText: true}, orderBy)
	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	if err != nil {
		ctx.JSON(http.StatusOK, gin.H{
			"status": false,
		})

		return
	}

	s.populatePicture(ctx, row, lang)
}

func (s *PicturesREST) handleItemOfDayPicture(ctx *gin.Context) {
	lang, err := s.detectLanguage(ctx)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	itemOfDay, err := s.itemOfDay.Current(ctx)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	var row *schema.PictureRow

	if itemOfDay.ItemID > 0 {
		carRow, err := s.itemsRepository.Item(ctx, &query.ItemListOptions{
			ItemID: itemOfDay.ItemID,
		}, &items.ItemFields{NameText: true})
		if err != nil && !errors.Is(err, items.ErrItemNotFound) {
			ctx.String(http.StatusInternalServerError, err.Error())

			return
		}

		if err == nil {
			for _, groupID := range []int32{schema.PerspectiveGroupAPI, 0} {
				filter := query.PictureListOptions{
					Status: schema.PictureStatusAccepted,
					PictureItem: &query.PictureItemListOptions{
						ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
							ParentID: carRow.ID,
						},
					},
				}
				order := pictures.OrderByResolutionDesc

				if groupID > 0 {
					filter.PictureItem.PerspectiveGroupPerspective = &query.PerspectiveGroupPerspectiveListOptions{
						GroupID: groupID,
					}
					order = pictures.OrderByPerspectivesGroupPerspectives
				}

				row, err = s.picturesRepository.Picture(
					ctx,
					&filter,
					&pictures.PictureFields{NameText: true},
					order,
				)
				if err != nil && !errors.Is(err, sql.ErrNoRows) {
					ctx.String(http.StatusInternalServerError, err.Error())

					return
				}

				if err == nil {
					break
				}
			}
		}
	}

	s.populatePicture(ctx, row, lang)
}

func (s *PicturesREST) populatePicture(ctx *gin.Context, row *schema.PictureRow, lang string) {
	if row == nil {
		ctx.JSON(http.StatusOK, gin.H{
			"status": false,
		})

		return
	}

	if !row.ImageID.Valid {
		ctx.Status(http.StatusNotFound)

		return
	}

	imageInfo, err := s.imageStorage.Image(ctx, int(row.ImageID.Int64))
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	uri, err := s.hostManager.URIByLanguage(lang)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	namesData, err := s.picturesRepository.NameData(
		ctx,
		[]*schema.PictureRow{row},
		pictures.NameDataOptions{
			Language: lang,
		},
	)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	nameText, err := s.pictureNameFormatter.FormatText(namesData[row.ID], lang)
	if err != nil {
		ctx.String(http.StatusInternalServerError, err.Error())

		return
	}

	ctx.JSON(http.StatusOK, gin.H{
		"status": true,
		"url":    imageInfo.Src(),
		"name":   nameText,
		"page":   frontend.PictureURL(uri, row.Identity),
	})
}
