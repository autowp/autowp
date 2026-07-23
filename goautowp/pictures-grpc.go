package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"math"
	"strings"
	"sync"
	"time"

	"cloud.google.com/go/civil"
	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/image/sampler"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/telegram"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"github.com/paulmach/orb"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

const (
	newboxPicturesPerPage   = 60
	newboxPicturesPerLine   = 6
	newboxGroupTypeItem     = "item"
	newboxGroupTypePicture  = "picture"
	newboxGroupTypePictures = "pictures"

	galleryItemsPerPage = 10

	acceptReplaceMessageModeratorURL          = "ModeratorURL"
	messagePictureURL                         = "PictureURL"
	acceptReplaceMessageReplacementPictureURL = "ReplacementPictureURL"

	pictureModerVoteTemplateMessageField = "message"
)

type PicturesGRPCServer struct {
	UnimplementedPicturesServer

	repository            *pictures.Repository
	auth                  *Auth
	events                *Events
	hostManager           *hosts.Manager
	messagingRepository   *messaging.Repository
	userRepository        *users.Repository
	duplicateFinder       *DuplicateFinder
	textStorageRepository *textstorage.Repository
	telegramService       *telegram.Service
	itemRepository        *items.Repository
	commentRepository     *comments.Repository
	locations             map[string]*time.Location
	locationsMutex        sync.Mutex
	pictureExtractor      *PictureExtractor
	pictureItemExtractor  *PictureItemExtractor
	itemExtractor         *ItemExtractor
	catalogue             *Catalogue
	itemOfDayCached       *ItemOfDayCached
}

func NewPicturesGRPCServer(
	repository *pictures.Repository,
	auth *Auth,
	events *Events,
	hostManager *hosts.Manager,
	messagingRepository *messaging.Repository,
	userRepository *users.Repository,
	duplicateFinder *DuplicateFinder,
	textStorageRepository *textstorage.Repository,
	telegramService *telegram.Service,
	itemRepository *items.Repository,
	commentRepository *comments.Repository,
	pictureExtractor *PictureExtractor,
	pictureItemExtractor *PictureItemExtractor,
	itemExtractor *ItemExtractor,
	catalogue *Catalogue,
	itemOfDayCached *ItemOfDayCached,
) *PicturesGRPCServer {
	return &PicturesGRPCServer{
		repository:            repository,
		auth:                  auth,
		events:                events,
		hostManager:           hostManager,
		messagingRepository:   messagingRepository,
		userRepository:        userRepository,
		duplicateFinder:       duplicateFinder,
		textStorageRepository: textStorageRepository,
		telegramService:       telegramService,
		itemRepository:        itemRepository,
		commentRepository:     commentRepository,
		locations:             make(map[string]*time.Location),
		locationsMutex:        sync.Mutex{},
		pictureExtractor:      pictureExtractor,
		pictureItemExtractor:  pictureItemExtractor,
		itemExtractor:         itemExtractor,
		catalogue:             catalogue,
		itemOfDayCached:       itemOfDayCached,
	}
}

func (s *PicturesGRPCServer) View(
	ctx context.Context,
	in *PicturesViewRequest,
) (*emptypb.Empty, error) {
	err := s.repository.IncView(ctx, in.GetPictureId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) Vote(
	ctx context.Context,
	in *PicturesVoteRequest,
) (*PicturesVoteSummary, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	err = s.repository.Vote(ctx, in.GetPictureId(), in.GetValue(), userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	vote, err := s.repository.GetVote(ctx, in.GetPictureId(), userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &PicturesVoteSummary{
		Value:    vote.Value,
		Positive: vote.Positive,
		Negative: vote.Negative,
	}, nil
}

func (s *PicturesGRPCServer) ValidatePictureModerVoteTemplateRow(
	tpl *schema.PictureModerVoteTemplateRow,
) ([]*errdetails.BadRequest_FieldViolation, error) {
	result := make([]*errdetails.BadRequest_FieldViolation, 0)

	var (
		problems []string
		err      error
	)

	messageInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
			&validation.StringLength{Max: schema.ModerVoteTemplateMessageMaxLength},
		},
	}

	tpl.Message, problems, err = messageInputFilter.IsValidString(tpl.Message)
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       pictureModerVoteTemplateMessageField,
			Description: fv,
		})
	}

	return result, nil
}

func (s *PicturesGRPCServer) CreateModerVoteTemplate(
	ctx context.Context,
	in *ModerVoteTemplate,
) (*ModerVoteTemplate, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	tpl := schema.PictureModerVoteTemplateRow{
		UserID:  userCtx.UserID,
		Message: in.GetMessage(),
		Vote:    int8(in.GetVote()), //nolint: gosec
	}

	fvs, err := s.ValidatePictureModerVoteTemplateRow(&tpl)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(fvs) > 0 {
		return nil, wrapFieldViolations(fvs)
	}

	tpl, err = s.repository.CreateModerVoteTemplate(ctx, tpl)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return extractPictureModerVoteTemplate(&tpl), nil
}

