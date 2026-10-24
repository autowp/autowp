package goautowp

import (
	"context"
	"errors"
	"maps"
	"slices"
	"time"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/pictures"
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
	imageStorage       userImageStorage
	picturesRepository userPictureCounts
}

type userImageStorage interface {
	Image(ctx context.Context, id int) (*storage.Image, error)
	FormattedImages(ctx context.Context, ids []int, formatName string) (map[int]storage.Image, error)
}

type userPictureCounts interface {
	AcceptedCountsByOwnerIDs(ctx context.Context, ids []int64) (map[int64]int32, error)
}

type userExtractionData struct {
	avatars map[int]storage.Image
	photos  map[int]storage.Image
	counts  map[int64]int32
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
	result, err := s.ExtractRows(ctx, []schema.UsersRow{*row}, fields, currentUserID, currentUserRoles)
	if err != nil {
		return nil, err
	}

	return result[0], nil
}

func (s *UserExtractor) ExtractRows(
	ctx context.Context,
	rows []schema.UsersRow,
	fields *UserFields,
	currentUserID int64,
	currentUserRoles []string,
) ([]*User, error) {
	imageIDs := make(map[int]struct{})
	userIDs := make(map[int64]struct{})

	isAdmin := util.Contains(currentUserRoles, users.RoleAdmin)
	for _, row := range rows {
		if row.Deleted && !isAdmin {
			continue
		}

		userIDs[row.ID] = struct{}{}
		if row.Img != nil {
			imageIDs[*row.Img] = struct{}{}
		}
	}

	var (
		data    userExtractionData
		lookups []func(context.Context) error
	)

	// Photos depend on the successfully loaded avatars, including missing-image
	// recovery. Keep that chain together while counts load independently.
	if len(imageIDs) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			data.avatars, err = s.formattedImages(ctx, slices.Sorted(maps.Keys(imageIDs)), "avatar")
			if err != nil {
				return err
			}

			if fields.GetPhoto() {
				data.photos, err = s.formattedImages(ctx, slices.Sorted(maps.Keys(data.avatars)), "photo")
			}

			return err
		})
	}

	if fields.GetPicturesAcceptedCount() && len(userIDs) > 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			data.counts, err = s.picturesRepository.AcceptedCountsByOwnerIDs(ctx, slices.Sorted(maps.Keys(userIDs)))

			return err
		})
	}

	if err := runItemLookups(ctx, lookups); err != nil {
		return nil, err
	}

	result := make([]*User, 0, len(rows))
	for idx := range rows {
		user, err := s.extract(ctx, &rows[idx], fields, currentUserID, currentUserRoles, data)
		if err != nil {
			return nil, err
		}

		result = append(result, user)
	}

	return result, nil
}

func (s *UserExtractor) formattedImages(ctx context.Context, ids []int, format string) (map[int]storage.Image, error) {
	if len(ids) == 0 {
		return make(map[int]storage.Image), nil
	}

	images, err := s.imageStorage.FormattedImages(ctx, ids, format)
	if !errors.Is(err, storage.ErrImageNotFound) {
		return images, err
	}

	// Isolate stale image references using smaller batches. Healthy lists need just
	// one query, and one missing image does not force individual reads for every user.
	if len(ids) == 1 {
		return make(map[int]storage.Image), nil
	}

	middle := len(ids) / 2

	left, err := s.formattedImages(ctx, ids[:middle], format)
	if err != nil {
		return nil, err
	}

	right, err := s.formattedImages(ctx, ids[middle:], format)
	if err != nil {
		return nil, err
	}

	maps.Copy(left, right)

	return left, nil
}

func (s *UserExtractor) extract(
	ctx context.Context,
	row *schema.UsersRow,
	fields *UserFields,
	currentUserID int64,
	currentUserRoles []string,
	data userExtractionData,
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
		user.ContactsPublic = row.ContactsPublic
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
			if avatar, ok := data.avatars[*row.Img]; ok {
				user.Avatar = APIImageToGRPC(&avatar)
			} else {
				logging.Warnf("user %d references missing image %d, skipping avatar", row.ID, *row.Img)
			}

			if user.GetAvatar() != nil && fields.GetPhoto() {
				if photo, ok := data.photos[*row.Img]; ok {
					user.Photo = APIImageToGRPC(&photo)
				} else {
					logging.Warnf("user %d references missing image %d, skipping photo", row.ID, *row.Img)
				}
			}
		}

		if fields.GetEmail() && row.EMail != nil &&
			(isMe || util.Contains(currentUserRoles, users.RoleModer)) {
			user.Email = *row.EMail
		}

		if fields.GetPicturesAcceptedCount() {
			user.PicturesAcceptedCount = data.counts[row.ID]
		}

		if fields.GetLastIp() && util.Contains(currentUserRoles, users.RoleModer) &&
			row.LastIP.Status == pgtype.Present {
			user.LastIp = row.LastIP.IPNet.IP.String()
		}

		if fields.GetLogin() && row.Login != nil && util.Contains(currentUserRoles, users.RoleModer) {
			user.Login = *row.Login
		}

		// Contacts go to anonymous visitors only when the owner opted in; any signed-in user
		// (moderators included) always sees them.
		if fields.GetContacts() && (row.ContactsPublic || currentUserID != 0) {
			user.Contacts = make([]*UserContact, 0, len(row.Contacts))
			for _, contact := range row.Contacts {
				user.Contacts = append(user.Contacts, &UserContact{
					Platform: UserContactPlatform(contact.Platform),
					Username: contact.Username,
				})
			}
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
			if errors.Is(err, storage.ErrImageNotFound) {
				logging.Warnf("user %d references missing image %d, skipping img: %v", row.ID, *row.Img, err)
			} else if err != nil {
				return nil, err
			}

			user.Img = APIImageToGRPC(img)
		}
	}

	return &user, nil
}
