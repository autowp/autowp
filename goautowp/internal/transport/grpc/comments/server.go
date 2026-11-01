package comments

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/commentextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/commentmapper"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/transport/grpc/userextractor"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/autowp/goautowp/internal/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

const (
	commentMessageField = "message"
)

type Server struct {
	pb.UnimplementedCommentsServer

	auth             *authn.Service
	repository       *comments.Repository
	usersRepository  *users.Repository
	messageExtractor *commentextractor.Extractor
	userExtractor    *userextractor.Extractor
}

func NewServer(
	auth *authn.Service,
	commentsRepository *comments.Repository,
	usersRepository *users.Repository,
	messageExtractor *commentextractor.Extractor,
	userExtractor *userextractor.Extractor,
) *Server {
	return &Server{
		auth:             auth,
		repository:       commentsRepository,
		usersRepository:  usersRepository,
		messageExtractor: messageExtractor,
		userExtractor:    userExtractor,
	}
}

func (s *Server) GetCommentVotes(
	ctx context.Context,
	in *pb.GetCommentVotesRequest,
) (*pb.CommentVoteItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	votes, err := s.repository.GetVotes(ctx, in.GetCommentId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if votes == nil {
		return nil, status.Errorf(codes.NotFound, "not found")
	}

	rows := make([]schema.UsersRow, 0, len(votes.PositiveVotes)+len(votes.NegativeVotes))
	rows = append(rows, votes.PositiveVotes...)
	rows = append(rows, votes.NegativeVotes...)

	extractedUsers, err := s.userExtractor.ExtractRows(ctx, rows, nil, userCtx.UserID, userCtx.Roles)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.CommentVote, 0, len(extractedUsers))
	for idx, extracted := range extractedUsers {
		value := pb.CommentVote_POSITIVE
		if idx >= len(votes.PositiveVotes) {
			value = pb.CommentVote_NEGATIVE
		}

		result = append(result, &pb.CommentVote{ //nolint:exhaustruct
			Value: value,
			User:  extracted,
		})
	}

	return &pb.CommentVoteItems{ //nolint:exhaustruct
		Items: result,
	}, nil
}

func (s *Server) Subscribe(
	ctx context.Context,
	in *pb.CommentsSubscribeRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	commentsType, err := commentmapper.TypeFromProto(in.GetTypeId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.InvalidArgument, err.Error())
	}

	err = s.repository.Subscribe(ctx, userCtx.UserID, commentsType, in.GetItemId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, err
}

