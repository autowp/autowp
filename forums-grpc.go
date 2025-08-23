package goautowp

import (
	"context"

	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const MaxTopicNameLength = 100

type ForumsGRPCServer struct {
	UnimplementedForumsServer

	auth               *Auth
	forums             *Forums
	commentsRepository *comments.Repository
	usersRepository    *users.Repository
}

func NewForumsGRPCServer(
	auth *Auth,
	forums *Forums,
	commentsRepository *comments.Repository,
	usersRepository *users.Repository,
) *ForumsGRPCServer {
	return &ForumsGRPCServer{
		auth:               auth,
		forums:             forums,
		commentsRepository: commentsRepository,
		usersRepository:    usersRepository,
	}
}

func (s *ForumsGRPCServer) GetUserSummary(
	ctx context.Context,
	_ *emptypb.Empty,
) (*APIForumsUserSummary, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	subscriptionsCount, err := s.forums.GetUserSummary(ctx, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &APIForumsUserSummary{
		SubscriptionsCount: int32(subscriptionsCount), //nolint: gosec
	}, nil
}

func (s *ForumsGRPCServer) CreateTopic(
	ctx context.Context,
	in *APICreateTopicRequest,
) (*APICreateTopicResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	InvalidParams, err := in.Validate(ctx, s.commentsRepository, userCtx.UserID)
	if err != nil {
		return nil, err
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	ctx = context.WithoutCancel(ctx)

	topicID, err := s.forums.AddTopic(
		ctx,
		in.GetThemeId(),
		in.GetName(),
		userCtx.UserID,
		userCtx.IP.String(),
	)
	if err != nil {
		return nil, err
	}

	_, err = s.commentsRepository.Add(
		ctx,
		schema.CommentMessageTypeIDForums,
		topicID,
		0,
		userCtx.UserID,
		in.GetMessage(),
		userCtx.IP.String(),
		in.GetModeratorAttention(),
	)
	if err != nil {
		return nil, err
	}

	if in.GetSubscription() {
		err = s.commentsRepository.Subscribe(
			ctx,
			userCtx.UserID,
			schema.CommentMessageTypeIDForums,
			topicID,
		)
		if err != nil {
			return nil, err
		}
	}

	err = s.usersRepository.IncForumTopics(ctx, userCtx.UserID)
	if err != nil {
		return nil, err
	}

	return &APICreateTopicResponse{
		Id: topicID,
	}, nil
}

func (s *APICreateTopicRequest) Validate(
	ctx context.Context,
	commentsRepository *comments.Repository,
	userID int64,
) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	nameInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
			&validation.StringLength{Max: MaxTopicNameLength},
		},
	}

	s.Name, problems, err = nameInputFilter.IsValidString(s.GetName())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "name",
			Description: fv,
		})
	}

	msgInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
			&validation.StringLength{Max: comments.MaxMessageLength},
		},
	}

	s.Message, problems, err = msgInputFilter.IsValidString(s.GetMessage())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "message",
			Description: fv,
		})
	}

	needWait, err := commentsRepository.NeedWait(ctx, userID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if needWait {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "message",
			Description: "Too often",
		})
	}

	return result, nil
}

