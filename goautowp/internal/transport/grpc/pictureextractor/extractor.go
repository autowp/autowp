package pictureextractor

import (
	"context"
	"errors"
	"fmt"
	"html"
	"maps"
	"math"
	"slices"
	"strings"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/comments"
	"github.com/autowp/goautowp/internal/i18nbundle"
	"github.com/autowp/goautowp/internal/image/storage"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/textstorage"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/dfdistanceextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/imagemapper"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/autowp/goautowp/internal/transport/grpc/pictureitemextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"golang.org/x/sync/errgroup"
	"google.golang.org/genproto/googleapis/type/date"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const maxPaginatorLength = 500

// The formatted variants of a picture's image the fields ask for by name.
const (
	pictureGalleryFormat      = "picture-gallery"
	pictureGalleryFullFormat  = "picture-gallery-full"
	picturePreviewLargeFormat = "picture-preview-large"

	// How many of them there are, for sizing the maps keyed by format.
	pictureImageFormatCount = 5
)

var errItemNotFound = errors.New("item not found")

type ItemExtractor interface {
	ExtractRows(ctx context.Context, rows []*items.Item, fields *pb.ItemFields,
		lang string, userCtx authn.User) ([]*pb.Item, error)
}

type Dependencies struct {
	PicturesRepository    func(ctx context.Context) (*pictures.Repository, error)
	ItemsRepository       func(ctx context.Context) (*items.Repository, error)
	CommentsRepository    func(ctx context.Context) (*comments.Repository, error)
	I18n                  func() (*i18nbundle.I18n, error)
	ImageStorage          func(ctx context.Context) (*storage.Storage, error)
	TextStorageRepository func(ctx context.Context) (*textstorage.Repository, error)
	DfDistanceExtractor   func(ctx context.Context) (*dfdistanceextractor.Extractor, error)
	PictureItemExtractor  func() *pictureitemextractor.Extractor
	ItemExtractor         func() ItemExtractor
}

type Extractor struct {
	dependencies Dependencies
}

func New(dependencies Dependencies) *Extractor {
	return &Extractor{dependencies: dependencies}
}

func (s *Extractor) Extract(
	ctx context.Context,
	row *schema.PictureRow,
	fields *pb.PictureFields,
	lang string,
	userCtx authn.User,
) (*pb.Picture, error) {
	result, err := s.ExtractRows(ctx, []*schema.PictureRow{row}, fields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	if len(result) == 0 {
		return nil, errItemNotFound
	}

	return result[0], nil
}

func (s *Extractor) ExtractRows( //nolint: maintidx
	ctx context.Context,
	rows []*schema.PictureRow,
	fields *pb.PictureFields,
	lang string,
	userCtx authn.User,
) ([]*pb.Picture, error) {
	if len(rows) == 0 {
		return []*pb.Picture{}, nil
	}

	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	if fields == nil {
		fields = &pb.PictureFields{}
	}

	var (
		namesData map[int64]pictures.PictureNameFormatterOptions
		err       error
		result    = make([]*pb.Picture, 0, len(rows))
		images    = make(map[int]*storage.Image)
	)

	picturesRepository, err := s.dependencies.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	i18nBundle, err := s.dependencies.I18n()
	if err != nil {
		return nil, err
	}

	imageStorage, err := s.dependencies.ImageStorage(ctx)
	if err != nil {
		return nil, err
	}

	textstorageRepository, err := s.dependencies.TextStorageRepository(ctx)
	if err != nil {
		return nil, err
	}

	var commentsRepository *comments.Repository

	if fields.GetSubscribed() && userCtx.UserID > 0 {
		commentsRepository, err = s.dependencies.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}
	}

	// These preloads are independent of each other - each is its own round trip to Postgres or
	// S3, writing its own map that the row loop below reads - so fan them out and wait once.
	// The only ordering constraint is images -> formatted (format requests are keyed by image
	// id), kept sequential inside a single task.
	var (
		stats             map[int64]comments.TopicStat
		subscribed        map[int64]bool
		copyrights        map[int32]string
		rightsVotes       map[int64]pictures.ModerVoteCounts
		acceptedStats     map[int64]pictures.RelatedAcceptedStat
		authorSuggestions map[int64][]*pb.PictureAuthorSuggestion
		siblingsByID      map[int64]*pb.PictureSiblings
		votesByID         map[int64]*pictures.VoteSummary
		viewsByID         map[int64]int32
		moderVotes        map[int64]pictures.ModerVoteSummary
		pictureItems      map[int64][]*pb.PictureItem
		moderVoteItems    map[int64][]*pb.PictureModerVote
		paths             map[int64][]*pb.PathTreePictureItem
		formatted         = make(map[string]map[int]storage.Image, pictureImageFormatCount)
	)

	pictureIDs := make([]int64, 0, len(rows))
	inboxIDs := make(map[int64]struct{})
	textIDs := make(map[int32]struct{})
	acceptedIDs := make(map[int64]struct{})
	authorIDs := make(map[int64]struct{})

	for _, row := range rows {
		pictureIDs = append(pictureIDs, row.ID)
		if fields.GetAcceptedCount() || (fields.GetIsLast() && row.Status == schema.PictureStatusAccepted) {
			acceptedIDs[row.ID] = struct{}{}
		}

		if fields.GetAuthorSuggestions() &&
			(isModer || (userCtx.UserID != 0 && row.OwnerID.Valid && row.OwnerID.Int64 == userCtx.UserID)) {
			authorIDs[row.ID] = struct{}{}
		}

		if fields.GetRights() && row.Status == schema.PictureStatusInbox {
			inboxIDs[row.ID] = struct{}{}
		}

		if fields.GetCopyrights() && row.CopyrightsTextID.Valid {
			textIDs[row.CopyrightsTextID.Int32] = struct{}{}
		}
	}

	preloadGroup, preloadCtx := errgroup.WithContext(ctx)
	preloadGroup.SetLimit(extraction.PictureParallelism(ctx))
	// Nested item and picture reads execute within this task's single slot.
	preloadCtx = extraction.WithInlineLookups(preloadCtx)
	preloadCtx = extraction.WithPictureParallelism(preloadCtx, 1)

	if fields.GetSubscribed() && userCtx.UserID > 0 {
		preloadGroup.Go(func() error {
			var err error

			subscribed, err = commentsRepository.SubscribedTopics(preloadCtx, userCtx.UserID,
				schema.CommentMessageTypeIDPictures, pictureIDs)

			return err
		})
	}

	if fields.GetRights() {
		preloadGroup.Go(func() error {
			var err error

			rightsVotes, err = picturesRepository.ModerVoteCountsBatch(preloadCtx, slices.Collect(maps.Keys(inboxIDs)))

			return err
		})
	}

	if fields.GetCopyrights() {
		preloadGroup.Go(func() error {
			var err error

			copyrights, err = textstorageRepository.Texts(preloadCtx, slices.Collect(maps.Keys(textIDs)))

			return err
		})
	}

	if fields.GetAcceptedCount() || fields.GetIsLast() {
		preloadGroup.Go(func() error {
			var err error

			acceptedStats, err = picturesRepository.RelatedAcceptedStats(
				preloadCtx,
				slices.Collect(maps.Keys(acceptedIDs)),
			)

			return err
		})
	}

	if fields.GetAuthorSuggestions() {
		preloadGroup.Go(func() error {
			var err error

			authorSuggestions, err = s.preloadAuthorSuggestions(
				preloadCtx,
				picturesRepository,
				slices.Collect(maps.Keys(authorIDs)),
				lang,
				userCtx,
			)

			return err
		})
	}

	if request := fields.GetSiblings(); request != nil {
		preloadGroup.Go(func() error {
			var err error

			nestedCtx := extraction.WithPictureParallelism(preloadCtx, 1)
			siblingsByID, err = s.preloadSiblings(
				nestedCtx,
				picturesRepository,
				pictureIDs,
				request.GetFields(),
				lang,
				userCtx,
			)

			return err
		})
	}

	if fields.GetModerVote() || (fields.GetModerVoted() && userCtx.UserID != 0) {
		preloadGroup.Go(func() error {
			if fields.GetModerVote() {
				var preloadErr error

				moderVotes, preloadErr = picturesRepository.ModerVoteSummaries(preloadCtx, pictureIDs, userCtx.UserID)

				return preloadErr
			}

			votedIDs, preloadErr := picturesRepository.ModerVotedPictureIDs(preloadCtx, pictureIDs, userCtx.UserID)
			if preloadErr != nil {
				return preloadErr
			}

			moderVotes = make(map[int64]pictures.ModerVoteSummary, len(votedIDs))
			for _, id := range votedIDs {
				moderVotes[id] = pictures.ModerVoteSummary{Voted: true}
			}

			return nil
		})
	}

	if request := fields.GetPictureItem(); request != nil {
		preloadGroup.Go(func() error {
			var preloadErr error

			pictureItems, preloadErr = s.preloadPictureItems(preloadCtx, pictureIDs, request, lang, userCtx)

			return preloadErr
		})
	}

	if request := fields.GetPictureModerVotes(); request != nil {
		preloadGroup.Go(func() error {
			var preloadErr error

			moderVoteItems, preloadErr = s.preloadPictureModerVotes(preloadCtx, pictureIDs, request)

			return preloadErr
		})
	}

	if fields.GetCommentsCount() {
		preloadGroup.Go(func() error {
			var preloadErr error

			stats, preloadErr = s.preloadTopicsStat(preloadCtx, pictureIDs, userCtx.UserID)

			return preloadErr
		})
	}

	if fields.GetVotes() {
		preloadGroup.Go(func() error {
			var preloadErr error

			votesByID, preloadErr = picturesRepository.GetVotesBatch(preloadCtx, pictureIDs, userCtx.UserID)

			return preloadErr
		})
	}

	if fields.GetViews() {
		preloadGroup.Go(func() error {
			var preloadErr error

			viewsByID, preloadErr = picturesRepository.PictureViewsBatch(preloadCtx, pictureIDs)

			return preloadErr
		})
	}

	if fields.GetNameText() || fields.GetNameHtml() {
		preloadGroup.Go(func() error {
			var preloadErr error

			namesData, preloadErr = picturesRepository.NameData(preloadCtx, rows, pictures.NameDataOptions{
				Language: lang,
			})

			return preloadErr
		})
	}

	if pathRequest := fields.GetPath(); pathRequest != nil {
		parentID := pathRequest.GetParentId()

		preloadGroup.Go(func() error {
			var preloadErr error

			paths, preloadErr = s.preloadPaths(preloadCtx, rows, parentID)

			return preloadErr
		})
	}

	// One task for both image steps because they are ordered: the formatted-variant requests can
	// need the raw image rows (crop dimensions for the gallery format). The raw rows are loaded
	// only when a caller wants the original/gallery image or is a moderator; the formatted
	// variants - thumbnails included - are loaded whenever any field asks for one, regardless.
	preloadGroup.Go(func() error {
		if fields.GetImage() || fields.GetImageGallery() || isModer {
			ids := make([]int, 0, len(rows))
			for _, row := range rows {
				if row.ImageID.Valid {
					ids = append(ids, int(row.ImageID.Int64))
				}
			}

			loadedImages, preloadErr := imageStorage.Images(preloadCtx, ids)
			if preloadErr != nil {
				return preloadErr
			}

			maps.Copy(images, loadedImages)
		}

		// Formatted variants for the whole batch: one query per format asked for, rather than one
		// per format per row - a gallery of two dozen pictures used to make two dozen of them.
		// FormattedImages looks the format up for a set of images and generates the ones that are
		// missing, exactly as FormattedImage does for one.
		for formatName, formatIDs := range pictureImageFormatRequests(rows, fields, images) {
			formattedImages, preloadErr := imageStorage.FormattedImages(preloadCtx, formatIDs, formatName)
			if preloadErr != nil {
				return preloadErr
			}

			formatted[formatName] = formattedImages
		}

		return nil
	})

	if err = preloadGroup.Wait(); err != nil {
		return nil, err
	}

	var dfDistanceExtractor *dfdistanceextractor.Extractor
	if fields.GetDfDistance() != nil {
		dfDistanceExtractor, err = s.dependencies.DfDistanceExtractor(ctx)
		if err != nil {
			return nil, err
		}
	}

	// Remaining reads share one bounded queue across all pictures. Batched maps
	// are complete before this loop and can be read without synchronization.
	group, groupCtx := errgroup.WithContext(ctx)
	group.SetLimit(extraction.PictureParallelism(ctx))

	groupCtx = extraction.WithInlineLookups(groupCtx)
	groupCtx = extraction.WithPictureParallelism(groupCtx, 1)

	for _, row := range rows {
		resultRow := &pb.Picture{
			Id:               row.ID,
			Identity:         row.Identity,
			Width:            uint32(row.Width),
			Height:           uint32(row.Height),
			CopyrightsTextId: util.NullInt32ToScalar(row.CopyrightsTextID),
			OwnerId:          util.NullInt64ToScalar(row.OwnerID),
			Status:           picturemapper.StatusToProto(row.Status),
			Resolution:       fmt.Sprintf("%d×%d", row.Width, row.Height),
			CreateTime:       timestamppb.New(row.CreatedAt),
			TakenDate: &date.Date{
				Year:  int32(util.NullInt16ToScalar(row.TakenYear)),
				Month: int32(util.NullByteToScalar(row.TakenMonth)),
				Day:   int32(util.NullByteToScalar(row.TakenDay)),
			},
			DpiX:           util.NullInt32ToScalar(row.DPIX),
			DpiY:           util.NullInt32ToScalar(row.DPIY),
			License:        picturemapper.LicenseToProto(row.LicenseID),
			SourceUrl:      util.NullStringToString(row.SourceURL),
			AuthorWithheld: row.AuthorSuppressionID.Valid,
		}

		if row.ChangeStatusUserID.Valid {
			resultRow.ChangeStatusUserId = row.ChangeStatusUserID.Int64
		}

		if row.ChangeSourceURLUserID.Valid {
			resultRow.ChangeSourceUrlUserId = row.ChangeSourceURLUserID.Int64
		}

		if row.ChangeSourceURLDate.Valid {
			resultRow.ChangeSourceUrlDate = timestamppb.New(row.ChangeSourceURLDate.Time)
		}

		if isModer && fields.GetSpecialName() {
			resultRow.SpecialName = util.NullStringToString(row.Name)
		}

		if isModer && row.ImageID.Valid {
			if img, ok := images[int(row.ImageID.Int64)]; ok {
				resultRow.Cropped = img.CropHeight() > 0 && img.CropWidth() > 0
				if resultRow.GetCropped() {
					resultRow.CropResolution = fmt.Sprintf(
						"%d×%d",
						img.CropWidth(),
						img.CropHeight(),
					)
				}
			}
		}

		if fields.GetNameText() || fields.GetNameHtml() {
			nameData, ok := namesData[row.ID]
			if ok {
				pictureNameFormatter := pictures.NewPictureNameFormatter(
					items.NewItemNameFormatter(i18nBundle),
					i18nBundle,
				)

				if fields.GetNameText() {
					resultRow.NameText, err = pictureNameFormatter.FormatText(nameData, lang)
					if err != nil {
						return nil, err
					}
				}

				if fields.GetNameHtml() {
					resultRow.NameHtml, err = pictureNameFormatter.FormatHTML(nameData, lang)
					if err != nil {
						return nil, err
					}
				}
			}
		}

		if row.Point.Valid {
			resultRow.Point = &latlng.LatLng{
				Latitude:  row.Point.Point.Y(),
				Longitude: row.Point.Point.X(),
			}
		}

		if fields.GetImage() && row.ImageID.Valid {
			if image, ok := images[int(row.ImageID.Int64)]; ok {
				resultRow.Image = imagemapper.FromStorage(image)
			}
		}

		if fields.GetThumbMedium() && row.ImageID.Valid {
			if image, ok := formatted[imagemapper.ThumbMediumFormat][int(row.ImageID.Int64)]; ok {
				resultRow.ThumbMedium = imagemapper.FromStorage(&image)
			}
		}

		if fields.GetThumbLarge() && row.ImageID.Valid {
			if image, ok := formatted[imagemapper.ThumbLargeFormat][int(row.ImageID.Int64)]; ok {
				resultRow.ThumbLarge = imagemapper.FromStorage(&image)
			}
		}

		if fields.GetImageGalleryFull() && row.ImageID.Valid {
			if image, ok := formatted[pictureGalleryFullFormat][int(row.ImageID.Int64)]; ok {
				resultRow.ImageGalleryFull = imagemapper.FromStorage(&image)
			}
		}

		if fields.GetImageGallery() && row.ImageID.Valid {
			if image, ok := formatted[pictureGalleryFormat][int(row.ImageID.Int64)]; ok {
				resultRow.ImageGallery = imagemapper.FromStorage(&image)
			}
		}

		if fields.GetPreviewLarge() && row.ImageID.Valid {
			if image, ok := formatted[picturePreviewLargeFormat][int(row.ImageID.Int64)]; ok {
				resultRow.PreviewLarge = imagemapper.FromStorage(&image)
			}
		}

		if fields.GetViews() {
			resultRow.Views = viewsByID[row.ID]
		}

		if fields.GetVotes() {
			if vote := votesByID[row.ID]; vote != nil {
				resultRow.Votes = &pb.PicturesVoteSummary{
					Value:    vote.Value,
					Positive: vote.Positive,
					Negative: vote.Negative,
				}
			}
		}

		if fields.GetCommentsCount() {
			stat := stats[row.ID]

			resultRow.CommentsCountTotal = stat.Messages
			resultRow.CommentsCountNew = stat.NewMessages
		}

		if fields.GetModerVote() {
			resultRow.ModerVoteCount = moderVotes[row.ID].Count
			resultRow.ModerVoteVote = moderVotes[row.ID].Vote
		}

		if paths != nil {
			resultRow.Path = paths[row.ID]
		}

		pictureItemRequest := fields.GetPictureItem()
		if pictureItemRequest != nil {
			resultRow.PictureItems = &pb.PictureItems{Items: pictureItems[row.ID]}
		}

		dfDistanceRequest := fields.GetDfDistance()
		if dfDistanceRequest != nil {
			group.Go(func() error {
				ddOptions, err := querymapper.DfDistanceListOptionsFromProto(dfDistanceRequest.GetOptions())
				if err != nil {
					return err
				}

				if ddOptions == nil {
					ddOptions = &query.DfDistanceListOptions{}
				}

				ddOptions.SrcPictureID = row.ID

				ddRows, err := picturesRepository.DfDistances(
					groupCtx,
					ddOptions,
					dfDistanceRequest.GetLimit(),
				)
				if err != nil {
					return err
				}

				res, err := dfDistanceExtractor.ExtractRows(
					groupCtx,
					ddRows,
					dfDistanceRequest.GetFields(),
					lang,
					userCtx,
				)
				if err != nil {
					return err
				}

				resultRow.DfDistances = &pb.DfDistances{
					Items: res,
				}

				return nil
			})
		}

		if fields.GetAcceptedCount() {
			resultRow.AcceptedCount = int32(acceptedStats[row.ID].Count) //nolint:gosec
		}

		if fields.GetCopyrights() && row.CopyrightsTextID.Valid {
			resultRow.Copyrights = copyrights[row.CopyrightsTextID.Int32]
		}

		if fields.GetAuthorSuggestions() &&
			(isModer || (userCtx.UserID != 0 && row.OwnerID.Valid && row.OwnerID.Int64 == userCtx.UserID)) {
			resultRow.AuthorSuggestions = authorSuggestions[row.ID]
		}

		if fields.GetExif() && row.ImageID.Valid {
			group.Go(func() error {
				exif, err := imageStorage.ImageEXIF(groupCtx, int(row.ImageID.Int64))
				if err != nil {
					return err
				}

				var exifStr strings.Builder

				skipSections := []string{"FILE", "COMPUTED"}

				if len(exif) > 0 {
					for key, section := range exif {
						if util.Contains(skipSections, key) {
							continue
						}

						exifStr.WriteString("<p>[" + html.EscapeString(key) + "]")

						var exifStrSb strings.Builder
						for name, val := range section {
							exifStrSb.WriteString("<br />" + html.EscapeString(
								fmt.Sprintf(
									"%s: %v",
									name, val,
								)),
							)
						}

						exifStrSb.WriteString("</p>")
					}
				}

				resultRow.Exif = exifStr.String()

				return nil
			})
		}

		if fields.GetIsLast() {
			resultRow.IsLast = row.Status == schema.PictureStatusAccepted && acceptedStats[row.ID].Others == 0
		}

		if fields.GetModerVoted() && userCtx.UserID != 0 {
			resultRow.ModerVoted = moderVotes[row.ID].Voted
		}

		pictureModerVoteRequest := fields.GetPictureModerVotes()
		if pictureModerVoteRequest != nil {
			resultRow.PictureModerVotes = &pb.PictureModerVotes{Items: moderVoteItems[row.ID]}
		}

		replaceableRequest := fields.GetReplaceable()
		if replaceableRequest != nil && row.ReplacePictureID.Valid {
			group.Go(func() error {
				pOptions, err := querymapper.PictureListOptionsFromProto(replaceableRequest.GetOptions())
				if err != nil {
					return err
				}

				if pOptions == nil {
					pOptions = &query.PictureListOptions{}
				}

				pOptions.ID = row.ReplacePictureID.Int64

				pFields := picturemapper.FieldsFromProto(replaceableRequest.GetFields())

				pRow, err := picturesRepository.Picture(groupCtx, pOptions, pFields, pictures.OrderByNone)
				if err != nil {
					return err
				}

				res, err := s.Extract(groupCtx, pRow, replaceableRequest.GetFields(), lang, userCtx)
				if err != nil {
					return err
				}

				resultRow.Replaceable = res

				return nil
			})
		}

		if fields.GetRights() {
			resultRow.Rights = &pb.PictureRights{
				Move: util.Contains(userCtx.Roles, users.RolePicturesModer),
				Unaccept: (row.Status == schema.PictureStatusAccepted) &&
					util.Contains(userCtx.Roles, users.RolePicturesModer),
				Accept: (row.Status == schema.PictureStatusInbox && rightsVotes[row.ID].Negative == 0) &&
					util.Contains(userCtx.Roles, users.RolePicturesModer),
				Restore: (row.Status == schema.PictureStatusRemoving) &&
					util.Contains(userCtx.Roles, users.RoleAdmin),
				Normalize: (row.Status == schema.PictureStatusInbox) &&
					util.Contains(userCtx.Roles, users.RolePicturesModer),
				Flop: (row.Status == schema.PictureStatusInbox) &&
					util.Contains(userCtx.Roles, users.RolePicturesModer),
				Crop:   util.Contains(userCtx.Roles, users.RolePicturesModer),
				Delete: row.Status == schema.PictureStatusInbox && rightsVotes[row.ID].Positive == 0,
			}
		}

		if fields.GetSiblings() != nil {
			resultRow.Siblings = siblingsByID[row.ID]
		}

		if row.IP.IsValid() && util.Contains(userCtx.Roles, users.RoleModer) {
			resultRow.IpAddress = row.IP.Addr().Unmap().String()
		}

		if fields.GetSubscribed() && userCtx.UserID > 0 {
			resultRow.Subscribed = subscribed[row.ID]
		}

		if fields.GetPaginator() != nil {
			group.Go(func() error {
				paginator, err := s.buildPicturesPaginator(groupCtx, row, fields.GetPaginator(), picturesRepository)
				if err != nil {
					return err
				}

				resultRow.Paginator = paginator

				return nil
			})
		}

		result = append(result, resultRow)
	}

	if err = group.Wait(); err != nil {
		return nil, err
	}

	return result, nil
}

// buildPicturesPaginator computes the sibling-pictures paginator for row
// within paginatorRequest's filter, or returns nil if no paginator was
// requested, the filter is empty, or the result set is too large to
// paginate cheaply (see maxPaginatorLength).
func (s *Extractor) buildPicturesPaginator(
	ctx context.Context,
	row *schema.PictureRow,
	paginatorRequest *pb.PicturesRequest,
	picturesRepository *pictures.Repository,
) (*pb.PicturesPages, error) {
	if paginatorRequest == nil {
		return nil, nil //nolint:nilnil
	}

	filter, err := querymapper.PictureListOptionsFromProto(paginatorRequest.GetOptions())
	if err != nil {
		return nil, err
	}

	if filter == nil {
		return nil, nil //nolint:nilnil
	}

	filter.Status = row.Status
	filter.Limit = 1
	orderBy := picturemapper.OrderFromProto(paginatorRequest.GetOrder())

	paginator, err := picturesRepository.PicturesPaginator(filter, nil, orderBy)
	if err != nil {
		return nil, err
	}

	total, err := paginator.GetTotalItemCount(ctx)
	if err != nil {
		return nil, err
	}

	if total >= maxPaginatorLength {
		return nil, nil //nolint:nilnil
	}

	filter.Limit = uint32(total) //nolint: gosec

	paginatorPictures, _, err := picturesRepository.Pictures(ctx, filter, nil, orderBy, false)
	if err != nil {
		return nil, err
	}

	var pageNumber int32

	for n, p := range paginatorPictures {
		if p.ID == row.ID {
			pageNumber = int32(n + 1)

			break
		}
	}

	paginator.CurrentPageNumber = pageNumber

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, err
	}

	// Wider than a generic list paginator's page-link strip would be: this is the only paginator
	// exposed as a strip of sibling *pictures* rather than page numbers, so a wider window reads
	// better here.
	const siblingPictureRange = 15

	first, last := int32(1), pages.PageCount

	var previous, next int32
	if pages.Current-1 > 0 {
		previous = pages.Current - 1
	}

	if pages.Current+1 <= pages.PageCount {
		next = pages.Current + 1
	}

	picturesPages := pb.PicturesPages{
		PageCount:      pages.PageCount,
		TotalItemCount: pages.TotalItemCount,
	}

	if previous > 0 {
		picturesPages.Previous = paginatorPictures[previous-1].Identity
	}

	if next > 0 {
		picturesPages.Next = paginatorPictures[next-1].Identity
	}

	if first > 0 {
		picturesPages.First = paginatorPictures[first-1].Identity
	}

	if last > 0 {
		picturesPages.Last = paginatorPictures[last-1].Identity
	}

	if pages.Current > 0 {
		picturesPages.Current = paginatorPictures[pages.Current-1].Identity
	}

	siblingPageNumbers := pageNumbersInRange(pages.PageCount, pages.Current, siblingPictureRange)

	pagesInRange := make([]*pb.PicturesPagesPage, 0, len(siblingPageNumbers))
	for _, i := range siblingPageNumbers {
		pagesInRange = append(pagesInRange, &pb.PicturesPagesPage{
			Page:     i,
			Identity: paginatorPictures[i-1].Identity,
		})
	}

	picturesPages.PagesInRange = pagesInRange

	return &picturesPages, nil
}