func (s *PicturesGRPCServer) DeleteModerVoteTemplate(
	ctx context.Context,
	in *DeleteModerVoteTemplateRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	err = s.repository.DeleteModerVoteTemplate(ctx, in.GetId(), userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) GetModerVoteTemplates(
	ctx context.Context,
	_ *emptypb.Empty,
) (*ModerVoteTemplates, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	rows, err := s.repository.GetModerVoteTemplates(ctx, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*ModerVoteTemplate, len(rows))
	for idx, item := range rows {
		result[idx] = extractPictureModerVoteTemplate(&item)
	}

	return &ModerVoteTemplates{
		Items: result,
	}, nil
}

func (s *PicturesGRPCServer) DeleteModerVote(
	ctx context.Context,
	in *DeleteModerVoteRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pictureID := in.GetPictureId()

	ctx = context.WithoutCancel(ctx)

	success, err := s.repository.DeleteModerVote(ctx, pictureID, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		err = s.events.Add(ctx, Event{
			UserID:   userCtx.UserID,
			Message:  fmt.Sprintf("Отменена заявка на принятие/удаление картинки %d", pictureID),
			Pictures: []int64{pictureID},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) UpdateModerVote(
	ctx context.Context,
	in *UpdateModerVoteRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RolePicturesModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	InvalidParams, err := in.Validate()
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	pictureID := in.GetPictureId()
	reason := in.GetReason()

	var vote uint8
	if in.GetVote() > 0 {
		vote = 1
	}

	ctx = context.WithoutCancel(ctx)

	success, err := s.repository.CreateModerVote(ctx, pictureID, userCtx.UserID, vote, reason)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !success {
		return &emptypb.Empty{}, nil
	}

	currentStatus, err := s.repository.Status(ctx, pictureID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if vote > 0 && currentStatus == schema.PictureStatusRemoving {
		err = s.restoreFromRemoving(ctx, pictureID, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	if (vote == 0) && currentStatus == schema.PictureStatusAccepted {
		err = s.unaccept(ctx, pictureID, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	if in.GetSave() {
		exists, err := s.repository.IsModerVoteTemplateExists(ctx, userCtx.UserID, reason)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if !exists {
			tpl := schema.PictureModerVoteTemplateRow{
				UserID:  userCtx.UserID,
				Message: reason,
				Vote:    int8(in.GetVote()), //nolint: gosec
			}

			_, err = s.repository.CreateModerVoteTemplate(ctx, tpl)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		}
	}

	msgTemplate := "Подана заявка на удаление картинки %d"
	if vote > 0 {
		msgTemplate = "Подана заявка на принятие картинки %d"
	}

	err = s.events.Add(ctx, Event{
		UserID:   userCtx.UserID,
		Message:  fmt.Sprintf(msgTemplate, pictureID),
		Pictures: []int64{pictureID},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.notifyVote(ctx, pictureID, vote, reason, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, pictureID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *UpdateModerVoteRequest) Validate() ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	reasonInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
		},
	}

	s.Reason, problems, err = reasonInputFilter.IsValidString(s.GetReason())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "reason",
			Description: fv,
		})
	}

	voteInputFilter := validation.InputFilter{
		Validators: []validation.ValidatorInterface{
			&validation.InArray{
				HaystackInt32: []int32{-1, 1},
			},
		},
	}

	s.Vote, problems, err = voteInputFilter.IsValidInt32(s.GetVote())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "vote",
			Description: fv,
		})
	}

	return result, nil
}

func (s *PicturesGRPCServer) GetUserSummary(
	ctx context.Context,
	_ *emptypb.Empty,
) (*PicturesUserSummary, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	acceptedCount, err := s.repository.Count(ctx, &query.PictureListOptions{
		Status:  schema.PictureStatusAccepted,
		OwnerID: userCtx.UserID,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	inboxCount, err := s.repository.Count(ctx, &query.PictureListOptions{
		Status:  schema.PictureStatusInbox,
		OwnerID: userCtx.UserID,
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &PicturesUserSummary{
		AcceptedCount: int32(acceptedCount), //nolint: gosec
		InboxCount:    int32(inboxCount),    //nolint: gosec
	}, nil
}

func (s *PicturesGRPCServer) Normalize(
	ctx context.Context,
	in *PictureIDRequest,
) (*emptypb.Empty, error) {
	pictureID := in.GetId()

	userID, err := s.enforcePictureImageOperation(ctx, pictureID)
	if err != nil {
		return nil, err
	}

	ctx = context.WithoutCancel(ctx)

	err = s.repository.Normalize(ctx, pictureID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID:   userID,
		Message:  fmt.Sprintf("К картинке %d применён normalize", pictureID),
		Pictures: []int64{pictureID},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) Flop(
	ctx context.Context,
	in *PictureIDRequest,
) (*emptypb.Empty, error) {
	pictureID := in.GetId()

	userID, err := s.enforcePictureImageOperation(ctx, pictureID)
	if err != nil {
		return nil, err
	}

	ctx = context.WithoutCancel(ctx)

	err = s.repository.Flop(ctx, pictureID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID:   userID,
		Message:  fmt.Sprintf("К картинке %d применён flop", pictureID),
		Pictures: []int64{pictureID},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) DeleteSimilar(
	ctx context.Context,
	in *DeleteSimilarRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	if in.GetId() == 0 || in.GetSimilarPictureId() == 0 {
		return nil, status.Errorf(codes.InvalidArgument, "InvalidArgument")
	}

	ctx = context.WithoutCancel(ctx)

	if err = s.duplicateFinder.HideSimilar(ctx, in.GetId(), in.GetSimilarPictureId()); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID:   userCtx.UserID,
		Message:  "Отменёно предупреждение о повторе",
		Pictures: []int64{in.GetId(), in.GetSimilarPictureId()},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) Repair(
	ctx context.Context,
	in *PictureIDRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	err = s.repository.Repair(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) SetPictureItemArea(
	ctx context.Context, in *SetPictureItemAreaRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pictureItemType := convertPictureItemType(in.GetType())
	ctx = context.WithoutCancel(ctx)

	err = s.repository.SetPictureItemArea(
		ctx, in.GetPictureId(), in.GetItemId(), pictureItemType, pictures.PictureItemArea{
			Left:   uint16(in.GetCropLeft()),   //nolint: gosec
			Top:    uint16(in.GetCropTop()),    //nolint: gosec
			Width:  uint16(in.GetCropWidth()),  //nolint: gosec
			Height: uint16(in.GetCropHeight()), //nolint: gosec
		},
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID:   userCtx.UserID,
		Message:  "Выделение области на картинке",
		Pictures: []int64{in.GetPictureId()},
		Items:    []int64{in.GetItemId()},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) SetPictureItemPerspective(
	ctx context.Context, in *SetPictureItemPerspectiveRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		pictureID := in.GetPictureId()
		if pictureID == 0 {
			return nil, status.Error(codes.NotFound, "NotFound")
		}

		pic, err := s.repository.Picture(
			ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if !pic.OwnerID.Valid || pic.OwnerID.Int64 != userCtx.UserID ||
			pic.Status != schema.PictureStatusInbox {
			return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
		}
	}

	pictureItemType := convertPictureItemType(in.GetType())

	ctx = context.WithoutCancel(ctx)

	err = s.repository.SetPictureItemPerspective(
		ctx, in.GetPictureId(), in.GetItemId(), pictureItemType, in.GetPerspectiveId(),
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID:   userCtx.UserID,
		Message:  "Установка ракурса картинки",
		Pictures: []int64{in.GetPictureId()},
		Items:    []int64{in.GetItemId()},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) SetPictureItemItemID(
	ctx context.Context, in *SetPictureItemItemIDRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RolePicturesModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pictureItemType := convertPictureItemType(in.GetType())
	ctx = context.WithoutCancel(ctx)

	err = s.repository.SetPictureItemItemID(
		ctx, in.GetPictureId(), in.GetItemId(), pictureItemType, in.GetNewItemId(),
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID: userCtx.UserID,
		Message: fmt.Sprintf(
			"Картинка %d перемещена из %d в %d",
			in.GetPictureId(), in.GetItemId(), in.GetNewItemId(),
		),
		Pictures: []int64{in.GetPictureId()},
		Items:    []int64{in.GetItemId(), in.GetNewItemId()},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, in.GetNewItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) DeletePictureItem(
	ctx context.Context, in *DeletePictureItemRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RolePicturesModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pictureItemType := convertPictureItemType(in.GetType())
	ctx = context.WithoutCancel(ctx)

	success, err := s.repository.DeletePictureItem(
		ctx,
		in.GetPictureId(),
		in.GetItemId(),
		pictureItemType,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !success {
		return nil, status.Errorf(codes.NotFound, "NotFound")
	}

	err = s.events.Add(ctx, Event{
		UserID:   userCtx.UserID,
		Message:  fmt.Sprintf("Картинка %d отвязана от %d", in.GetPictureId(), in.GetItemId()),
		Pictures: []int64{in.GetPictureId()},
		Items:    []int64{in.GetItemId()},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) CreatePictureItem(
	ctx context.Context, in *CreatePictureItemRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RolePicturesModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pictureItemType := convertPictureItemType(in.GetType())
	ctx = context.WithoutCancel(ctx)

	success, err := s.repository.CreatePictureItem(
		ctx, in.GetPictureId(), in.GetItemId(), pictureItemType, in.GetPerspectiveId(),
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		err = s.events.Add(ctx, Event{
			UserID: userCtx.UserID,
			Message: fmt.Sprintf(
				"Картинка %d связана с %d",
				in.GetPictureId(), in.GetItemId(),
			),
			Pictures: []int64{in.GetPictureId()},
			Items:    []int64{in.GetItemId()},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	err = s.itemOfDayCached.FlushItemOfDayCache(ctx, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) SetPictureCrop(
	ctx context.Context,
	in *SetPictureCropRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RolePicturesModer) {
		pictureID := in.GetPictureId()
		if pictureID == 0 {
			return nil, status.Error(codes.NotFound, "NotFound")
		}

		pic, err := s.repository.Picture(
			ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
		)
		if err != nil {
			if errors.Is(err, sql.ErrNoRows) {
				return nil, status.Errorf(codes.NotFound, "NotFound")
			}

			return nil, status.Error(codes.Internal, err.Error())
		}

		if !pic.OwnerID.Valid || pic.OwnerID.Int64 != userCtx.UserID ||
			pic.Status != schema.PictureStatusInbox {
			return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
		}
	}

	ctx = context.WithoutCancel(ctx)

	err = s.repository.SetPictureCrop(
		ctx, in.GetPictureId(), sampler.Crop{
			Left:   int(in.GetCropLeft()),
			Top:    int(in.GetCropTop()),
			Width:  int(in.GetCropWidth()),
			Height: int(in.GetCropHeight()),
		},
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.events.Add(ctx, Event{
		UserID:   userCtx.UserID,
		Message:  "Выделение области на картинке",
		Pictures: []int64{in.GetPictureId()},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, in.GetPictureId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) ClearReplacePicture(
	ctx context.Context,
	in *PictureIDRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RolePicturesModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	ctx = context.WithoutCancel(ctx)

	success, err := s.repository.ClearReplacePicture(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		err = s.events.Add(ctx, Event{
			UserID:   userCtx.UserID,
			Message:  fmt.Sprintf("Замена для %d отклонена", in.GetId()),
			Pictures: []int64{in.GetId()},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) AcceptReplacePicture(
	ctx context.Context,
	in *PictureIDRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pictureID := in.GetId()
	if pictureID == 0 {
		return nil, status.Errorf(codes.NotFound, "NotFound")
	}

	pic, err := s.repository.Picture(
		ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !pic.ReplacePictureID.Valid {
		return nil, status.Errorf(codes.NotFound, "NotFound")
	}

	replacePicture, err := s.repository.Picture(
		ctx, &query.PictureListOptions{ID: pic.ReplacePictureID.Int64}, nil, pictures.OrderByNone,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !s.canReplace(pic, replacePicture, userCtx.Roles) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	ctx = context.WithoutCancel(ctx)

	// statuses
	if pic.Status != schema.PictureStatusAccepted {
		_, success, err := s.repository.Accept(ctx, pic.ID, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, "Accept error: "+err.Error())
		}

		if success && pic.OwnerID.Valid {
			err = s.userRepository.RefreshPicturesCount(ctx, pic.OwnerID.Int64)
			if err != nil {
				return nil, status.Error(codes.Internal, "RefreshPicturesCount error: "+err.Error())
			}
		}
	}

	if replacePicture.Status != schema.PictureStatusRemoving &&
		replacePicture.Status != schema.PictureStatusRemoved {
		success, err := s.repository.QueueRemove(ctx, replacePicture.ID, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, "QueueRemove error: "+err.Error())
		}

		if success && replacePicture.OwnerID.Valid {
			err = s.userRepository.RefreshPicturesCount(ctx, replacePicture.OwnerID.Int64)
			if err != nil {
				return nil, status.Error(codes.Internal, "RefreshPicturesCount error: "+err.Error())
			}
		}
	}

	// comments
	err = s.commentRepository.MoveMessages(ctx,
		schema.CommentMessageTypeIDPictures, replacePicture.ID,
		schema.CommentMessageTypeIDPictures, pic.ID,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	// pms
	recipients := make(map[int64]sql.NullInt64)

	if pic.OwnerID.Valid {
		recipients[pic.OwnerID.Int64] = pic.OwnerID
	}

	if replacePicture.OwnerID.Valid {
		recipients[replacePicture.OwnerID.Int64] = replacePicture.OwnerID
	}

	user, err := s.userRepository.User(
		ctx,
		&query.UserListOptions{ID: userCtx.UserID},
		users.UserFields{},
		users.OrderByNone,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	for _, recipient := range recipients {
		err = s.sendLocalizedMessage(
			ctx, userCtx.UserID, recipient, "pm/user-%s-accept-replace-%s-%s",
			func(lang string) (map[string]interface{}, error) {
				uri, err := s.hostManager.URIByLanguage(lang)
				if err != nil {
					return nil, err
				}

				return map[string]interface{}{
					acceptReplaceMessageModeratorURL:          frontend.UserURL(uri, userCtx.UserID, user.Identity),
					messagePictureURL:                         frontend.PictureURL(uri, replacePicture.Identity),
					acceptReplaceMessageReplacementPictureURL: frontend.PictureURL(uri, pic.Identity),
				}, nil
			})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	// log
	err = s.events.Add(ctx, Event{
		UserID:   userCtx.UserID,
		Message:  fmt.Sprintf("Замена %d на %d", replacePicture.ID, pic.ID),
		Pictures: []int64{replacePicture.ID, pic.ID},
	})
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, replacePicture.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, pic.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) SetPicturePoint(
	ctx context.Context,
	in *SetPicturePointRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	var (
		point    = in.GetPoint()
		orbPoint *orb.Point
	)

	if point.GetLatitude() != 0 || point.GetLongitude() != 0 {
		orbPoint = &orb.Point{point.GetLongitude(), point.GetLatitude()}
	}

	ctx = context.WithoutCancel(ctx)

	success, err := s.repository.SetPicturePoint(ctx, in.GetPictureId(), orbPoint)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		err = s.events.Add(ctx, Event{
			UserID:   userCtx.UserID,
			Message:  "Изменена точка для изображения",
			Pictures: []int64{in.GetPictureId()},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) UpdatePicture(
	ctx context.Context,
	in *UpdatePictureRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	inDate := in.GetTakenDate()

	ctx = context.WithoutCancel(ctx)

	success, err := s.repository.UpdatePicture(
		ctx, in.GetId(), in.GetName(),
		int16(inDate.GetYear()), int8(inDate.GetMonth()), int8(inDate.GetDay()), //nolint: gosec
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		err = s.events.Add(ctx, Event{
			UserID:   userCtx.UserID,
			Message:  "Редактирование изображения (дата, особое название)",
			Pictures: []int64{in.GetId()},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) SetPictureCopyrights(
	ctx context.Context, in *SetPictureCopyrightsRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pictureID := in.GetId()
	ctx = context.WithoutCancel(ctx)

	success, textID, err := s.repository.SetPictureCopyrights(
		ctx,
		pictureID,
		in.GetCopyrights(),
		userCtx.UserID,
	)
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, status.Errorf(codes.NotFound, "NotFound")
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	if success {
		err = s.events.Add(ctx, Event{
			UserID:   userCtx.UserID,
			Message:  "Редактирование текста копирайтов изображения",
			Pictures: []int64{in.GetId()},
		})
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		err = s.notifyCopyrightsEdited(ctx, pictureID, textID, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) SetPictureStatus(
	ctx context.Context, in *SetPictureStatusRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pictureID := in.GetId()
	if pictureID == 0 {
		return nil, status.Errorf(codes.NotFound, "NotFound")
	}

	pic, err := s.repository.Picture(
		ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	ctx = context.WithoutCancel(ctx)

	switch in.GetStatus() {
	case PictureStatus_PICTURE_STATUS_ACCEPTED:
		canAccept, err := s.canAccept(ctx, pic, userCtx.Roles)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if !canAccept {
			return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
		}

		isFirstTimeAccepted, success, err := s.repository.Accept(ctx, pic.ID, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, "Accept error: "+err.Error())
		}

		if success {
			err = s.events.Add(ctx, Event{
				UserID:   userCtx.UserID,
				Message:  fmt.Sprintf("Картинка `%d` принята", pic.ID),
				Pictures: []int64{pic.ID},
			})
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}

			if pic.OwnerID.Valid {
				err = s.userRepository.RefreshPicturesCount(ctx, pic.OwnerID.Int64)
				if err != nil {
					return nil, status.Error(
						codes.Internal,
						"RefreshPicturesCount error: "+err.Error(),
					)
				}
			}

			err = s.notifyAccepted(ctx, pic, userCtx.UserID, isFirstTimeAccepted)
			if err != nil {
				return nil, status.Error(codes.Internal, "notifyAccepted error: "+err.Error())
			}
		}
	case PictureStatus_PICTURE_STATUS_INBOX:
		switch pic.Status {
		case schema.PictureStatusRemoving:
			canRestore := util.Contains(userCtx.Roles, users.RoleAdmin)
			if !canRestore {
				return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
			}

			err = s.restoreFromRemoving(ctx, pic.ID, userCtx.UserID)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		case schema.PictureStatusAccepted:
			canUnaccept := util.Contains(userCtx.Roles, users.RolePicturesModer)
			if !canUnaccept {
				return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
			}

			err = s.unaccept(ctx, pic.ID, userCtx.UserID)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		case schema.PictureStatusUnknown, schema.PictureStatusRemoved, schema.PictureStatusInbox:
		}
	case PictureStatus_PICTURE_STATUS_REMOVING:
		canDelete, err := s.pictureCanDelete(ctx, pic, userCtx.Roles, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if !canDelete {
			return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
		}

		success, err := s.repository.QueueRemove(ctx, pic.ID, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if success {
			err = s.notifyRemoving(ctx, pic, userCtx.UserID)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}

			err = s.events.Add(ctx, Event{
				UserID:   userCtx.UserID,
				Message:  fmt.Sprintf("Картинка `%d` поставлена в очередь на удаление", pic.ID),
				Pictures: []int64{pic.ID},
			})
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		}

	case PictureStatus_PICTURE_STATUS_REMOVED:
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")

	case PictureStatus_PICTURE_STATUS_UNKNOWN:
		return nil, status.Errorf(codes.InvalidArgument, "InvalidArgument")
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, pic.ID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) NotifyInboxed(
	ctx context.Context,
	pic *schema.PictureRow,
	userID int64,
) error {
	if !pic.ChangeStatusUserID.Valid || pic.ChangeStatusUserID.Int64 == userID {
		return nil
	}

	return s.sendMessage(ctx, userID, pic.ChangeStatusUserID, func(lang string) (string, error) {
		pictureURL, err := s.pictureURL(pic.Identity, lang)
		if err != nil {
			return "", err
		}

		return fmt.Sprintf(
			`С картинки %s снят статус "принято"`,
			pictureURL,
		), nil
	})
}

func (s *PicturesGRPCServer) GetPictureItem(
	ctx context.Context,
	in *PictureItemsRequest,
) (*PictureItem, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)
	if !isModer {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	options, err := convertPictureItemListOptions(in.GetOptions())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	row, err := s.repository.PictureItem(ctx, options)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result, err := s.pictureItemExtractor.Extract(
		ctx,
		row,
		in.GetFields(),
		in.GetLanguage(),
		userCtx,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return result, nil
}

func (s *PicturesGRPCServer) GetPictureItems(
	ctx context.Context,
	in *PictureItemsRequest,
) (*PictureItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	inOptions := in.GetOptions()

	if inOptions.GetPictureId() == 0 && inOptions.GetItemId() == 0 {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	options, err := convertPictureItemListOptions(inOptions)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	order := convertPictureItemsOrder(in.GetOrder())

	rows, err := s.repository.PictureItems(ctx, options, order, 0)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res, err := s.pictureItemExtractor.ExtractRows(
		ctx,
		rows,
		in.GetFields(),
		in.GetLanguage(),
		userCtx,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &PictureItems{
		Items: res,
	}, nil
}

func (s *PicturesGRPCServer) GetPicture(
	ctx context.Context,
	in *PicturesRequest,
) (*Picture, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	err = s.isRestricted(in, isModer, userCtx.UserID)
	if err != nil {
		return nil, err
	}

	options, err := convertPictureListOptions(in.GetOptions())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if options == nil {
		options = &query.PictureListOptions{}
	}

	restrictPictureListOptionsToModer(options, isModer)

	options.Limit = in.GetLimit()
	options.Page = in.GetPage()

	fields := convertPictureFields(in.GetFields())

	row, err := s.repository.Picture(ctx, options, fields, convertPicturesOrder(in.GetOrder()))
	if err != nil {
		if errors.Is(err, sql.ErrNoRows) {
			return nil, status.Error(codes.NotFound, err.Error())
		}

		return nil, status.Error(codes.Internal, err.Error())
	}

	return s.pictureExtractor.Extract(ctx, row, in.GetFields(), in.GetLanguage(), userCtx)
}

func (s *PicturesGRPCServer) LoadLocation(timezone string) (*time.Location, error) {
	s.locationsMutex.Lock()
	defer s.locationsMutex.Unlock()

	var err error

	loc, ok := s.locations[timezone]

	if !ok {
		loc, err = time.LoadLocation(timezone)
		if err != nil {
			return nil, err
		}

		s.locations[timezone] = loc
	}

	return loc, nil
}

func (s *PicturesGRPCServer) GetPictures(
	ctx context.Context,
	in *PicturesRequest,
) (*PicturesList, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	inOptions := in.GetOptions()
	order := convertPicturesOrder(in.GetOrder())

	isModer := util.Contains(userCtx.Roles, users.RoleModer)
	// && options.ExactItemID == 0 && options.Status == "" && !options.identity
	err = s.isRestricted(in, isModer, userCtx.UserID)
	if err != nil {
		return nil, err
	}

	options, err := convertPictureListOptions(inOptions)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if options == nil {
		options = &query.PictureListOptions{}
	}

	restrictPictureListOptionsToModer(options, isModer)

	options.Limit = in.GetLimit()
	options.Page = in.GetPage()

	if options.CreatedAt != nil || options.AcceptDate != nil || options.AddedFrom != nil {
		options.Timezone, err = s.resolveTimezone(ctx, userCtx.UserID, in.GetLanguage())
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	fields := convertPictureFields(in.GetFields())

	rows, pages, err := s.repository.Pictures(ctx, options, fields, order, in.GetPaginator())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	res, err := s.pictureExtractor.ExtractRows(ctx, rows, in.GetFields(), in.GetLanguage(), userCtx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	var paginator *Pages
	if pages != nil {
		paginator = &Pages{
			PageCount:        pages.PageCount,
			First:            pages.First,
			Last:             pages.Last,
			Current:          pages.Current,
			FirstPageInRange: pages.FirstPageInRange,
			LastPageInRange:  pages.LastPageInRange,
			PagesInRange:     pages.PagesInRange,
			TotalItemCount:   pages.TotalItemCount,
			Next:             pages.Next,
			Previous:         pages.Previous,
		}
	}

	return &PicturesList{
		Items:     res,
		Paginator: paginator,
	}, nil
}

func (s *PicturesGRPCServer) GetPicturesPaginator(
	ctx context.Context,
	in *PicturesRequest,
) (*Pages, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	inOptions := in.GetOptions()
	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	err = s.isRestricted(in, isModer, userCtx.UserID)
	if err != nil {
		return nil, err
	}

	options, err := convertPictureListOptions(inOptions)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if options == nil {
		options = &query.PictureListOptions{}
	}

	restrictPictureListOptionsToModer(options, isModer)

	options.Limit = in.GetLimit()
	options.Page = in.GetPage()

	if options.CreatedAt != nil || options.AcceptDate != nil || options.AddedFrom != nil {
		options.Timezone, err = s.resolveTimezone(ctx, userCtx.UserID, in.GetLanguage())
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	paginator, err := s.repository.PicturesPaginator(options, nil, pictures.OrderByNone)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &Pages{
		PageCount:        pages.PageCount,
		First:            pages.First,
		Last:             pages.Last,
		Current:          pages.Current,
		FirstPageInRange: pages.FirstPageInRange,
		LastPageInRange:  pages.LastPageInRange,
		PagesInRange:     pages.PagesInRange,
		TotalItemCount:   pages.TotalItemCount,
		Next:             pages.Next,
		Previous:         pages.Previous,
	}, nil
}

func (s *PicturesGRPCServer) GetInbox(ctx context.Context, in *InboxRequest) (*Inbox, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	listOptions := query.PictureListOptions{
		Status: schema.PictureStatusInbox,
	}

	if in.GetBrandId() > 0 {
		listOptions.PictureItem = &query.PictureItemListOptions{
			ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
				ParentID: in.GetBrandId(),
			},
		}
	}

	timezone, err := s.resolveTimezone(ctx, userCtx.UserID, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	inCurrentDate := util.GrpcDateToDate(in.GetDate())
	if inCurrentDate == nil {
		inCurrentDate = &civil.Date{}
	}

	service, err := NewDayPictures(
		s.repository, schema.PictureTableCreatedAtColName, timezone, &listOptions, *inCurrentDate,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = service.SetCurrentDateToLastIfEmptyDate(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	prevDate, err := service.PrevDate(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	currentDate := service.CurrentDate()

	nextDate, err := service.NextDate(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	prevCount, err := service.PrevDateCount(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	currentCount, err := service.CurrentDateCount(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	nextCount, err := service.NextDateCount(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	brands, err := s.inboxBrands(ctx, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &Inbox{
		Brands:       brands,
		PrevDate:     util.DateToGrpcDate(prevDate),
		PrevCount:    prevCount,
		CurrentDate:  util.DateToGrpcDate(currentDate),
		CurrentCount: currentCount,
		NextDate:     util.DateToGrpcDate(nextDate),
		NextCount:    nextCount,
	}, nil
}

func (s *PicturesGRPCServer) GetNewbox(ctx context.Context, in *NewboxRequest) (*Newbox, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	listOptions := query.PictureListOptions{
		Status: schema.PictureStatusAccepted,
	}

	timezone, err := s.resolveTimezone(ctx, userCtx.UserID, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	inCurrentDate := util.GrpcDateToDate(in.GetDate())
	if inCurrentDate == nil {
		inCurrentDate = &civil.Date{}
	}

	service, err := NewDayPictures(
		s.repository,
		schema.PictureTableAcceptDatetimeColName,
		timezone,
		&listOptions,
		*inCurrentDate,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = service.SetCurrentDateToLastIfEmptyDate(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	prevDate, err := service.PrevDate(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	currentDate := service.CurrentDate()

	nextDate, err := service.NextDate(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	prevCount, err := service.PrevDateCount(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	currentCount, err := service.CurrentDateCount(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	nextCount, err := service.NextDateCount(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	groups, pages, err := s.newboxGroups(
		ctx,
		service.CurrentDate(),
		in.GetPage(),
		timezone,
		in.GetLanguage(),
		userCtx,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &Newbox{
		Groups:       groups,
		PrevDate:     util.DateToGrpcDate(prevDate),
		PrevCount:    prevCount,
		CurrentDate:  util.DateToGrpcDate(currentDate),
		CurrentCount: currentCount,
		NextDate:     util.DateToGrpcDate(nextDate),
		NextCount:    nextCount,
		Paginator: &Pages{
			PageCount:        pages.PageCount,
			First:            pages.First,
			Last:             pages.Last,
			Current:          pages.Current,
			FirstPageInRange: pages.FirstPageInRange,
			LastPageInRange:  pages.LastPageInRange,
			PagesInRange:     pages.PagesInRange,
			TotalItemCount:   pages.TotalItemCount,
			Next:             pages.Next,
			Previous:         pages.Previous,
		},
	}, nil
}

type NewboxGroupDraft struct {
	Type     string
	Picture  *schema.PictureRow
	ItemID   int64
	Pictures []*schema.PictureRow
}

func (s *PicturesGRPCServer) GetCanonicalRoute(
	ctx context.Context, in *CanonicalRouteRequest,
) (*CanonicalRoute, error) {
	identity := in.GetIdentity()
	if identity == "" {
		return nil, status.Errorf(codes.InvalidArgument, "InvalidArgument")
	}

	picture, err := s.repository.Picture(ctx, &query.PictureListOptions{
		Identity: identity,
	}, nil, pictures.OrderByNone)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	var route []string

	pictureItems, err := s.repository.PictureItems(ctx, &query.PictureItemListOptions{
		PictureID: picture.ID,
		TypeID:    schema.PictureItemTypeContent,
		Item: &query.ItemListOptions{
			TypeID: []schema.ItemTableItemTypeID{
				schema.ItemTableItemTypeIDBrand,
				schema.ItemTableItemTypeIDVehicle,
				schema.ItemTableItemTypeIDEngine,
				schema.ItemTableItemTypeIDPerson,
			},
		},
	}, pictures.OrderByNone, 0)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(pictureItems) > 0 {
		pictureItem := pictureItems[0]

		paths, err := s.itemRepository.CataloguePaths(
			ctx,
			pictureItem.ItemID,
			items.CataloguePathOptions{
				BreakOnFirst: true,
				StockFirst:   true,
			},
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if len(paths) > 0 {
			path := paths[0]

			switch path.Type {
			case items.CataloguePathResultTypeBrand:
				if len(path.CarCatname) > 0 {
					route = frontend.BrandItemPathPicturesPictureRoute(
						path.BrandCatname,
						path.CarCatname,
						path.Path,
						picture.Identity,
					)
				} else {
					action := frontend.BrandOther

					if pictureItem.PerspectiveID.Valid {
						switch pictureItem.PerspectiveID.Int32 {
						case schema.PerspectiveLogo:
							action = frontend.BrandLogotypes
						case schema.PerspectiveMixed:
							action = frontend.BrandMixed
						}
					}

					route = frontend.BrandGroupPictureRoute(path.BrandCatname, action, picture.Identity)
				}
			case items.CataloguePathResultTypeBrandItem:
				route = frontend.BrandItemPathPicturesPictureRoute(
					path.BrandCatname,
					path.CarCatname,
					path.Path,
					picture.Identity,
				)
			case items.CataloguePathResultTypeCategory:
				route = frontend.CategoryPictureRoute(path.CategoryCatname, picture.Identity)
			case items.CataloguePathResultTypePerson:
				route = frontend.PersonPictureRoute(path.ID, picture.Identity)
			}
		}
	}

	return &CanonicalRoute{
		Route: route,
	}, nil
}

func (s *PicturesGRPCServer) CorrectFileNames(
	ctx context.Context,
	in *PictureIDRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	id := in.GetId()
	if id == 0 {
		return nil, status.Errorf(codes.InvalidArgument, "InvalidArgument")
	}

	err = s.repository.CorrectFileNames(ctx, id)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.itemOfDayCached.FlushItemOfDayCacheByPictureID(ctx, id)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *PicturesGRPCServer) GetGallery(
	ctx context.Context,
	in *GalleryRequest,
) (*GalleryResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)
	pictureIdentity := in.GetPictureIdentity()
	request := in.GetRequest()

	if len(pictureIdentity) == 0 {
		err = s.isRestricted(request, isModer, userCtx.UserID)
		if err != nil {
			return nil, err
		}
	}

	options := request.GetOptions()

	repoOptions, err := convertPictureListOptions(options)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if repoOptions == nil {
		repoOptions = &query.PictureListOptions{}
	}

	restrictPictureListOptionsToModer(repoOptions, isModer)

	repoOptions.Limit = galleryItemsPerPage
	repoOptions.Page = request.GetPage()
	order := convertPicturesOrder(request.GetOrder())

	itemSpecified := options.GetPictureItem().GetItemParentCacheAncestor().GetParentId() != 0 ||
		options.GetPictureItem().GetItemId() != 0

	if len(pictureIdentity) > 0 {
		if !itemSpecified {
			repoOptions.Identity = pictureIdentity
		}

		// look for page of that picture
		filterCopy := *repoOptions
		filterCopy.Status = schema.PictureStatusUnknown
		filterCopy.Identity = pictureIdentity

		row, err := s.repository.Picture(ctx, &filterCopy, nil, pictures.OrderByNone)
		if err != nil {
			if errors.Is(err, sql.ErrNoRows) {
				return nil, status.Errorf(codes.NotFound, "NotFound")
			}

			return nil, status.Error(codes.Internal, err.Error())
		}

		repoOptions.Status = row.Status
		repoOptions.Page = 0

		if itemSpecified {
			page, err := s.getPicturePage(ctx, repoOptions, pictureIdentity, order)
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}

			repoOptions.Page = uint32(page) //nolint: gosec
		}
	}

	lang := request.GetLanguage()
	fields := request.GetFields()

	repoFields := convertPictureFields(fields)

	rows, pages, err := s.repository.Pictures(ctx, repoOptions, repoFields, order, true)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	gallery, err := s.pictureExtractor.ExtractRows(ctx, rows, fields, lang, userCtx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &GalleryResponse{
		Page:   pages.Current,
		Pages:  pages.PageCount,
		Count:  pages.TotalItemCount,
		Items:  gallery,
		Status: extractPicturesStatus(repoOptions.Status),
	}, nil
}

func (s *PicturesGRPCServer) GetPerspectives(
	ctx context.Context,
	_ *emptypb.Empty,
) (*PerspectivesItems, error) {
	res, err := s.catalogue.getPerspectives(ctx, nil)
	if err != nil {
		return nil, err
	}

	return &PerspectivesItems{ //nolint:exhaustruct
		Items: res,
	}, nil
}

func (s *PicturesGRPCServer) GetPerspectivePages(
	ctx context.Context,
	_ *emptypb.Empty,
) (*PerspectivePagesItems, error) {
	res, err := s.catalogue.getPerspectivePages(ctx)
	if err != nil {
		return nil, err
	}

	return &PerspectivePagesItems{ //nolint:exhaustruct
		Items: res,
	}, nil
}

func (s *PicturesGRPCServer) restoreFromRemoving(
	ctx context.Context,
	pictureID int64,
	userID int64,
) error {
	if pictureID == 0 {
		return sql.ErrNoRows
	}

	pic, err := s.repository.Picture(
		ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
	)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	err = s.repository.SetStatus(ctx, pic.ID, schema.PictureStatusInbox, userID)
	if err != nil {
		return err
	}

	err = s.events.Add(ctx, Event{
		UserID:   userID,
		Message:  fmt.Sprintf("Картинки `%d` восстановлена из очереди удаления", pic.ID),
		Pictures: []int64{pic.ID},
	})
	if err != nil {
		return err
	}

	if pic.OwnerID.Valid {
		err = s.userRepository.RefreshPicturesCount(ctx, pic.OwnerID.Int64)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *PicturesGRPCServer) unaccept(ctx context.Context, pictureID int64, userID int64) error {
	if pictureID == 0 {
		return sql.ErrNoRows
	}

	picture, err := s.repository.Picture(
		ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
	)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	err = s.repository.SetStatus(ctx, pictureID, schema.PictureStatusInbox, userID)
	if err != nil {
		return err
	}

	err = s.events.Add(ctx, Event{
		UserID:   userID,
		Message:  fmt.Sprintf(`С картинки %d снят статус "принято"`, pictureID),
		Pictures: []int64{pictureID},
	})
	if err != nil {
		return err
	}

	if picture.OwnerID.Valid {
		err = s.userRepository.RefreshPicturesCount(ctx, picture.OwnerID.Int64)
		if err != nil {
			return err
		}
	}

	return s.NotifyInboxed(ctx, picture, userID)
}

func (s *PicturesGRPCServer) notifyVote(
	ctx context.Context, pictureID int64, vote uint8, reason string, userID int64,
) error {
	if pictureID == 0 {
		return sql.ErrNoRows
	}

	picture, err := s.repository.Picture(
		ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
	)
	if err != nil {
		return err
	}

	if !picture.OwnerID.Valid || picture.OwnerID.Int64 == userID {
		return nil
	}

	tpl := "pm/new-picture-%s-vote-%s/delete"
	if vote > 0 {
		tpl = "pm/new-picture-%s-vote-%s/accept"
	}

	return s.sendLocalizedMessage(
		ctx, userID, picture.OwnerID, tpl,
		func(lang string) (map[string]interface{}, error) {
			uri, err := s.hostManager.URIByLanguage(lang)
			if err != nil {
				return nil, err
			}

			return map[string]interface{}{
				"Picture": frontend.PictureURL(uri, picture.Identity),
				"Reason":  reason,
			}, nil
		})
}

func (s *PicturesGRPCServer) enforcePictureImageOperation(
	ctx context.Context,
	pictureID int64,
) (int64, error) {
	if pictureID == 0 {
		return 0, status.Error(codes.NotFound, "NotFound")
	}

	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return 0, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return 0, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return 0, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	pic, err := s.repository.Picture(
		ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
	)
	if err != nil {
		return 0, status.Error(codes.Internal, err.Error())
	}

	if pic == nil {
		return 0, status.Errorf(codes.NotFound, "NotFound")
	}

	canNormalize := pic.Status == schema.PictureStatusInbox &&
		util.Contains(userCtx.Roles, users.RolePicturesModer)
	if !canNormalize {
		return 0, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	return userCtx.UserID, nil
}

func (s *PicturesGRPCServer) notifyCopyrightsEdited(
	ctx context.Context, pictureID int64, textID int32, userID int64,
) error {
	revUserIDs, err := s.textStorageRepository.TextUserIDs(ctx, textID)
	if err != nil {
		return err
	}

	revUserIDs = util.RemoveValueFromArray(revUserIDs, userID)
	if len(revUserIDs) == 0 {
		return nil
	}

	userRows, _, err := s.userRepository.Users(
		ctx, &query.UserListOptions{IDs: revUserIDs}, users.UserFields{}, users.OrderByNone,
	)
	if err != nil {
		return err
	}

	if pictureID == 0 {
		return nil
	}

	picture, err := s.repository.Picture(
		ctx, &query.PictureListOptions{ID: pictureID}, nil, pictures.OrderByNone,
	)
	if err != nil {
		return err
	}

	editorRow, err := s.userRepository.User(
		ctx, &query.UserListOptions{ID: userID}, users.UserFields{}, users.OrderByNone,
	)
	if err != nil {
		return err
	}

	for _, userRow := range userRows {
		pictureURL, err := s.pictureURL(picture.Identity, userRow.Language)
		if err != nil {
			return err
		}

		editorURL, err := s.userURL(editorRow.ID, editorRow.Identity, userRow.Language)
		if err != nil {
			return err
		}

		err = s.messagingRepository.CreateMessageFromTemplate(
			ctx, 0, userRow.ID, "pm/user-%s-edited-picture-copyrights-%s-%s",
			map[string]interface{}{
				"User":            editorURL,
				messagePictureURL: pictureURL,
			},
			userRow.Language,
		)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *PicturesGRPCServer) userURL(userID int64, identity *string, lang string) (string, error) {
	userURL, err := s.hostManager.URIByLanguage(lang)
	if err != nil {
		return "", err
	}

	userURL.Path = frontend.UserPath(userID, identity)

	return userURL.String(), nil
}

func (s *PicturesGRPCServer) pictureURL(identity string, lang string) (string, error) {
	pictureURL, err := s.hostManager.URIByLanguage(lang)
	if err != nil {
		return "", err
	}

	return frontend.PictureURL(pictureURL, identity), nil
}

func (s *PicturesGRPCServer) sendMessage(
	ctx context.Context,
	userID int64,
	receiverID sql.NullInt64,
	messageFunc func(lang string) (string, error),
) error {
	if !receiverID.Valid || (receiverID.Int64 == userID) {
		return nil
	}

	notDeleted := false

	receiver, err := s.userRepository.User(
		ctx,
		&query.UserListOptions{ID: receiverID.Int64, Deleted: &notDeleted},
		users.UserFields{},
		users.OrderByNone,
	)
	if err != nil && !errors.Is(err, users.ErrUserNotFound) {
		return err
	}

	if receiver == nil {
		return nil
	}

	message, err := messageFunc(receiver.Language)
	if err != nil {
		return err
	}

	return s.messagingRepository.CreateMessage(ctx, 0, receiver.ID, message)
}

func (s *PicturesGRPCServer) sendLocalizedMessage(
	ctx context.Context, userID int64, receiverID sql.NullInt64, messageID string,
	templateDataFunc func(lang string) (map[string]interface{}, error),
) error {
	if !receiverID.Valid || (receiverID.Int64 == userID) {
		return nil
	}

	notDeleted := false

	receiver, err := s.userRepository.User(
		ctx,
		&query.UserListOptions{ID: receiverID.Int64, Deleted: &notDeleted},
		users.UserFields{},
		users.OrderByNone,
	)
	if err != nil && !errors.Is(err, users.ErrUserNotFound) {
		return err
	}

	if receiver == nil {
		return nil
	}

	templateData, err := templateDataFunc(receiver.Language)
	if err != nil {
		return err
	}

	return s.messagingRepository.CreateMessageFromTemplate(
		ctx,
		0,
		receiver.ID,
		messageID,
		templateData,
		receiver.Language,
	)
}

func (s *PicturesGRPCServer) notifyAccepted(
	ctx context.Context, pic *schema.PictureRow, userID int64, isFirstTimeAccepted bool,
) error {
	ctx = context.WithoutCancel(ctx)

	if isFirstTimeAccepted {
		err := s.sendLocalizedMessage(
			ctx, userID, pic.OwnerID, "pm/your-picture-accepted-%s",
			func(lang string) (map[string]interface{}, error) {
				pictureURL, err := s.pictureURL(pic.Identity, lang)
				if err != nil {
					return nil, err
				}

				return map[string]interface{}{
					messagePictureURL: pictureURL,
				}, nil
			})
		if err != nil {
			return fmt.Errorf("sendLocalizedMessage: %w", err)
		}

		err = s.telegramService.NotifyPicture(ctx, pic, s.itemRepository)
		if err != nil {
			return fmt.Errorf("NotifyPicture: %w", err)
		}
	}

	err := s.sendMessage(
		ctx, userID, pic.ChangeStatusUserID, func(lang string) (string, error) {
			pictureURL, err := s.pictureURL(pic.Identity, lang)
			if err != nil {
				return "", err
			}

			return "Принята картинка " + pictureURL, nil
		})
	if err != nil {
		return fmt.Errorf("sendMessage: %w", err)
	}

	return nil
}

func (s *PicturesGRPCServer) notifyRemoving(
	ctx context.Context,
	pic *schema.PictureRow,
	userID int64,
) error {
	ctx = context.WithoutCancel(ctx)

	return s.sendLocalizedMessage(
		ctx, userID, pic.OwnerID, "pm/your-picture-%s-enqueued-to-remove-%s",
		func(lang string) (map[string]interface{}, error) {
			deleteRequests, err := s.repository.NegativeVotes(ctx, pic.ID)
			if err != nil {
				return nil, err
			}

			reasons := make([]string, 0, len(deleteRequests))

			for _, request := range deleteRequests {
				user, err := s.userRepository.User(
					ctx,
					&query.UserListOptions{ID: request.UserID},
					users.UserFields{},
					users.OrderByNone,
				)
				if err != nil {
					return nil, err
				}

				userURL, err := s.userURL(user.ID, user.Identity, user.Language)
				if err != nil {
					return nil, err
				}

				reasons = append(reasons, userURL+" : "+request.Reason)
			}

			pictureURL, err := s.pictureURL(pic.Identity, lang)
			if err != nil {
				return nil, err
			}

			return map[string]interface{}{
				messagePictureURL: pictureURL,
				"Reasons":         strings.Join(reasons, "\n"),
			}, nil
		})
}

func (s *PicturesGRPCServer) canAccept(
	ctx context.Context,
	picture *schema.PictureRow,
	roles []string,
) (bool, error) {
	if !util.Contains(roles, users.RolePicturesModer) {
		return false, nil
	}

	return s.repository.CanAccept(ctx, picture)
}

func (s *PicturesGRPCServer) pictureCanDelete(
	ctx context.Context, picture *schema.PictureRow, roles []string, userID int64,
) (bool, error) {
	canDelete, err := s.repository.CanDelete(ctx, picture)
	if err != nil {
		return false, err
	}

	if !canDelete {
		return false, nil
	}

	if util.Contains(roles, users.RolePicturesModer) {
		hasVote, err := s.repository.HasVote(ctx, picture.ID, userID)
		if err != nil {
			return false, err
		}

		if hasVote {
			acceptVotes, err := s.repository.PositiveVotesCount(ctx, picture.ID)
			if err != nil {
				return false, err
			}

			deleteVotes, err := s.repository.NegativeVotesCount(ctx, picture.ID)
			if err != nil {
				return false, err
			}

			return deleteVotes > acceptVotes, nil
		}
	}

	return false, nil
}

func (s *PicturesGRPCServer) canReplace(
	picture, replacedPicture *schema.PictureRow,
	roles []string,
) bool {
	return (picture.Status == schema.PictureStatusAccepted ||
		picture.Status == schema.PictureStatusInbox) &&
		(replacedPicture.Status == schema.PictureStatusRemoving ||
			replacedPicture.Status == schema.PictureStatusInbox ||
			replacedPicture.Status == schema.PictureStatusAccepted) &&
		util.Contains(roles, users.RolePicturesModer)
}

func (s *PicturesGRPCServer) resolveTimezone(
	ctx context.Context,
	userID int64,
	lang string,
) (*time.Location, error) {
	var (
		err      error
		timezone = ""
	)

	if userID > 0 {
		user, err := s.userRepository.User(
			ctx,
			&query.UserListOptions{ID: userID},
			users.UserFields{Timezone: true},
			users.OrderByNone,
		)
		if err != nil {
			return nil, err
		}

		timezone = user.Timezone
	}

	if timezone == "" {
		timezone, err = s.hostManager.TimezoneByLanguage(lang)
		if err != nil {
			return nil, err
		}
	}

	loc, err := s.LoadLocation(timezone)
	if err != nil {
		return nil, err
	}

	return loc, nil
}

func (s *PicturesGRPCServer) isRestricted(in *PicturesRequest, isModer bool, userID int64) error {
	const acceptedInDaysMax = 3

	inOptions := in.GetOptions()
	fields := in.GetFields()

	if inOptions.GetStatus() == PictureStatus_PICTURE_STATUS_INBOX && userID == 0 {
		return status.Error(codes.PermissionDenied, "inbox not allowed anonymously")
	}

	restricted := !isModer && inOptions.GetPictureItem().GetItemId() == 0 &&
		inOptions.GetPictureItem().GetItemParentCacheAncestor().GetItemId() == 0 &&
		inOptions.GetPictureItem().GetItemParentCacheAncestor().GetParentId() == 0 &&
		inOptions.GetPictureItem().GetPerspectiveId() == 0 &&
		inOptions.GetOwnerId() == 0 && inOptions.GetAcceptedInDays() < acceptedInDaysMax &&
		inOptions.GetCreatedAt() == nil && inOptions.GetId() == 0 && inOptions.GetIdentity() == ""
	if restricted {
		return status.Error(
			codes.PermissionDenied,
			"PictureItem.ItemParentCacheAncestor.ItemID or OwnerID is required",
		)
	}

	restricted = !isModer && (inOptions.GetHasNoComments() || inOptions.GetCommentTopic() != nil ||
		inOptions.GetPictureItem().GetItemVehicleType() != nil || inOptions.GetHasSpecialName() ||
		inOptions.GetDfDistance() != nil || inOptions.GetPictureModerVote() != nil ||
		inOptions.GetHasNoPictureModerVote() || inOptions.GetHasNoReplacePicture() ||
		inOptions.GetReplacePicture() != nil || inOptions.GetHasNoPictureItem() || inOptions.GetHasNoPoint() ||
		inOptions.GetAddedFrom() != nil || inOptions.GetPictureItem().GetExcludeAncestorOrSelfId() != 0)
	if restricted {
		return status.Error(codes.PermissionDenied, "PermissionDenied")
	}

	restricted = !isModer && (fields.GetAcceptedCount() || fields.GetExif() || fields.GetIsLast() ||
		fields.GetSpecialName() || fields.GetSiblings() != nil)
	if restricted {
		return status.Error(codes.PermissionDenied, "PermissionDenied")
	}

	return nil
}

// restrictPictureListOptionsToModer silently drops filters that are only allowed for moderators,
// instead of rejecting the request, so that non-moder callers just get the unfiltered result.
func restrictPictureListOptionsToModer(options *query.PictureListOptions, isModer bool) {
	if isModer {
		return
	}

	options.HasCopyrights = false
	options.HasNoCopyrights = false
}

func (s *PicturesGRPCServer) inboxBrands(ctx context.Context, lang string) ([]*InboxBrand, error) {
	rows, _, err := s.itemRepository.List(ctx, &query.ItemListOptions{
		Language:   lang,
		SortByName: true,
		TypeID:     []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{
			PictureItemsByItemID: &query.PictureItemListOptions{
				Pictures: &query.PictureListOptions{
					Status: schema.PictureStatusInbox,
				},
			},
		},
	}, &items.ItemFields{NameOnly: true}, items.OrderByName, false)
	if err != nil {
		return nil, err
	}

	res := make([]*InboxBrand, 0, len(rows))
	for _, row := range rows {
		res = append(res, &InboxBrand{
			Id:   row.ID,
			Name: row.NameOnly,
		})
	}

	return res, nil
}

func (s *PicturesGRPCServer) newboxGroups(
	ctx context.Context,
	acceptDate civil.Date,
	page uint32,
	timezone *time.Location,
	lang string,
	userCtx UserContext,
) ([]*NewboxGroup, *util.Pages, error) {
	pictureFields := PictureFields{
		ThumbMedium:   true,
		NameText:      true,
		NameHtml:      true,
		Votes:         true,
		Views:         true,
		CommentsCount: true,
	}
	repoPictureFields := convertPictureFields(&pictureFields)

	itemPictureFields := PictureFields{
		ThumbMedium: true,
		NameText:    true,
		NameHtml:    true,
	}
	repoItemPictureFields := convertPictureFields(&itemPictureFields)

	itemFields := ItemFields{
		NameHtml:    true,
		NameDefault: true,
		Description: true,
		Design:      true,
		SpecsRoute:  true,
		Categories: &ItemsRequest{
			Fields: &ItemFields{NameHtml: true},
		},
		Twins: &ItemsRequest{},
	}
	repoItemFields := convertItemFields(&itemFields)

	rows, pages, err := s.repository.Pictures(ctx, &query.PictureListOptions{
		Status:     schema.PictureStatusAccepted,
		Limit:      newboxPicturesPerPage,
		Page:       page,
		AcceptDate: &acceptDate,
		Timezone:   timezone,
	}, repoPictureFields, pictures.OrderByAcceptDatetimeDesc, true)
	if err != nil {
		return nil, nil, fmt.Errorf("repository.Pictures(): %w", err)
	}

	groupsData, err := s.splitPictures(ctx, rows)
	if err != nil {
		return nil, nil, err
	}

	groups := make([]*NewboxGroup, 0)

	for _, groupData := range groupsData {
		group := &NewboxGroup{
			Type: groupData.Type,
		}

		if groupData.Type == newboxGroupTypeItem {
			itemRow, err := s.itemRepository.Item(
				ctx,
				&query.ItemListOptions{ItemID: groupData.ItemID},
				repoItemFields,
			)
			if err != nil {
				return nil, nil, err
			}

			group.Item, err = s.itemExtractor.Extract(ctx, itemRow, &itemFields, lang, userCtx)
			if err != nil {
				return nil, nil, err
			}

			ids := make([]int64, 0)
			for _, picture := range groupData.Pictures {
				ids = append(ids, picture.ID)
			}

			pictureRows, _, err := s.repository.Pictures(ctx, &query.PictureListOptions{
				IDs:    ids,
				Status: schema.PictureStatusAccepted,
				PictureItem: &query.PictureItemListOptions{
					ItemID: groupData.ItemID,
				},
				Limit: newboxPicturesPerLine,
			}, repoItemPictureFields, pictures.OrderByAcceptDatetimeDesc, false)
			if err != nil {
				return nil, nil, err
			}

			group.Pictures, err = s.pictureExtractor.ExtractRows(
				ctx,
				pictureRows,
				&itemPictureFields,
				lang,
				userCtx,
			)
			if err != nil {
				return nil, nil, err
			}

			totalPictures, err := s.repository.Count(ctx, &query.PictureListOptions{
				Status: schema.PictureStatusAccepted,
				PictureItem: &query.PictureItemListOptions{
					ItemID: groupData.ItemID,
					TypeID: schema.PictureItemTypeContent,
				},
				AcceptDate: &acceptDate,
				Timezone:   timezone,
			})
			if err != nil {
				return nil, nil, err
			}

			group.TotalPictures = int32(totalPictures) //nolint: gosec
		} else {
			group.Pictures, err = s.pictureExtractor.ExtractRows(ctx, groupData.Pictures, &pictureFields, lang, userCtx)
			if err != nil {
				return nil, nil, err
			}
		}

		groups = append(groups, group)
	}

	return groups, pages, nil
}

func (s *PicturesGRPCServer) splitPictures(
	ctx context.Context, pictureRows []*schema.PictureRow,
) ([]*NewboxGroupDraft, error) {
	res := make([]*NewboxGroupDraft, 0)

	for _, pictureRow := range pictureRows {
		pictureItems, err := s.repository.PictureItems(ctx, &query.PictureItemListOptions{
			PictureID: pictureRow.ID,
			TypeID:    schema.PictureItemTypeContent,
		}, pictures.PictureItemOrderByNone, 0)
		if err != nil {
			return nil, err
		}

		if len(pictureItems) != 1 {
			res = append(res, &NewboxGroupDraft{
				Type:    newboxGroupTypePicture,
				Picture: pictureRow,
			})
		} else {
			itemID := pictureItems[0].ItemID

			found := false

			for idx := range res {
				if res[idx].Type == newboxGroupTypeItem && res[idx].ItemID == itemID {
					res[idx].Pictures = append(res[idx].Pictures, pictureRow)
					found = true

					break
				}
			}

			if !found {
				res = append(res, &NewboxGroupDraft{
					ItemID:   itemID,
					Type:     newboxGroupTypeItem,
					Pictures: []*schema.PictureRow{pictureRow},
				})
			}
		}
	}

	// convert single picture items to picture record
	// merge sibling single items
	return s.mergeSiblings(s.expandSmallItems(res)), nil
}

func (s *PicturesGRPCServer) mergeSiblings(groups []*NewboxGroupDraft) []*NewboxGroupDraft {
	result := make([]*NewboxGroupDraft, 0)
	picturesBuffer := make([]*schema.PictureRow, 0)

	for _, item := range groups {
		if item.Type == newboxGroupTypeItem {
			if len(picturesBuffer) > 0 {
				result = append(result, &NewboxGroupDraft{
					Type:     newboxGroupTypePictures,
					Pictures: picturesBuffer,
				})
				picturesBuffer = make([]*schema.PictureRow, 0)
			}

			result = append(result, item)
		} else {
			picturesBuffer = append(picturesBuffer, item.Picture)
		}
	}

	if len(picturesBuffer) > 0 {
		result = append(result, &NewboxGroupDraft{
			Type:     newboxGroupTypePictures,
			Pictures: picturesBuffer,
		})
	}

	return result
}

func (s *PicturesGRPCServer) expandSmallItems(items []*NewboxGroupDraft) []*NewboxGroupDraft {
	result := make([]*NewboxGroupDraft, 0)

	for _, item := range items {
		if item.Type != newboxGroupTypeItem {
			result = append(result, item)

			continue
		}

		if len(item.Pictures) <= 2 {
			for _, picture := range item.Pictures {
				result = append(result, &NewboxGroupDraft{
					Type:    newboxGroupTypePicture,
					Picture: picture,
				})
			}
		} else {
			result = append(result, item)
		}
	}

	return result
}

func (s *PicturesGRPCServer) getPicturePage(
	ctx context.Context, filter *query.PictureListOptions, identity string, order pictures.OrderBy,
) (int32, error) {
	filterCopy := *filter
	filterCopy.Identity = ""
	filterCopy.Limit = 0
	filterCopy.Page = 0

	rows, _, err := s.repository.Pictures(ctx, &filterCopy, nil, order, false)
	if err != nil {
		return 0, err
	}

	for index, row := range rows {
		if row.Identity == identity {
			return int32(math.Floor(float64(index)/float64(galleryItemsPerPage))) + 1, nil
		}
	}

	return 1, nil
}
