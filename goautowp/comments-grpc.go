package goautowp

import (
	"context"
	"errors"
	"fmt"

	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"github.com/doug-martin/goqu/v9/exp"
	"github.com/jackc/pgtype"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

var (
	errUnknownCommentType        = errors.New("unknown comments type identifier")
	errUnknownModeratorAttention = errors.New("unknown CommentMessageModeratorAttention value")
)

const (
	MaxReplies = 500

	commentMessageField = "message"
)

type CommentsGRPCServer struct {
	UnimplementedCommentsServer

	auth               *Auth
	repository         *comments.Repository
	usersRepository    *users.Repository
	picturesRepository *pictures.Repository
	userExtractor      *UserExtractor
}

func extractPicturesStatus(status schema.PictureStatus) PictureStatus {
	switch status {
	case schema.PictureStatusUnknown:
		return PictureStatus_PICTURE_STATUS_UNKNOWN
	case schema.PictureStatusAccepted:
		return PictureStatus_PICTURE_STATUS_ACCEPTED
	case schema.PictureStatusRemoving:
		return PictureStatus_PICTURE_STATUS_REMOVING
	case schema.PictureStatusInbox:
		return PictureStatus_PICTURE_STATUS_INBOX
	case schema.PictureStatusRemoved:
		return PictureStatus_PICTURE_STATUS_REMOVED
	}

	return PictureStatus_PICTURE_STATUS_UNKNOWN
}

func convertCommentsType(commentsType CommentsType) (schema.CommentMessageType, error) {
	switch commentsType {
	case CommentsType_PICTURES_TYPE_ID:
		return schema.CommentMessageTypeIDPictures, nil
	case CommentsType_ITEM_TYPE_ID:
		return schema.CommentMessageTypeIDItems, nil
	case CommentsType_VOTINGS_TYPE_ID:
		return schema.CommentMessageTypeIDVotings, nil
	case CommentsType_ARTICLES_TYPE_ID:
		return schema.CommentMessageTypeIDArticles, nil
	case CommentsType_FORUMS_TYPE_ID:
		return schema.CommentMessageTypeIDForums, nil
	case CommentsType_UNKNOWN:
		return 0, nil
	}

	return 0, fmt.Errorf("%w: `%v`", errUnknownCommentType, commentsType)
}

func extractConvertType(commentsType schema.CommentMessageType) (CommentsType, error) {
	switch commentsType {
	case schema.CommentMessageTypeIDPictures:
		return CommentsType_PICTURES_TYPE_ID, nil
	case schema.CommentMessageTypeIDItems:
		return CommentsType_ITEM_TYPE_ID, nil
	case schema.CommentMessageTypeIDVotings:
		return CommentsType_VOTINGS_TYPE_ID, nil
	case schema.CommentMessageTypeIDArticles:
		return CommentsType_ARTICLES_TYPE_ID, nil
	case schema.CommentMessageTypeIDForums:
		return CommentsType_FORUMS_TYPE_ID, nil
	}

	return 0, fmt.Errorf("%w: `%v`", errUnknownCommentType, commentsType)
}

func extractModeratorAttention(
	value schema.CommentMessageModeratorAttention,
) (ModeratorAttention, error) {
	switch value {
	case schema.CommentMessageModeratorAttentionNone:
		return ModeratorAttention_NONE, nil
	case schema.CommentMessageModeratorAttentionRequired:
		return ModeratorAttention_REQUIRED, nil
	case schema.CommentMessageModeratorAttentionCompleted:
		return ModeratorAttention_COMPLETE, nil
	}

	return 0, fmt.Errorf("%w: `%v`", errUnknownModeratorAttention, value)
}

func convertModeratorAttention(
	value ModeratorAttention,
) (schema.CommentMessageModeratorAttention, error) {
	switch value {
	case ModeratorAttention_NONE:
		return schema.CommentMessageModeratorAttentionNone, nil
	case ModeratorAttention_REQUIRED:
		return schema.CommentMessageModeratorAttentionRequired, nil
	case ModeratorAttention_COMPLETE:
		return schema.CommentMessageModeratorAttentionCompleted, nil
	}

	return 0, fmt.Errorf("%w: `%v`", errUnknownModeratorAttention, value)
}

func extractMessage(
	ctx context.Context, row *schema.CommentMessageRow, repository *comments.Repository,
	picturesRepository *pictures.Repository, userID int64, roles []string, canViewIP bool,
	fields *CommentMessageFields,
) (*CommentMessage, error) {
	canRemove := util.Contains(roles, users.RoleCommentsModer)
	isModer := util.Contains(roles, users.RoleModer)

	typeID, err := extractConvertType(row.TypeID)
	if err != nil {
		return nil, err
	}

	parentID := row.ParentID.Int64
	if !row.ParentID.Valid {
		parentID = 0
	}

	ma, err := extractModeratorAttention(row.ModeratorAttention)
	if err != nil {
		return nil, err
	}

	isNew := false
	if fields.GetIsNew() && userID > 0 {
		isNew, err = repository.IsNewMessage(ctx, row.TypeID, row.ItemID, row.CreatedAt, userID)
		if err != nil {
			return nil, err
		}
	}

	authorID := row.AuthorID.Int64
	if !row.AuthorID.Valid {
		authorID = 0
	}

	var (
		preview       string
		text          string
		vote          int32
		userVote      int32
		route         []string
		replies       []*CommentMessage
		pictureStatus PictureStatus
	)

	if canRemove || !row.Deleted {
		if fields.GetPreview() {
			preview = util.GetTextPreview(row.Message, util.TextPreviewOptions{
				Maxlines:  1,
				Maxlength: comments.CommentMessagePreviewLength,
			})
		}

		if fields.GetRoute() {
			route, err = repository.MessageRowRoute(ctx, row.TypeID, row.ItemID, row.ID)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetText() {
			text = row.Message
		}

		if fields.GetVote() {
			vote = row.Vote
		}

		if fields.GetUserVote() {
			if userID != 0 {
				userVote, err = repository.UserVote(ctx, userID, row.ID)
				if err != nil {
					return nil, err
				}
			}
		}

		if fields.GetReplies() {
			paginator := repository.Paginator(comments.Request{
				ItemID:       row.ItemID,
				TypeID:       row.TypeID,
				ParentID:     row.ID,
				PerPage:      MaxReplies,
				Order:        []exp.OrderedExpression{schema.CommentMessageTableDatetimeCol.Desc()},
				FetchMessage: fields.GetPreview() || fields.GetText(),
				FetchVote:    fields.GetVote(),
				FetchIP:      canViewIP,
			})

			sqSelect, err := paginator.GetCurrentItems(ctx)
			if err != nil {
				return nil, err
			}

			rows := make([]*schema.CommentMessageRow, 0)

			err = sqSelect.ScanStructsContext(ctx, &rows)
			if err != nil {
				return nil, err
			}

			replies = make([]*CommentMessage, 0)

			for _, row := range rows {
				msg, err := extractMessage(
					ctx,
					row,
					repository,
					picturesRepository,
					userID,
					roles,
					canViewIP,
					fields,
				)
				if err != nil {
					return nil, err
				}

				replies = append(replies, msg)
			}
		}

		if fields.GetStatus() && isModer {
			if row.TypeID == schema.CommentMessageTypeIDPictures {
				ps, err := picturesRepository.Status(ctx, row.ItemID)
				if err != nil {
					return nil, err
				}

				pictureStatus = extractPicturesStatus(ps)
			}
		}
	}

	ip := ""
	if canViewIP && row.IP.Status == pgtype.Present {
		ip = row.IP.IPNet.IP.String()
	}

	return &CommentMessage{
		Id:                 row.ID,
		TypeId:             typeID,
		ItemId:             row.ItemID,
		ParentId:           parentID,
		CreateTime:         timestamppb.New(row.CreatedAt),
		Deleted:            row.Deleted,
		ModeratorAttention: ma,
		IsNew:              isNew,
		AuthorId:           authorID,
		IpAddress:          ip,
		Preview:            preview,
		Text:               text,
		Vote:               vote,
		Route:              route,
		UserVote:           userVote,
		Replies:            replies,
		PictureStatus:      pictureStatus,
	}, nil
}

func NewCommentsGRPCServer(
	auth *Auth,
	commentsRepository *comments.Repository,
	usersRepository *users.Repository,
	picturesRepository *pictures.Repository,
	userExtractor *UserExtractor,
) *CommentsGRPCServer {
	return &CommentsGRPCServer{
		auth:               auth,
		repository:         commentsRepository,
		usersRepository:    usersRepository,
		picturesRepository: picturesRepository,
		userExtractor:      userExtractor,
	}
}

func (s *CommentsGRPCServer) GetCommentVotes(
	ctx context.Context,
	in *GetCommentVotesRequest,
) (*CommentVoteItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	votes, err := s.repository.GetVotes(ctx, in.GetCommentId())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if votes == nil {
		return nil, status.Errorf(codes.NotFound, "not found")
	}

	result := make([]*CommentVote, 0)

	for idx := range votes.PositiveVotes {
		extracted, err := s.userExtractor.Extract(
			ctx,
			&votes.PositiveVotes[idx],
			nil,
			userCtx.UserID,
			userCtx.Roles,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		result = append(result, &CommentVote{ //nolint:exhaustruct
			Value: CommentVote_POSITIVE,
			User:  extracted,
		})
	}

	for idx := range votes.NegativeVotes {
		extracted, err := s.userExtractor.Extract(
			ctx,
			&votes.NegativeVotes[idx],
			nil,
			userCtx.UserID,
			userCtx.Roles,
		)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		result = append(result, &CommentVote{ //nolint:exhaustruct
			Value: CommentVote_NEGATIVE,
			User:  extracted,
		})
	}

	return &CommentVoteItems{ //nolint:exhaustruct
		Items: result,
	}, nil
}

func (s *CommentsGRPCServer) Subscribe(
	ctx context.Context,
	in *CommentsSubscribeRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	commentsType, err := convertCommentsType(in.GetTypeId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.InvalidArgument, err.Error())
	}

	err = s.repository.Subscribe(ctx, userCtx.UserID, commentsType, in.GetItemId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, err
}

func (s *CommentsGRPCServer) UnSubscribe(
	ctx context.Context,
	in *CommentsUnSubscribeRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	commentsType, err := convertCommentsType(in.GetTypeId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.InvalidArgument, err.Error())
	}

	err = s.repository.UnSubscribe(ctx, userCtx.UserID, commentsType, in.GetItemId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *CommentsGRPCServer) View(
	ctx context.Context,
	in *CommentsViewRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	commentsType, err := convertCommentsType(in.GetTypeId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.InvalidArgument, err.Error())
	}

	err = s.repository.View(ctx, userCtx.UserID, commentsType, in.GetItemId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *CommentsGRPCServer) SetDeleted(
	ctx context.Context,
	in *CommentsSetDeletedRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	if !util.Contains(userCtx.Roles, users.RoleCommentsModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	if in.GetDeleted() {
		err = s.repository.QueueDeleteMessage(ctx, in.GetCommentId(), userCtx.UserID, in.GetReason())
	} else {
		err = s.repository.RestoreMessage(ctx, in.GetCommentId())
	}

	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *CommentsGRPCServer) MoveComment(
	ctx context.Context,
	in *CommentsMoveCommentRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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

	commentsType, err := convertCommentsType(in.GetTypeId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	err = s.repository.MoveMessage(ctx, in.GetCommentId(), commentsType, in.GetItemId())
	if err != nil {
		return &emptypb.Empty{}, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *CommentsGRPCServer) VoteComment(
	ctx context.Context,
	in *CommentsVoteCommentRequest,
) (*CommentsVoteCommentResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
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

	return &CommentsVoteCommentResponse{ //nolint:exhaustruct
		Votes: votes,
	}, nil
}

func (s *AddCommentRequest) Validate(
	ctx context.Context,
	repository *comments.Repository,
	userID int64,
) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	msgInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.NotEmpty{},
			&validation.StringLength{Min: 0, Max: comments.MaxMessageLength},
		},
	}

	s.Message, problems, err = msgInputFilter.IsValidString(s.GetMessage())
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

func (s *CommentsGRPCServer) Add(
	ctx context.Context,
	in *AddCommentRequest,
) (*AddCommentResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if userCtx.UserID == 0 {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	InvalidParams, err := in.Validate(ctx, s.repository, userCtx.UserID)
	if err != nil {
		return nil, err
	}

	if len(InvalidParams) > 0 {
		return nil, wrapFieldViolations(InvalidParams)
	}

	commentsType, err := convertCommentsType(in.GetTypeId())
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

	if in.GetTypeId() == CommentsType_FORUMS_TYPE_ID {
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

	return &AddCommentResponse{ //nolint:exhaustruct
		Id: messageID,
	}, nil
}

func (s *CommentsGRPCServer) GetMessagePage(
	ctx context.Context, in *GetMessagePageRequest,
) (*CommentMessagePage, error) {
	itemID, typeID, page, err := s.repository.MessagePage(ctx, in.GetMessageId(), in.GetPerPage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	convertedTypeID, err := extractConvertType(typeID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &CommentMessagePage{ //nolint:exhaustruct
		TypeId: convertedTypeID,
		ItemId: itemID,
		Page:   page,
	}, nil
}

func (s *CommentsGRPCServer) GetMessage(
	ctx context.Context,
	in *GetMessageRequest,
) (*CommentMessage, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	canViewIP := util.Contains(userCtx.Roles, users.RoleModer)

	fields := in.GetFields()
	if fields == nil {
		fields = &CommentMessageFields{} //nolint:exhaustruct
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

	return extractMessage(
		ctx,
		row,
		s.repository,
		s.picturesRepository,
		userCtx.UserID,
		userCtx.Roles,
		canViewIP,
		fields,
	)
}

func (s *CommentsGRPCServer) GetMessages(
	ctx context.Context,
	in *GetMessagesRequest,
) (*CommentMessages, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)
	canViewIP := isModer

	typeID, err := convertCommentsType(in.GetTypeId())
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	fields := in.GetFields()
	if fields == nil {
		fields = &CommentMessageFields{}
	}

	options := comments.Request{
		ItemID:       in.GetItemId(),
		TypeID:       typeID,
		ParentID:     in.GetParentId(),
		NoParents:    in.GetNoParents(),
		UserID:       in.GetUserId(),
		Order:        []exp.OrderedExpression{schema.CommentMessageTableDatetimeCol.Asc()},
		FetchMessage: fields.GetPreview() || fields.GetText(),
		FetchVote:    fields.GetVote(),
		FetchIP:      canViewIP,
		Page:         in.GetPage(),
	}

	switch in.GetOrder() {
	case GetMessagesRequest_VOTE_DESC:
		options.Order = []exp.OrderedExpression{
			schema.CommentMessageTableVoteCol.Desc(),
			schema.CommentMessageTableDatetimeCol.Desc(),
		}
	case GetMessagesRequest_VOTE_ASC:
		options.Order = []exp.OrderedExpression{
			schema.CommentMessageTableVoteCol.Asc(),
			schema.CommentMessageTableDatetimeCol.Desc(),
		}
	case GetMessagesRequest_DATE_DESC:
		options.Order = []exp.OrderedExpression{schema.CommentMessageTableDatetimeCol.Desc()}
	case GetMessagesRequest_DATE_ASC, GetMessagesRequest_DEFAULT:
		options.Order = []exp.OrderedExpression{schema.CommentMessageTableDatetimeCol.Asc()}
	}

	if isModer {
		if len(in.GetUserIdentity()) > 0 {
			options.UserID, err = s.usersRepository.UserIDByIdentity(ctx, in.GetUserIdentity())
			if err != nil {
				return nil, status.Error(codes.Internal, err.Error())
			}
		}

		if in.GetModeratorAttention() != ModeratorAttention_NONE {
			ma, err := convertModeratorAttention(in.GetModeratorAttention())
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

	paginator := s.repository.Paginator(options)

	msgs := make([]*CommentMessage, 0)

	if in.GetLimit() > 0 {
		sqSelect, err := paginator.GetCurrentItems(ctx)
		if err != nil {
			return nil, err
		}

		rows := make([]*schema.CommentMessageRow, 0)

		err = sqSelect.ScanStructsContext(ctx, &rows)
		if err != nil {
			return nil, err
		}

		for _, row := range rows {
			msg, err := extractMessage(
				ctx,
				row,
				s.repository,
				s.picturesRepository,
				userCtx.UserID,
				userCtx.Roles,
				canViewIP,
				fields,
			)
			if err != nil {
				return nil, err
			}

			msgs = append(msgs, msg)
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

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, err
	}

	return &CommentMessages{
		Items: msgs,
		Paginator: &Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}