// pageNumbersInRange returns up to pageRange page numbers centered on current, clamped to
// [1, pageCount] - the page-link window shown around the current sibling picture.
func pageNumbersInRange(pageCount, current, pageRange int32) []int32 {
	if pageRange > pageCount {
		pageRange = pageCount
	}

	delta := int32(math.Ceil(float64(pageRange) / 2.0))

	lowerBound := pageCount - pageRange + 1
	upperBound := pageCount

	if current-delta <= pageCount-pageRange {
		if current-delta < 0 {
			delta = current
		}

		offset := current - delta
		lowerBound = offset + 1
		upperBound = offset + pageRange
	}

	lowerBound = clampPageNumber(lowerBound, pageCount)
	upperBound = clampPageNumber(upperBound, pageCount)

	pageNumbers := make([]int32, upperBound-lowerBound+1)
	for pageNumber := lowerBound; pageNumber <= upperBound; pageNumber++ {
		pageNumbers[pageNumber-lowerBound] = pageNumber
	}

	return pageNumbers
}

func clampPageNumber(pageNumber, pageCount int32) int32 {
	if pageNumber < 1 {
		pageNumber = 1
	}

	if pageCount > 0 && pageNumber > pageCount {
		pageNumber = pageCount
	}

	return pageNumber
}

