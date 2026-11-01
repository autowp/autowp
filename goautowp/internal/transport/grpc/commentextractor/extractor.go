package commentextractor

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/commentmapper"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/jackc/pgtype"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const maxReplies = 500

type Extractor struct {
	repository         *comments.Repository
	picturesRepository *pictures.Repository
}

func New(repository *comments.Repository, picturesRepository *pictures.Repository) *Extractor {
	return &Extractor{repository: repository, picturesRepository: picturesRepository}
}

type commentMessageState struct {
	isNew    map[int64]bool
	userVote map[int64]int32
}

func (s *Extractor) Extract(
	ctx context.Context, row *schema.CommentMessageRow, userID int64, roles []string, canViewIP bool,
	fields *pb.CommentMessageFields,
) (*pb.CommentMessage, error) {
	return s.extractWithState(ctx, row, userID, roles, canViewIP, fields, nil)
}

func (s *Extractor) ExtractRows(
	ctx context.Context, rows []*schema.CommentMessageRow, userID int64, roles []string,
	canViewIP bool, fields *pb.CommentMessageFields,
) ([]*pb.CommentMessage, error) {
	state := &commentMessageState{}

	var reads []func(context.Context) error
	if fields.GetIsNew() && userID > 0 {
		reads = append(reads, func(ctx context.Context) error {
			var err error

			state.isNew, err = s.repository.NewMessages(ctx, userID, rows)

			return err
		})
	}

	if fields.GetUserVote() && userID != 0 {
		ids := make([]int64, 0, len(rows))
		for _, row := range rows {
			if !row.Deleted || util.Contains(roles, users.RoleCommentsModer) {
				ids = append(ids, row.ID)
			}
		}

		reads = append(reads, func(ctx context.Context) error {
			var err error

			state.userVote, err = s.repository.UserVotes(ctx, userID, ids)

			return err
		})
	}

	if err := extraction.Run(ctx, reads); err != nil {
		return nil, err
	}

	msgs := make([]*pb.CommentMessage, len(rows))

	lookups := make([]func(context.Context) error, 0, len(rows))
	for index, row := range rows {
		lookups = append(lookups, func(ctx context.Context) error {
			msg, err := s.extractWithState(ctx, row,
				userID, roles, canViewIP, fields, state)
			if err != nil {
				return err
			}

			msgs[index] = msg

			return nil
		})
	}

	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	return msgs, nil
}

func (s *Extractor) extractWithState(
	ctx context.Context, row *schema.CommentMessageRow, userID int64, roles []string, canViewIP bool,
	fields *pb.CommentMessageFields, state *commentMessageState,
) (*pb.CommentMessage, error) {
	canRemove := util.Contains(roles, users.RoleCommentsModer)
	isModer := util.Contains(roles, users.RoleModer)

	typeID, err := commentmapper.TypeToProto(row.TypeID)
	if err != nil {
		return nil, err
	}

	parentID := row.ParentID.Int64
	if !row.ParentID.Valid {
		parentID = 0
	}

	ma, err := commentmapper.ModeratorAttentionToProto(row.ModeratorAttention)
	if err != nil {
		return nil, err
	}

	var lookups []func(context.Context) error

	isNew := false

	if state != nil && state.isNew != nil {
		isNew = state.isNew[row.ID]
	} else if fields.GetIsNew() && userID > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			isNew, err = s.repository.IsNewMessage(ctx, row.TypeID, row.ItemID, row.CreatedAt, userID)

			return err
		})
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
		replies       []*pb.CommentMessage
		pictureStatus pb.PictureStatus
	)

	if canRemove || !row.Deleted {
		if fields.GetPreview() {
			preview = util.GetTextPreview(row.Message, util.TextPreviewOptions{
				Maxlines:  1,
				Maxlength: comments.CommentMessagePreviewLength,
			})
		}

		if fields.GetRoute() {
			lookups = append(lookups, func(ctx context.Context) error {
				var err error

				route, err = s.repository.MessageRowRoute(ctx, row.TypeID, row.ItemID, row.ID)

				return err
			})
		}

		if fields.GetText() {
			text = row.Message
		}

		if fields.GetVote() {
			vote = row.Vote
		}

		if state != nil && state.userVote != nil {
			userVote = state.userVote[row.ID]
		} else if fields.GetUserVote() && userID != 0 {
			lookups = append(lookups, func(ctx context.Context) error {
				var err error

				userVote, err = s.repository.UserVote(ctx, userID, row.ID)

				return err
			})
		}

		if fields.GetReplies() {
			lookups = append(lookups, func(ctx context.Context) error {
				rows, _, err := s.repository.Messages(ctx, comments.Request{
					ItemID:       row.ItemID,
					TypeID:       row.TypeID,
					ParentID:     row.ID,
					PerPage:      maxReplies,
					OrderBy:      comments.OrderByDateDesc,
					FetchMessage: fields.GetPreview() || fields.GetText(),
					FetchVote:    fields.GetVote(),
					FetchIP:      canViewIP,
				})
				if err != nil {
					return err
				}

				replies = make([]*pb.CommentMessage, 0)

				for _, row := range rows {
					msg, err := s.Extract(
						ctx,
						row,
						userID,
						roles,
						canViewIP,
						fields,
					)
					if err != nil {
						return err
					}

					replies = append(replies, msg)
				}

				return nil
			})
		}

		if fields.GetStatus() && isModer && row.TypeID == schema.CommentMessageTypeIDPictures {
			lookups = append(lookups, func(ctx context.Context) error {
				ps, err := s.picturesRepository.Status(ctx, row.ItemID)
				if err != nil {
					return err
				}

				pictureStatus = picturemapper.StatusToProto(ps)

				return nil
			})
		}
	}

	// Reuse the bounded lookup runner. Recursive replies inherit its context
	// marker and execute their reads inline, so depth cannot multiply concurrency.
	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	ip := ""
	if canViewIP && row.IP.Status == pgtype.Present {
		ip = row.IP.IPNet.IP.String()
	}

	var updateTime *timestamppb.Timestamp
	if row.UpdatedAt.Valid {
		updateTime = timestamppb.New(row.UpdatedAt.Time)
	}

	return &pb.CommentMessage{
		Id:                 row.ID,
		TypeId:             typeID,
		ItemId:             row.ItemID,
		ParentId:           parentID,
		CreateTime:         timestamppb.New(row.CreatedAt),
		UpdateTime:         updateTime,
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
