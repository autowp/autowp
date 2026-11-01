package forums

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/forums"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/commentextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/autowp/goautowp/internal/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const (
	MaxTopicNameLength = 100

	topicNameField    = "name"
	topicMessageField = "message"
)

type Server struct {
	pb.UnimplementedForumsServer

	auth               *authn.Service
	forums             *forums.Repository
	commentsRepository *comments.Repository
	usersRepository    *users.Repository
	messageExtractor   *commentextractor.Extractor
}

func NewServer(
	auth *authn.Service,
	forums *forums.Repository,
	commentsRepository *comments.Repository,
	usersRepository *users.Repository,
	messageExtractor *commentextractor.Extractor,
) *Server {
	return &Server{
		auth:               auth,
		forums:             forums,
		commentsRepository: commentsRepository,
		usersRepository:    usersRepository,
		messageExtractor:   messageExtractor,
	}
}

func (s *Server) GetUserSummary(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.ForumsUserSummary, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	subscriptionsCount, err := s.forums.GetUserSummary(ctx, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.ForumsUserSummary{
		SubscriptionsCount: int32(subscriptionsCount), //nolint: gosec
	}, nil
}

func (s *Server) CreateTopic(
	ctx context.Context,
	in *pb.CreateTopicRequest,
) (*pb.CreateTopicResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	InvalidParams, err := validateCreateTopicRequest(ctx, in, s.commentsRepository, userCtx.UserID)
	if err != nil {
		return nil, err
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	ctx = context.WithoutCancel(ctx)

	topicID, err := s.forums.AddTopic(
		ctx,
		in.GetTopic().GetThemeId(),
		in.GetTopic().GetName(),
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

	if in.GetTopic().GetSubscription() {
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

	return &pb.CreateTopicResponse{
		Id: topicID,
	}, nil
}

func validateCreateTopicRequest(
	ctx context.Context,
	request *pb.CreateTopicRequest,
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

	if request.GetTopic() == nil {
		request.Topic = &pb.Topic{}
	}

	request.Topic.Name, problems, err = nameInputFilter.IsValidString(request.GetTopic().GetName())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       topicNameField,
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

	request.Message, problems, err = msgInputFilter.IsValidString(request.GetMessage())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       topicMessageField,
			Description: fv,
		})
	}

	needWait, err := commentsRepository.NeedWait(ctx, userID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if needWait {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       topicMessageField,
			Description: "Too often",
		})
	}

	return result, nil
}

func (s *Server) UpdateTopic(
	ctx context.Context,
	in *pb.UpdateTopicRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Errorf(codes.Unauthenticated, "unauthenticated")
	}

	forumAdmin := util.Contains(userCtx.Roles, users.RoleForumsModer)
	if !forumAdmin {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	values := in.GetTopic()
	maskPaths := in.GetUpdateMask().GetPaths()

	if util.Contains(maskPaths, "theme_id") {
		err = s.forums.MoveTopic(ctx, values.GetId(), values.GetThemeId())
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	if util.Contains(maskPaths, "status") {
		switch values.GetStatus() {
		case string(schema.ForumsTopicStatusClosed):
			err = s.forums.Close(ctx, values.GetId())
		case string(schema.ForumsTopicStatusNormal):
			err = s.forums.Open(ctx, values.GetId())
		case string(schema.ForumsTopicStatusDeleted):
			err = s.forums.Delete(ctx, values.GetId())
		default:
			return nil, status.Errorf(codes.InvalidArgument, "invalid status")
		}

		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	return &emptypb.Empty{}, nil
}

func convertTheme(theme *schema.ForumsThemeRow) *pb.Theme {
	return &pb.Theme{
		Id:            theme.ID,
		Name:          theme.Name,
		TopicsCount:   theme.TopicsCount,
		MessagesCount: theme.MessagesCount,
		DisableTopics: theme.DisableTopics,
		Description:   theme.Description,
	}
}

func convertTopic(topic *schema.ForumsTopicRow) *pb.Topic {
	return &pb.Topic{
		Id:           topic.ID,
		Name:         topic.Name,
		Status:       topic.Status,
		OldMessages:  topic.Messages - topic.NewMessages,
		NewMessages:  topic.NewMessages,
		CreateTime:   timestamppb.New(topic.CreatedAt),
		UserId:       topic.UserID,
		ThemeId:      topic.ThemeID,
		Subscription: topic.Subscription,
	}
}

func (s *Server) GetTheme(
	ctx context.Context,
	in *pb.GetThemeRequest,
) (*pb.Theme, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
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

func (s *Server) ListThemes(
	ctx context.Context,
	in *pb.ListThemesRequest,
) (*pb.ListThemesResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModerator := util.Contains(userCtx.Roles, users.RoleForumsModer)

	themes, err := s.forums.Themes(ctx, in.GetThemeId(), isModerator)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.Theme, len(themes))
	for idx, theme := range themes {
		result[idx] = convertTheme(theme)
	}

	return &pb.ListThemesResponse{
		Items: result,
	}, nil
}

func (s *Server) GetLastTopic(
	ctx context.Context,
	in *pb.GetThemeRequest,
) (*pb.Topic, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
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

func (s *Server) GetLastMessage(
	ctx context.Context,
	in *pb.GetTopicRequest,
) (*pb.CommentMessage, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModerator := util.Contains(userCtx.Roles, users.RoleForumsModer)
	canViewIP := util.Contains(userCtx.Roles, users.RoleModer)

	row, err := s.forums.LastMessage(ctx, in.GetId(), isModerator, canViewIP)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if row == nil {
		return nil, status.Error(codes.NotFound, "Message not found")
	}

	return s.messageExtractor.Extract(ctx, row, userCtx.UserID, userCtx.Roles, canViewIP, &pb.CommentMessageFields{})
}

func (s *Server) ListTopics(
	ctx context.Context,
	in *pb.ListTopicsRequest,
) (*pb.ListTopicsResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
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

	result := make([]*pb.Topic, len(topics))
	for idx, topic := range topics {
		result[idx] = convertTopic(topic)
	}

	return &pb.ListTopicsResponse{
		Items: result,
		Paginator: &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}

func (s *Server) GetTopic(
	ctx context.Context,
	in *pb.GetTopicRequest,
) (*pb.Topic, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
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
