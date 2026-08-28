package goautowp

import (
	"context"
	"time"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/drexedam/gravatar"
	"github.com/jackc/pgtype"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const (
	avatarSize      = 70
	avatarLargeSize = 270
)

type UserExtractor struct {
	imageStorage       *storage.Storage
	picturesRepository *pictures.Repository
}

func NewUserExtractor(
	imageStorage *storage.Storage,
	picturesRepository *pictures.Repository,
) *UserExtractor {
	return &UserExtractor{
		imageStorage:       imageStorage,
		picturesRepository: picturesRepository,
	}
}

func (s *UserExtractor) Extract(
	ctx context.Context,
	row *schema.UsersRow,
	fields *UserFields,
	currentUserID int64,
	currentUserRoles []string,
) (*User, error) {
	identity := ""
	if row.Identity != nil {
		identity = *row.Identity
	}

	user := User{
		Id:       row.ID,
		Deleted:  row.Deleted,
		Route:    frontend.UserRoute(row.ID, row.Identity),
		Identity: identity,
	}

	isMe := row.ID == currentUserID

	if isMe {
		user.TermsAcceptanceRequired = row.TermsVersion == nil || *row.TermsVersion < users.TermsVersion
	}

	if !row.Deleted || util.Contains(currentUserRoles, users.RoleAdmin) {
		longAway := true

		if row.LastOnline != nil {
			date := time.Now().AddDate(0, -6, 0)
			longAway = date.After(*row.LastOnline)
		}

		user.Name = row.Name
		user.LongAway = longAway
		user.Green = row.Green
		user.SpecsWeight = row.SpecsWeight
		user.PicturesAdded = int32(row.PicturesAdded) //nolint:gosec

		if fields.GetRegDate() && row.RegDate != nil {
			user.CreateTime = timestamppb.New(*row.RegDate)
		}

		if row.LastOnline != nil {
			user.LastOnline = timestamppb.New(*row.LastOnline)
		}

		if row.EMail != nil {
			gr := gravatar.New(*row.EMail)
			user.Gravatar = gr.Size(avatarSize).Rating(gravatar.G).AvatarURL()

			if fields.GetGravatarLarge() {
				user.GravatarLarge = gr.Size(avatarLargeSize).Rating(gravatar.G).AvatarURL()
			}
		}

		if row.Img != nil {
			avatar, err := s.imageStorage.FormattedImage(ctx, *row.Img, "avatar")
			if err != nil {
				return nil, err
			}

			user.Avatar = APIImageToGRPC(avatar)

			if fields.GetPhoto() {
				photo, err := s.imageStorage.FormattedImage(ctx, *row.Img, "photo")
				if err != nil {
					return nil, err
				}

				user.Photo = APIImageToGRPC(photo)
			}
		}

		if fields.GetEmail() && row.EMail != nil &&
			(isMe || util.Contains(currentUserRoles, users.RoleModer)) {
			user.Email = *row.EMail
		}

		if fields.GetPicturesAcceptedCount() {
			count, err := s.picturesRepository.Count(ctx, &query.PictureListOptions{
				Status:  schema.PictureStatusAccepted,
				OwnerID: row.ID,
			})
			if err != nil {
				return nil, err
			}

			user.PicturesAcceptedCount = int32(count) //nolint:gosec
		}

		if fields.GetLastIp() && util.Contains(currentUserRoles, users.RoleModer) &&
			row.LastIP.Status == pgtype.Present {
			user.LastIp = row.LastIP.IPNet.IP.String()
		}

		if fields.GetLogin() && row.Login != nil && util.Contains(currentUserRoles, users.RoleModer) {
			user.Login = *row.Login
		}
	}

	if isMe {
		if fields.GetVotesLeft() {
			user.VotesLeft = row.VotesLeft
		}

		if fields.GetVotesPerDay() {
			user.VotesPerDay = row.VotesPerDay
		}

		if fields.GetLanguage() {
			user.Language = row.Language
		}

		if fields.GetTimezone() {
			user.Timezone = row.Timezone
		}

		if fields.GetImg() && row.Img != nil {
			img, err := s.imageStorage.Image(ctx, *row.Img)
			if err != nil {
				return nil, err
			}

			user.Img = APIImageToGRPC(img)
		}
	}

	return &user, nil
}
