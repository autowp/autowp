package messaging

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/messaging"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/autowp/goautowp/internal/validation"
	"golang.org/x/sync/errgroup"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const (
	messageMessageField         = "message"
	messagingSummaryParallelism = 4
)

type Server struct {
	pb.UnimplementedMessagingServer

	repository *messaging.Repository
	auth       *authn.Service
}

func NewServer(repository *messaging.Repository, auth *authn.Service) *Server {
	return &Server{
		repository: repository,
		auth:       auth,
	}
}

func (s *Server) GetMessagesNewCount(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.MessageNewCount, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	count, err := s.repository.GetUserNewMessagesCount(ctx, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.MessageNewCount{
		Count: count,
	}, nil
}

func (s *Server) GetMessagesSummary(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.MessageSummary, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	var response pb.MessageSummary

	group, queryCtx := errgroup.WithContext(ctx)
	group.SetLimit(messagingSummaryParallelism)

	// Each independent count owns one response field. Authorization has already
	// completed, and the response is read only after all tasks have joined.
	for _, lookup := range []struct {
		count  func(context.Context, int64) (int32, error)
		target *int32
	}{
		{count: s.repository.GetInboxCount, target: &response.InboxCount},
		{count: s.repository.GetInboxNewCount, target: &response.InboxNewCount},
		{count: s.repository.GetSentCount, target: &response.SentCount},
		{count: s.repository.GetSystemCount, target: &response.SystemCount},
		{count: s.repository.GetSystemNewCount, target: &response.SystemNewCount},
	} {
		group.Go(func() error {
			count, err := lookup.count(queryCtx, userCtx.UserID)
			if err != nil {
				return err
			}

			*lookup.target = count

			return nil
		})
	}

	if err := group.Wait(); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &response, nil
}

func (s *Server) DeleteMessage(
	ctx context.Context,
	in *pb.MessagingDeleteMessage,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	err = s.repository.DeleteMessage(ctx, userCtx.UserID, in.GetMessageId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) ClearFolder(
	ctx context.Context,
	in *pb.MessagingClearFolder,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	switch in.GetFolder() {
	case "sent":
		err = s.repository.ClearSent(ctx, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

	case "system":
		err = s.repository.ClearSystem(ctx, userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	default:
		return nil, status.Error(codes.InvalidArgument, "invalid argument")
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) CreateMessage(
	ctx context.Context,
	in *pb.CreateMessageRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	var (
		fvs      = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
	)

	message := in.GetMessage()
	messageText := message.GetText()

	messageInputFilter := validation.InputFilter{
		Filters:    []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{&validation.NotEmpty{}},
	}

	messageText, problems, err = messageInputFilter.IsValidString(messageText)
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		fvs = append(fvs, &errdetails.BadRequest_FieldViolation{
			Field:       messageMessageField,
			Description: fv,
		})
	}

	if len(fvs) > 0 {
		return nil, grpcerrors.FieldViolations(fvs)
	}

	// A blacklisted sender cannot message the recipient. Only an administrator bypasses this.
	if !util.Contains(userCtx.Roles, users.RoleAdmin) {
		blocked, err := s.repository.IsBlacklisted(ctx, message.GetToUserId(), userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		if blocked {
			return nil, status.Error(
				codes.PermissionDenied,
				"the recipient does not accept messages from you",
			)
		}
	}

	err = s.repository.CreateMessage(ctx, userCtx.UserID, message.GetToUserId(), messageText)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetMessages(
	ctx context.Context,
	in *pb.MessagingGetMessagesRequest,
) (*pb.MessagingGetMessagesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	var (
		messages []messaging.Message
		pages    *util.Pages
	)

	switch in.GetFolder() {
	case "inbox":
		messages, pages, err = s.repository.GetInbox(ctx, userCtx.UserID, in.GetPage())
	case "sent":
		messages, pages, err = s.repository.GetSentbox(ctx, userCtx.UserID, in.GetPage())
	case "system":
		messages, pages, err = s.repository.GetSystembox(ctx, userCtx.UserID, in.GetPage())
	case "dialog":
		messages, pages, err = s.repository.GetDialogbox(
			ctx,
			userCtx.UserID,
			in.GetUserId(),
			in.GetPage(),
		)
	default:
		return nil, status.Errorf(codes.InvalidArgument, "Unexpected folder value")
	}

	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	var sendingBlocked bool

	if in.GetFolder() == "dialog" && in.GetUserId() != 0 &&
		!util.Contains(userCtx.Roles, users.RoleAdmin) {
		sendingBlocked, err = s.repository.IsBlacklisted(ctx, in.GetUserId(), userCtx.UserID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	items := make([]*pb.Message, len(messages))

	for idx, msg := range messages {
		item := pb.Message{
			Id:              msg.ID,
			Text:            msg.Text,
			IsNew:           msg.IsNew,
			CanDelete:       msg.CanDelete,
			CanReply:        msg.CanReply,
			CreateTime:      timestamppb.New(msg.Date),
			AllMessagesLink: msg.AllMessagesLink,
			DialogCount:     msg.DialogCount,
			ToUserId:        msg.ToUserID,
		}

		if msg.AuthorID != nil {
			item.AuthorId = *msg.AuthorID
		}

		if msg.DialogWithUserID != 0 {
			item.DialogWithUserId = msg.DialogWithUserID
		}

		items[idx] = &item
	}

	return &pb.MessagingGetMessagesResponse{
		Items: items,
		Paginator: &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
		SendingBlocked: sendingBlocked,
	}, nil
}