func (s *Extractor) preloadTopicsStat(
	ctx context.Context, itemIDs []int64, userID int64,
) (map[int64]comments.TopicStat, error) {
	commentsRepository, err := s.dependencies.CommentsRepository(ctx)
	if err != nil {
		return nil, err
	}

	stats, err := commentsRepository.TopicsStatForUser(
		ctx,
		schema.CommentMessageTypeIDPictures,
		itemIDs,
		userID,
	)
	if err != nil {
		return nil, err
	}

	return stats, nil
}

// preloadAuthorSuggestions loads the stored EXIF-derived author candidates for visible pictures and
// resolves each to a localized Item for display in the upload grid.
func (s *Extractor) preloadAuthorSuggestions(
	ctx context.Context,
	picturesRepository *pictures.Repository,
	pictureIDs []int64,
	lang string,
	userCtx authn.User,
) (map[int64][]*pb.PictureAuthorSuggestion, error) {
	rows, err := picturesRepository.PictureAuthorSuggestionsBatch(ctx, pictureIDs)
	if err != nil {
		return nil, err
	}

	if len(rows) == 0 {
		return nil, nil //nolint:nilnil
	}

	itemsRepository, err := s.dependencies.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	idSet := make(map[int64]struct{}, len(rows))
	for _, row := range rows {
		idSet[row.ItemID] = struct{}{}
	}

	itemFields := &pb.ItemFields{NameHtml: true, NameText: true}

	itemRows, _, err := itemsRepository.List(
		ctx,
		&query.ItemListOptions{ItemIDs: slices.Collect(maps.Keys(idSet))},
		itemmapper.FieldsFromProto(itemFields),
		items.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	extractedItems, err := s.dependencies.ItemExtractor().ExtractRows(ctx, itemRows, itemFields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	itemByID := make(map[int64]*pb.Item, len(extractedItems))
	for _, item := range extractedItems {
		itemByID[item.GetId()] = item
	}

	suggestions := make(map[int64][]*pb.PictureAuthorSuggestion)
	for _, row := range rows {
		suggestions[row.PictureID] = append(suggestions[row.PictureID], &pb.PictureAuthorSuggestion{
			ItemId:   row.ItemID,
			Item:     itemByID[row.ItemID],
			Source:   row.Source,
			RawValue: row.RawValue,
		})
	}

	return suggestions, nil
}

// Item types whose parents continue a route. Anything else ends it.
var pathTreeParentItemTypes = []schema.ItemTableItemTypeID{
	schema.ItemTableItemTypeIDCategory,
	schema.ItemTableItemTypeIDEngine,
	schema.ItemTableItemTypeIDVehicle,
}

// pathTreeGraph is the slice of the item graph a picture's routes can reach: the items on them,
// and the edges to the parents of those whose type continues a route.
type pathTreeGraph struct {
	items   map[int64]*items.Item
	parents map[int64][]*items.ItemParent
}

// pictureImageFormatRequests maps each formatted variant the fields ask for to the images it is
// needed for. Only the gallery format is selective: it was only ever fetched for cropped images,
// and asking for it more widely would not just cost a lookup - it would generate variants that
// were never generated before.
func pictureImageFormatRequests(
	rows []*schema.PictureRow, fields *pb.PictureFields, images map[int]*storage.Image,
) map[string][]int {
	imageIDs := make([]int, 0, len(rows))
	croppedImageIDs := make([]int, 0, len(rows))
	seen := make(map[int]bool, len(rows))

	for _, row := range rows {
		if !row.ImageID.Valid {
			continue
		}

		imageID := int(row.ImageID.Int64)
		if seen[imageID] {
			continue
		}

		seen[imageID] = true

		imageIDs = append(imageIDs, imageID)

		if img, ok := images[imageID]; ok && img.CropHeight() > 0 && img.CropWidth() > 0 {
			croppedImageIDs = append(croppedImageIDs, imageID)
		}
	}

	if len(imageIDs) == 0 {
		return nil
	}

	requests := make(map[string][]int, pictureImageFormatCount)

	if fields.GetThumbMedium() {
		requests[imagemapper.ThumbMediumFormat] = imageIDs
	}

	if fields.GetThumbLarge() {
		requests[imagemapper.ThumbLargeFormat] = imageIDs
	}

	if fields.GetImageGalleryFull() {
		requests[pictureGalleryFullFormat] = imageIDs
	}

	if fields.GetPreviewLarge() {
		requests[picturePreviewLargeFormat] = imageIDs
	}

	if fields.GetImageGallery() && len(croppedImageIDs) > 0 {
		requests[pictureGalleryFormat] = croppedImageIDs
	}

	return requests
}

// preloadPaths builds the routes of every picture in one go: one query for the picture-items of
// all of them, one collection of the item graph they reach (see collectPathTree), and one builder
// shared between them - so a listing of two dozen pictures, which is what the catalogue asks for,
// costs what a single picture used to rather than two dozen times that. Pictures of the same item,
// or of items under a shared parent, now also share the nodes above them instead of each
// discovering them again.
func (s *Extractor) preloadPaths(
	ctx context.Context, rows []*schema.PictureRow, targetItemID int64,
) (map[int64][]*pb.PathTreePictureItem, error) {
	if len(rows) == 0 {
		return map[int64][]*pb.PathTreePictureItem{}, nil
	}

	picturesRepository, err := s.dependencies.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	pictureIDs := make([]int64, 0, len(rows))
	for _, row := range rows {
		pictureIDs = append(pictureIDs, row.ID)
	}

	piRows, err := picturesRepository.PictureItems(ctx, &query.PictureItemListOptions{
		PictureIDs: pictureIDs,
		TypeID:     schema.PictureItemTypeContent,
	}, pictures.PictureItemOrderByNone, 0)
	if err != nil {
		return nil, err
	}

	itemIDs := make([]int64, 0, len(piRows))
	for _, piRow := range piRows {
		itemIDs = append(itemIDs, piRow.ItemID)
	}

	graph, err := s.collectPathTree(ctx, itemIDs)
	if err != nil {
		return nil, err
	}

	builder := &pathTreeBuilder{
		graph:        graph,
		built:        make(map[int64]*pb.PathTreeItem),
		building:     make(map[int64]bool),
		targetItemID: targetItemID,
	}

	result := make(map[int64][]*pb.PathTreePictureItem, len(rows))

	for _, piRow := range piRows {
		item := builder.route(piRow.ItemID)

		if item != nil {
			result[piRow.PictureID] = append(result[piRow.PictureID], &pb.PathTreePictureItem{
				PerspectiveId: util.NullInt32ToScalar(piRow.PerspectiveID),
				Item:          item,
			})
		}
	}

	return result, nil
}

// collectPathTree walks the parent graph upwards a level at a time: two queries per level - the
// items of the whole level, then the parent edges of the whole level - rather than two per node.
//
// The routes it collects are then assembled in memory, without touching the database again. Walked
// node by node instead, as this used to be, the number of queries grew with the number of *routes*
// through the graph rather than with its size: an item reachable through several parents had its
// whole subtree re-walked once per route reaching it, on a call production logs showed at 10-14s.
func (s *Extractor) collectPathTree(ctx context.Context, itemIDs []int64) (*pathTreeGraph, error) {
	itemsRepository, err := s.dependencies.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	graph := &pathTreeGraph{
		items:   make(map[int64]*items.Item),
		parents: make(map[int64][]*items.ItemParent),
	}

	// Every id ever queued, including the ones no item came back for: without it a deleted parent
	// would be asked for on every level that references it, forever.
	seen := make(map[int64]bool, len(itemIDs))
	frontier := make([]int64, 0, len(itemIDs))

	queue := func(ids ...int64) {
		for _, id := range ids {
			if id > 0 && !seen[id] {
				seen[id] = true

				frontier = append(frontier, id)
			}
		}
	}

	queue(itemIDs...)

	for len(frontier) > 0 {
		rows, _, err := itemsRepository.List(
			ctx, &query.ItemListOptions{ItemIDs: frontier}, nil, items.OrderByNone, false,
		)
		if err != nil {
			return nil, err
		}

		expandable := make([]int64, 0, len(rows))

		for _, row := range rows {
			graph.items[row.ID] = row

			if util.Contains(pathTreeParentItemTypes, row.ItemTypeID) {
				expandable = append(expandable, row.ID)
			}
		}

		frontier = frontier[:0]

		if len(expandable) == 0 {
			break
		}

		parentRows, _, err := itemsRepository.ItemParents(
			ctx, &query.ItemParentListOptions{ItemIDs: expandable}, items.ItemParentFields{},
			items.ItemParentOrderByNone,
		)
		if err != nil {
			return nil, err
		}

		for _, parentRow := range parentRows {
			graph.parents[parentRow.ItemID] = append(graph.parents[parentRow.ItemID], parentRow)

			queue(parentRow.ParentID)
		}
	}

	return graph, nil
}

// pathTreeBuilder assembles the response tree out of a collected graph, memoized per item: a node
// several routes lead to is built once and shared between them instead of expanded again. The tree
// that comes out is the same one node-by-node walking produced.
type pathTreeBuilder struct {
	graph        *pathTreeGraph
	built        map[int64]*pb.PathTreeItem
	building     map[int64]bool
	targetItemID int64
}

func (s *pathTreeBuilder) route(itemID int64) *pb.PathTreeItem {
	if built, ok := s.built[itemID]; ok {
		return built
	}

	// A cycle in item_parent would otherwise recurse until the stack runs out - which the previous
	// implementation would have done too, one query per step. The node that closes the cycle keeps
	// (and caches) the truncated parents it was built with; on data that has no cycles, which is
	// what item_parent is meant to hold, this never comes up.
	if s.building[itemID] {
		return nil
	}

	row, ok := s.graph.items[itemID]
	if !ok {
		// No such item any more: the route through it is dropped, as it was before.
		return nil
	}

	s.building[itemID] = true
	defer delete(s.building, itemID)

	parents := make([]*pb.PathTreeItemParent, 0, len(s.graph.parents[itemID]))

	for _, parentRow := range s.graph.parents[itemID] {
		item := s.route(parentRow.ParentID)

		if item != nil {
			parents = append(parents, &pb.PathTreeItemParent{
				Catname: parentRow.Catname,
				Item:    item,
			})
		}
	}

	// A route that reaches neither the item asked about nor anything above it is not a route to it.
	if len(parents) == 0 && s.targetItemID != 0 && itemID != s.targetItemID {
		return nil
	}

	result := &pb.PathTreeItem{
		ItemTypeId: itemmapper.TypeToProto(row.ItemTypeID),
		Catname:    util.NullStringToString(row.Catname),
		Parents:    parents,
	}

	s.built[itemID] = result

	return result
}
