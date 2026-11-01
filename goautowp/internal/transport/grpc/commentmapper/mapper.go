package commentmapper

import (
	"errors"
	"fmt"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/schema"
)

var (
	errUnknownCommentType        = errors.New("unknown comments type identifier")
	errUnknownModeratorAttention = errors.New("unknown CommentMessageModeratorAttention value")
)

func TypeToProto(commentsType schema.CommentMessageType) (pb.CommentsType, error) {
	switch commentsType {
	case schema.CommentMessageTypeIDPictures:
		return pb.CommentsType_PICTURES_TYPE_ID, nil
	case schema.CommentMessageTypeIDItems:
		return pb.CommentsType_ITEM_TYPE_ID, nil
	case schema.CommentMessageTypeIDVotings:
		return pb.CommentsType_VOTINGS_TYPE_ID, nil
	case schema.CommentMessageTypeIDArticles:
		return pb.CommentsType_ARTICLES_TYPE_ID, nil
	case schema.CommentMessageTypeIDForums:
		return pb.CommentsType_FORUMS_TYPE_ID, nil
	}

	return 0, fmt.Errorf("%w: `%v`", errUnknownCommentType, commentsType)
}

func ModeratorAttentionToProto(
	value schema.CommentMessageModeratorAttention,
) (pb.ModeratorAttention, error) {
	switch value {
	case schema.CommentMessageModeratorAttentionNone:
		return pb.ModeratorAttention_NONE, nil
	case schema.CommentMessageModeratorAttentionRequired:
		return pb.ModeratorAttention_REQUIRED, nil
	case schema.CommentMessageModeratorAttentionCompleted:
		return pb.ModeratorAttention_COMPLETE, nil
	}

	return 0, fmt.Errorf("%w: `%v`", errUnknownModeratorAttention, value)
}

func ModeratorAttentionFromProto(
	value pb.ModeratorAttention,
) (schema.CommentMessageModeratorAttention, error) {
	switch value {
	case pb.ModeratorAttention_NONE:
		return schema.CommentMessageModeratorAttentionNone, nil
	case pb.ModeratorAttention_REQUIRED:
		return schema.CommentMessageModeratorAttentionRequired, nil
	case pb.ModeratorAttention_COMPLETE:
		return schema.CommentMessageModeratorAttentionCompleted, nil
	}

	return 0, fmt.Errorf("%w: `%v`", errUnknownModeratorAttention, value)
}

func TypeFromProto(commentsType pb.CommentsType) (schema.CommentMessageType, error) {
	switch commentsType {
	case pb.CommentsType_PICTURES_TYPE_ID:
		return schema.CommentMessageTypeIDPictures, nil
	case pb.CommentsType_ITEM_TYPE_ID:
		return schema.CommentMessageTypeIDItems, nil
	case pb.CommentsType_VOTINGS_TYPE_ID:
		return schema.CommentMessageTypeIDVotings, nil
	case pb.CommentsType_ARTICLES_TYPE_ID:
		return schema.CommentMessageTypeIDArticles, nil
	case pb.CommentsType_FORUMS_TYPE_ID:
		return schema.CommentMessageTypeIDForums, nil
	case pb.CommentsType_UNKNOWN:
		return 0, nil
	}

	return 0, fmt.Errorf("%w: `%v`", errUnknownCommentType, commentsType)
}