func (s *Server) UnSubscribe(
	ctx context.Context,
	in *pb.CommentsUnSubscribeRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	commentsType, err := commentmapper.TypeFromProto(in.GetTypeId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.InvalidArgument, err.Error())
	}

	err = s.repository.UnSubscribe(ctx, userCtx.UserID, commentsType, in.GetItemId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) View(
	ctx context.Context,
	in *pb.CommentsViewRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	commentsType, err := commentmapper.TypeFromProto(in.GetTypeId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.InvalidArgument, err.Error())
	}

	err = s.repository.View(ctx, userCtx.UserID, commentsType, in.GetItemId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) SetDeleted(
	ctx context.Context,
	in *pb.CommentsSetDeletedRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	isModer := util.Contains(userCtx.Roles, users.RoleCommentsModer)

	if in.GetDeleted() {
		// An author may delete their own comment; only a moderator can delete anyone's (with a
		// reason and a notification - see QueueDeleteMessage).
		if isModer {
			err = s.repository.QueueDeleteMessage(ctx, in.GetCommentId(), userCtx.UserID, in.GetReason())
		} else {
			err = s.repository.DeleteMessageByAuthor(ctx, in.GetCommentId(), userCtx.UserID)
		}
	} else {
		if !isModer {
			return nil, status.Errorf(codes.PermissionDenied, "permission denied")
		}

		err = s.repository.RestoreMessage(ctx, in.GetCommentId())
	}

	if err != nil {
		return &emptypb.Empty{}, commentWriteError(err)
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) UpdateComment(
	ctx context.Context,
	in *pb.UpdateCommentRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	comment := in.GetComment()
	if comment.GetId() == 0 {
		return nil, status.Errorf(codes.InvalidArgument, "id is zero")
	}

	if !util.Contains(in.GetUpdateMask().GetPaths(), "text") {
		return &emptypb.Empty{}, nil
	}

	message, problems, err := commentMessageInputFilter.IsValidString(comment.GetText())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if len(problems) > 0 {
		fvs := make([]*errdetails.BadRequest_FieldViolation, 0, len(problems))
		for _, problem := range problems {
			fvs = append(fvs, &errdetails.BadRequest_FieldViolation{
				Field:       commentMessageField,
				Description: problem,
			})
		}

		return nil, grpcerrors.FieldViolations(fvs)
	}

	if err = s.repository.UpdateMessage(ctx, comment.GetId(), userCtx.UserID, message); err != nil {
		return nil, commentWriteError(err)
	}

	return &emptypb.Empty{}, nil
}

func commentWriteError(err error) error {
	switch {
	case err == nil:
		return nil
	case errors.Is(err, comments.ErrMessageNotFound):
		return status.Error(codes.NotFound, err.Error())
	case errors.Is(err, comments.ErrNotMessageAuthor):
		return status.Error(codes.PermissionDenied, err.Error())
	case errors.Is(err, comments.ErrMessageLocked), errors.Is(err, comments.ErrMessageIsDeleted):
		return status.Error(codes.FailedPrecondition, err.Error())
	default:
		return status.Error(codes.Internal, err.Error())
	}
}

func (s *Server) MoveComment(
	ctx context.Context,
	in *pb.CommentsMoveCommentRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if !util.Contains(userCtx.Roles, users.RoleForumsModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	commentType, err := s.repository.GetCommentType(ctx, in.GetCommentId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	if commentType != schema.CommentMessageTypeIDForums {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	commentsType, err := commentmapper.TypeFromProto(in.GetTypeId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	err = s.repository.MoveMessage(ctx, in.GetCommentId(), commentsType, in.GetItemId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) VoteComment(
	ctx context.Context,
	in *pb.CommentsVoteCommentRequest,
) (*pb.CommentsVoteCommentResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	votesLeft, err := s.usersRepository.GetVotesLeft(ctx, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if votesLeft <= 0 {
		return nil, status.Error(codes.PermissionDenied, "today vote limit reached")
	}

	ctx = context.WithoutCancel(ctx)

	votes, err := s.repository.VoteComment(ctx, userCtx.UserID, in.GetCommentId(), in.GetVote())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.usersRepository.DecVotes(ctx, userCtx.UserID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.CommentsVoteCommentResponse{ //nolint:exhaustruct
		Votes: votes,
	}, nil
}

// commentMessageInputFilter trims and length-checks a comment body. Shared by Add and
// UpdateComment so both enforce the same rules.
var commentMessageInputFilter = validation.InputFilter{
	Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
	Validators: []validation.ValidatorInterface{
		&validation.NotEmpty{},
		&validation.StringLength{Min: 0, Max: comments.MaxMessageLength},
	},
}

func validateAddCommentRequest(
	ctx context.Context,
	request *pb.AddCommentRequest,
	repository *comments.Repository,
	userID int64,
) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	request.Message, problems, err = commentMessageInputFilter.IsValidString(request.GetMessage())
	if err != nil {
		return nil, err
	}

	for _, fv := range problems {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       commentMessageField,
			Description: fv,
		})
	}

	needWait, err := repository.NeedWait(ctx, userID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if needWait {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       commentMessageField,
			Description: "Too often",
		})
	}

	return result, nil
}

func (s *Server) Add(
	ctx context.Context,
	in *pb.AddCommentRequest,
) (*pb.AddCommentResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := validateAddCommentRequest(ctx, in, s.repository, userCtx.UserID)
	if err != nil {
		return nil, err
	}

	if len(InvalidParams) > 0 {
		return nil, grpcerrors.FieldViolations(InvalidParams)
	}

	commentsType, err := commentmapper.TypeFromProto(in.GetTypeId())
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	err = s.repository.AssertItem(ctx, commentsType, in.GetItemId())
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	moderatorAttention := in.GetModeratorAttention()

	ctx = context.WithoutCancel(ctx)

	messageID, err := s.repository.Add(
		ctx,
		commentsType,
		in.GetItemId(),
		in.GetParentId(),
		userCtx.UserID,
		in.GetMessage(),
		userCtx.IP.String(),
		moderatorAttention,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if messageID == 0 {
		return nil, status.Errorf(codes.Internal, "Message add failed")
	}

	if util.Contains(userCtx.Roles, users.RoleModer) && in.GetParentId() > 0 && in.GetResolve() {
		err = s.repository.CompleteMessage(ctx, in.GetParentId())
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	if in.GetTypeId() == pb.CommentsType_FORUMS_TYPE_ID {
		err = s.usersRepository.IncForumMessages(ctx, userCtx.UserID)
	} else {
		err = s.usersRepository.TouchLastMessage(ctx, userCtx.UserID)
	}

	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if in.GetParentId() > 0 {
		err = s.repository.NotifyAboutReply(ctx, messageID)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}
	}

	err = s.repository.NotifySubscribers(ctx, messageID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.AddCommentResponse{ //nolint:exhaustruct
		Id: messageID,
	}, nil
}

func (s *Server) GetMessagePage(
	ctx context.Context, in *pb.GetMessagePageRequest,
) (*pb.CommentMessagePage, error) {
	itemID, typeID, page, err := s.repository.MessagePage(ctx, in.GetMessageId(), in.GetPerPage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	convertedTypeID, err := commentmapper.TypeToProto(typeID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.CommentMessagePage{ //nolint:exhaustruct
		TypeId: convertedTypeID,
		ItemId: itemID,
		Page:   page,
	}, nil
}

func (s *Server) GetMessage(
	ctx context.Context,
	in *pb.GetMessageRequest,
) (*pb.CommentMessage, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	canViewIP := util.Contains(userCtx.Roles, users.RoleModer)

	fields := in.GetFields()
	if fields == nil {
		fields = &pb.CommentMessageFields{} //nolint:exhaustruct
	}

	row, err := s.repository.Message(
		ctx,
		in.GetId(),
		fields.GetPreview() || fields.GetText(),
		fields.GetVote(),
		canViewIP,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if row == nil {
		return nil, status.Errorf(codes.NotFound, "not found")
	}

	return s.messageExtractor.Extract(
		ctx,
		row,
		userCtx.UserID,
		userCtx.Roles,
		canViewIP,
		fields,
	)
}

func (s *Server) GetMessages(
	ctx context.Context,
	in *pb.GetMessagesRequest,
) (*pb.CommentMessages, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)
	canViewIP := isModer

	typeID, err := commentmapper.TypeFromProto(in.GetTypeId())
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	fields := in.GetFields()
	if fields == nil {
		fields = &pb.CommentMessageFields{}
	}

	options := comments.Request{
		ItemID:       in.GetItemId(),
		TypeID:       typeID,
		ParentID:     in.GetParentId(),
		NoParents:    in.GetNoParents(),
		UserID:       in.GetUserId(),
		OrderBy:      comments.OrderByDateAsc,
		FetchMessage: fields.GetPreview() || fields.GetText(),
		FetchVote:    fields.GetVote(),
		FetchIP:      canViewIP,
		Page:         in.GetPage(),
	}

	switch in.GetOrder() {
	case pb.GetMessagesRequest_VOTE_DESC:
		options.OrderBy = comments.OrderByVoteDesc
	case pb.GetMessagesRequest_VOTE_ASC:
		options.OrderBy = comments.OrderByVoteAsc
	case pb.GetMessagesRequest_DATE_DESC:
		options.OrderBy = comments.OrderByDateDesc
	case pb.GetMessagesRequest_DATE_ASC, pb.GetMessagesRequest_DEFAULT:
		options.OrderBy = comments.OrderByDateAsc
	}

	if isModer {
		if len(in.GetUserIdentity()) > 0 {
			options.UserID, err = s.usersRepository.UserIDByIdentity(ctx, in.GetUserIdentity())
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		}

		if in.GetModeratorAttention() != pb.ModeratorAttention_NONE {
			ma, err := commentmapper.ModeratorAttentionFromProto(in.GetModeratorAttention())
			if err != nil {
				return nil, status.Error(codes.InvalidArgument, err.Error())
			}

			options.ModeratorAttention = ma
		}

		if in.GetPicturesOfItemId() > 0 {
			options.PicturesOfItemID = in.GetPicturesOfItemId()
		}
	} else if in.GetItemId() == 0 && in.GetUserId() == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if in.GetLimit() <= 0 {
		in.Limit = 50000
	}

	options.PerPage = in.GetLimit()

	rows, pages, err := s.repository.Messages(ctx, options)
	if err != nil {
		return nil, err
	}

	msgs := make([]*pb.CommentMessage, 0)
	if in.GetLimit() > 0 {
		msgs, err = s.messageExtractor.ExtractRows(ctx, rows, userCtx.UserID, userCtx.Roles, canViewIP, fields)
		if err != nil {
			return nil, err
		}

		if userCtx.UserID > 0 && in.GetItemId() > 0 && in.GetTypeId() > 0 {
			err = s.repository.SetSubscriptionSent(
				ctx,
				typeID,
				in.GetItemId(),
				userCtx.UserID,
				false,
			)
			if err != nil {
				return nil, err
			}
		}
	}

	return &pb.CommentMessages{
		Items: msgs,
		Paginator: &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}