func (s *ForumsGRPCServer) CloseTopic(
	ctx context.Context,
	in *APISetTopicStatusRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	forumAdmin := util.Contains(userCtx.Roles, users.RoleForumsModer)
	if !forumAdmin {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	err = s.forums.Close(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *ForumsGRPCServer) OpenTopic(
	ctx context.Context,
	in *APISetTopicStatusRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	forumAdmin := util.Contains(userCtx.Roles, users.RoleForumsModer)
	if !forumAdmin {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	err = s.forums.Open(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *ForumsGRPCServer) DeleteTopic(
	ctx context.Context,
	in *APISetTopicStatusRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	forumAdmin := util.Contains(userCtx.Roles, users.RoleForumsModer)
	if !forumAdmin {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	err = s.forums.Delete(ctx, in.GetId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *ForumsGRPCServer) MoveTopic(
	ctx context.Context,
	in *APIMoveTopicRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "Unauthenticated")
	}

	forumAdmin := util.Contains(userCtx.Roles, users.RoleForumsModer)
	if !forumAdmin {
		return nil, status.Errorf(codes.PermissionDenied, "PermissionDenied")
	}

	err = s.forums.MoveTopic(ctx, in.GetId(), in.GetThemeId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func convertTheme(theme *ForumsTheme) *APIForumsTheme {
	return &APIForumsTheme{
		Id:            theme.ID,
		Name:          theme.Name,
		TopicsCount:   theme.TopicsCount,
		MessagesCount: theme.MessagesCount,
		DisableTopics: theme.DisableTopics,
		Description:   theme.Description,
	}
}

func convertTopic(topic *ForumsTopic) *APIForumsTopic {
	return &APIForumsTopic{
		Id:           topic.ID,
		Name:         topic.Name,
		Status:       topic.Status,
		OldMessages:  topic.Messages - topic.NewMessages,
		NewMessages:  topic.NewMessages,
		CreatedAt:    timestamppb.New(topic.CreatedAt),
		UserId:       topic.UserID,
		ThemeId:      topic.ThemeID,
		Subscription: topic.Subscription,
	}
}

func (s *ForumsGRPCServer) GetTheme(
	ctx context.Context,
	in *APIGetForumsThemeRequest,
) (*APIForumsTheme, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModerator := util.Contains(userCtx.Roles, users.RoleForumsModer)

	theme, err := s.forums.Theme(ctx, in.GetId(), isModerator)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if theme == nil {
		return nil, status.Error(codes.NotFound, "Theme not found")
	}

	return convertTheme(theme), nil
}

func (s *ForumsGRPCServer) GetThemes(
	ctx context.Context,
	in *APIGetForumsThemesRequest,
) (*APIForumsThemes, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModerator := util.Contains(userCtx.Roles, users.RoleForumsModer)

	themes, err := s.forums.Themes(ctx, in.GetThemeId(), isModerator)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*APIForumsTheme, len(themes))
	for idx, theme := range themes {
		result[idx] = convertTheme(theme)
	}

	return &APIForumsThemes{
		Items: result,
	}, nil
}

func (s *ForumsGRPCServer) GetLastTopic(
	ctx context.Context,
	in *APIGetForumsThemeRequest,
) (*APIForumsTopic, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModerator := util.Contains(userCtx.Roles, users.RoleForumsModer)

	topic, err := s.forums.LastTopic(ctx, in.GetId(), userCtx.UserID, isModerator)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if topic == nil {
		return nil, status.Error(codes.NotFound, "Topic not found")
	}

	return convertTopic(topic), nil
}

func (s *ForumsGRPCServer) GetLastMessage(
	ctx context.Context,
	in *APIGetForumsTopicRequest,
) (*APICommentMessage, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModerator := util.Contains(userCtx.Roles, users.RoleForumsModer)

	msg, err := s.forums.LastMessage(ctx, in.GetId(), isModerator)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if msg == nil {
		return nil, status.Error(codes.NotFound, "Message not found")
	}

	var userID int64
	if msg.UserID.Valid {
		userID = msg.UserID.Int64
	}

	return &APICommentMessage{
		Id:        msg.ID,
		CreatedAt: timestamppb.New(msg.Datetime),
		UserId:    userID,
	}, nil
}

func (s *ForumsGRPCServer) GetTopics(
	ctx context.Context,
	in *APIGetForumsTopicsRequest,
) (*APIForumsTopics, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModerator := util.Contains(userCtx.Roles, users.RoleForumsModer)

	topics, pages, err := s.forums.Topics(
		ctx,
		in.GetThemeId(),
		userCtx.UserID,
		isModerator,
		in.GetSubscription(),
		in.GetPage(),
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*APIForumsTopic, len(topics))
	for idx, topic := range topics {
		result[idx] = convertTopic(topic)
	}

	return &APIForumsTopics{
		Items: result,
		Paginator: &Pages{
			PageCount:        pages.PageCount,
			First:            pages.First,
			Last:             pages.Last,
			Previous:         pages.Previous,
			Next:             pages.Next,
			Current:          pages.Current,
			FirstPageInRange: pages.FirstPageInRange,
			LastPageInRange:  pages.LastPageInRange,
			PagesInRange:     pages.PagesInRange,
			TotalItemCount:   pages.TotalItemCount,
		},
	}, nil
}

func (s *ForumsGRPCServer) GetTopic(
	ctx context.Context,
	in *APIGetForumsTopicRequest,
) (*APIForumsTopic, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModerator := util.Contains(userCtx.Roles, users.RoleForumsModer)

	topic, err := s.forums.Topic(ctx, in.GetId(), userCtx.UserID, isModerator)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if topic == nil {
		return nil, status.Error(codes.NotFound, "Topic not found")
	}

	return convertTopic(topic), nil
}
