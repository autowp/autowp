package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"html"
	"math"
	"strings"

	"github.com/autowp/goautowp/comments"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/jackc/pgtype"
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
	pictureThumbLargeFormat   = "picture-thumb-large"
	pictureThumbMediumFormat  = "picture-thumb-medium"

	// How many of them there are, for sizing the maps keyed by format.
	pictureImageFormatCount = 5
)

// How many of a row's independent lookups may be in flight at once, across the whole batch being
// extracted. Kept well under the Postgres pool (30 connections per pod in the chart) because a pod
// runs several renders at a time and each of them can be extracting pictures.
const pictureExtractorParallelism = 4

var errItemNotFound = errors.New("item not found")

type PictureExtractor struct {
	container *Container
}

func NewPictureExtractor(container *Container) *PictureExtractor {
	return &PictureExtractor{container: container}
}

func (s *PictureExtractor) Extract(
	ctx context.Context,
	row *schema.PictureRow,
	fields *PictureFields,
	lang string,
	userCtx UserContext,
) (*Picture, error) {
	result, err := s.ExtractRows(ctx, []*schema.PictureRow{row}, fields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	if len(result) == 0 {
		return nil, errItemNotFound
	}

	return result[0], nil
}

func (s *PictureExtractor) ExtractRows( //nolint: maintidx
	ctx context.Context,
	rows []*schema.PictureRow,
	fields *PictureFields,
	lang string,
	userCtx UserContext,
) ([]*Picture, error) {
	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	if fields == nil {
		fields = &PictureFields{}
	}

	var (
		namesData map[int64]pictures.PictureNameFormatterOptions
		err       error
		result    = make([]*Picture, 0, len(rows))
		images    = make(map[int]*storage.Image)
	)

	picturesRepository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	i18nBundle, err := s.container.I18n()
	if err != nil {
		return nil, err
	}

	imageStorage, err := s.container.ImageStorage(ctx)
	if err != nil {
		return nil, err
	}

	textstorageRepository, err := s.container.TextStorageRepository(ctx)
	if err != nil {
		return nil, err
	}

	var stats map[int64]comments.TopicStat

	if fields.GetCommentsCount() {
		itemIDs := make([]int64, 0, len(rows))
		for _, row := range rows {
			itemIDs = append(itemIDs, row.ID)
		}

		stats, err = s.preloadTopicsStat(ctx, itemIDs, userCtx.UserID)
		if err != nil {
			return nil, err
		}
	}

	if fields.GetNameText() || fields.GetNameHtml() {
		namesData, err = picturesRepository.NameData(ctx, rows, pictures.NameDataOptions{
			Language: lang,
		})
		if err != nil {
			return nil, err
		}
	}

	if fields.GetImage() || fields.GetImageGallery() || isModer {
		ids := make([]int, 0, len(rows))

		for _, row := range rows {
			if row.ImageID.Valid {
				ids = append(ids, int(row.ImageID.Int64))
			}
		}

		images, err = imageStorage.Images(ctx, ids)
		if err != nil {
			return nil, err
		}
	}

	// Formatted variants for the whole batch: one query per format asked for, rather than one per
	// format per row - a gallery of two dozen pictures used to make two dozen of them. It is the
	// same call the per-row code made, only plural: FormattedImages looks the format up for a set
	// of images and generates the ones that are missing, exactly as FormattedImage does for one.
	formatted := make(map[string]map[int]storage.Image, pictureImageFormatCount)

	if formatRequests := pictureImageFormatRequests(rows, fields, images); len(formatRequests) > 0 {
		for formatName, ids := range formatRequests {
			formatted[formatName], err = imageStorage.FormattedImages(ctx, ids, formatName)
			if err != nil {
				return nil, err
			}
		}
	}

	var pictureItemExtractor *PictureItemExtractor
	if fields.GetPictureItem() != nil {
		pictureItemExtractor = s.container.PictureItemExtractor()
	}

	var dfDistanceExtractor *DfDistanceExtractor
	if fields.GetDfDistance() != nil {
		dfDistanceExtractor = s.container.DfDistanceExtractor()
	}

	var commentsRepository *comments.Repository

	if fields.GetSubscribed() && userCtx.UserID > 0 {
		commentsRepository, err = s.container.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}
	}

	var paths map[int64][]*PathTreePictureItem

	if pathRequest := fields.GetPath(); pathRequest != nil {
		paths, err = s.preloadPaths(ctx, rows, pathRequest.GetParentId())
		if err != nil {
			return nil, err
		}
	}

	// Everything a row needs beyond what was preloaded above is independent of everything else it
	// needs - a vote count knows nothing about a thumbnail - and all of it is a round trip to
	// Postgres, to text storage or to S3. Run sequentially, as this used to be, a picture page's
	// worth of them added up: copyrights, votes, moderator votes, subscription, the replaced
	// picture and its own extraction, one after another. They now go out together, bounded so a
	// listing of two dozen pictures cannot put a multiple of that on the connection pool at once.
	//
	// Each task writes its own field of its own row, and reads only what was preloaded before the
	// loop, so no two of them touch the same memory.
	group, groupCtx := errgroup.WithContext(ctx)
	group.SetLimit(pictureExtractorParallelism)

	for _, row := range rows {
		resultRow := &Picture{
			Id:               row.ID,
			Identity:         row.Identity,
			Width:            uint32(row.Width),
			Height:           uint32(row.Height),
			CopyrightsTextId: util.NullInt32ToScalar(row.CopyrightsTextID),
			OwnerId:          util.NullInt64ToScalar(row.OwnerID),
			Status:           extractPicturesStatus(row.Status),
			Resolution:       fmt.Sprintf("%d×%d", row.Width, row.Height),
			CreateTime:       timestamppb.New(row.CreatedAt),
			TakenDate: &date.Date{
				Year:  int32(util.NullInt16ToScalar(row.TakenYear)),
				Month: int32(util.NullByteToScalar(row.TakenMonth)),
				Day:   int32(util.NullByteToScalar(row.TakenDay)),
			},
			DpiX: util.NullInt32ToScalar(row.DPIX),
			DpiY: util.NullInt32ToScalar(row.DPIY),
		}

		if row.ChangeStatusUserID.Valid {
			resultRow.ChangeStatusUserId = row.ChangeStatusUserID.Int64
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
				resultRow.Image = APIImageToGRPC(image)
			}
		}

		if fields.GetThumbMedium() && row.ImageID.Valid {
			if image, ok := formatted[pictureThumbMediumFormat][int(row.ImageID.Int64)]; ok {
				resultRow.ThumbMedium = APIImageToGRPC(&image)
			}
		}

		if fields.GetThumbLarge() && row.ImageID.Valid {
			if image, ok := formatted[pictureThumbLargeFormat][int(row.ImageID.Int64)]; ok {
				resultRow.ThumbLarge = APIImageToGRPC(&image)
			}
		}

		if fields.GetImageGalleryFull() && row.ImageID.Valid {
			if image, ok := formatted[pictureGalleryFullFormat][int(row.ImageID.Int64)]; ok {
				resultRow.ImageGalleryFull = APIImageToGRPC(&image)
			}
		}

		if fields.GetImageGallery() && row.ImageID.Valid {
			if image, ok := formatted[pictureGalleryFormat][int(row.ImageID.Int64)]; ok {
				resultRow.ImageGallery = APIImageToGRPC(&image)
			}
		}

		if fields.GetPreviewLarge() && row.ImageID.Valid {
			if image, ok := formatted[picturePreviewLargeFormat][int(row.ImageID.Int64)]; ok {
				resultRow.PreviewLarge = APIImageToGRPC(&image)
			}
		}

		if fields.GetViews() {
			group.Go(func() error {
				views, err := picturesRepository.PictureViews(groupCtx, row.ID)
				if err != nil {
					return err
				}

				resultRow.Views = views

				return nil
			})
		}

		if fields.GetVotes() {
			group.Go(func() error {
				vote, err := picturesRepository.GetVote(groupCtx, row.ID, userCtx.UserID)
				if err != nil {
					return err
				}

				resultRow.Votes = &PicturesVoteSummary{
					Value:    vote.Value,
					Positive: vote.Positive,
					Negative: vote.Negative,
				}

				return nil
			})
		}

		if fields.GetCommentsCount() {
			stat := stats[row.ID]

			resultRow.CommentsCountTotal = stat.Messages
			resultRow.CommentsCountNew = stat.NewMessages
		}

		if fields.GetModerVote() {
			group.Go(func() error {
				count, sum, err := picturesRepository.ModerVoteCount(groupCtx, row.ID)
				if err != nil {
					return err
				}

				resultRow.ModerVoteCount = count
				resultRow.ModerVoteVote = sum

				return nil
			})
		}

		if paths != nil {
			resultRow.Path = paths[row.ID]
		}

		pictureItemRequest := fields.GetPictureItem()
		if pictureItemRequest != nil {
			group.Go(func() error {
				piOptions, err := convertPictureItemListOptions(pictureItemRequest.GetOptions())
				if err != nil {
					return err
				}

				if piOptions == nil {
					piOptions = &query.PictureItemListOptions{}
				}

				piOptions.PictureID = row.ID

				order := convertPictureItemsOrder(pictureItemRequest.GetOrder())

				piRows, err := picturesRepository.PictureItems(groupCtx, piOptions, order, 0)
				if err != nil {
					return err
				}

				res, err := pictureItemExtractor.ExtractRows(
					groupCtx,
					piRows,
					pictureItemRequest.GetFields(),
					lang,
					userCtx,
				)
				if err != nil {
					return err
				}

				resultRow.PictureItems = &PictureItems{
					Items: res,
				}

				return nil
			})
		}

		dfDistanceRequest := fields.GetDfDistance()
		if dfDistanceRequest != nil {
			group.Go(func() error {
				ddOptions, err := convertDfDistanceListOptions(dfDistanceRequest.GetOptions())
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

				resultRow.DfDistances = &DfDistances{
					Items: res,
				}

				return nil
			})
		}

		if fields.GetAcceptedCount() {
			group.Go(func() error {
				acceptedCount, err := picturesRepository.Count(groupCtx, &query.PictureListOptions{
					Status: schema.PictureStatusAccepted,
					PictureItem: &query.PictureItemListOptions{
						PictureItemByItemID: &query.PictureItemListOptions{
							PictureID: row.ID,
						},
					},
				})
				if err != nil {
					return err
				}

				resultRow.AcceptedCount = int32(acceptedCount) //nolint: gosec

				return nil
			})
		}

		if fields.GetCopyrights() {
			group.Go(func() error {
				if row.CopyrightsTextID.Valid {
					copyrights, err := textstorageRepository.Text(groupCtx, row.CopyrightsTextID.Int32)
					if err != nil && !errors.Is(err, textstorage.ErrTextNotFound) {
						return err
					}

					if err == nil {
						resultRow.Copyrights = copyrights
					}
				}

				return nil
			})
		}

		if fields.GetAuthorSuggestions() &&
			(isModer || (userCtx.UserID != 0 && row.OwnerID.Valid && row.OwnerID.Int64 == userCtx.UserID)) {
			group.Go(func() error {
				suggestions, err := s.extractAuthorSuggestions(groupCtx, picturesRepository, row.ID, lang, userCtx)
				if err != nil {
					return err
				}

				resultRow.AuthorSuggestions = suggestions

				return nil
			})
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
			group.Go(func() error {
				hasOtherPicture := true

				if row.Status == schema.PictureStatusAccepted {
					exists, err := picturesRepository.Exists(groupCtx, &query.PictureListOptions{
						ExcludeID: row.ID,
						Status:    schema.PictureStatusAccepted,
						PictureItem: &query.PictureItemListOptions{
							PictureItemByItemID: &query.PictureItemListOptions{
								PictureID: row.ID,
							},
						},
					})
					if err != nil {
						return err
					}

					hasOtherPicture = exists
				}

				resultRow.IsLast = !hasOtherPicture

				return nil
			})
		}

		if fields.GetModerVoted() && userCtx.UserID != 0 {
			group.Go(func() error {
				moderVoted, err := picturesRepository.HasModerVote(groupCtx, row.ID, userCtx.UserID)
				if err != nil {
					return err
				}

				resultRow.ModerVoted = moderVoted

				return nil
			})
		}

		pictureModerVoteRequest := fields.GetPictureModerVotes()
		if pictureModerVoteRequest != nil {
			group.Go(func() error {
				pmvOptions := convertPictureModerVoteListOptions(pictureModerVoteRequest.GetOptions())
				if pmvOptions == nil {
					pmvOptions = &query.PictureModerVoteListOptions{}
				}

				pmvOptions.PictureID = row.ID

				pmvRows, err := picturesRepository.PictureModerVotes(groupCtx, pmvOptions)
				if err != nil {
					return err
				}

				pmvExtractor := NewPictureModerVoteExtractor()

				res, err := pmvExtractor.ExtractRows(pmvRows)
				if err != nil {
					return err
				}

				resultRow.PictureModerVotes = &PictureModerVotes{
					Items: res,
				}

				return nil
			})
		}

		replaceableRequest := fields.GetReplaceable()
		if replaceableRequest != nil && row.ReplacePictureID.Valid {
			group.Go(func() error {
				pOptions, err := convertPictureListOptions(replaceableRequest.GetOptions())
				if err != nil {
					return err
				}

				if pOptions == nil {
					pOptions = &query.PictureListOptions{}
				}

				pOptions.ID = row.ReplacePictureID.Int64

				pFields := convertPictureFields(replaceableRequest.GetFields())

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
			group.Go(func() error {
				canAccept, err := picturesRepository.CanAccept(groupCtx, row)
				if err != nil {
					return err
				}

				canDelete, err := picturesRepository.CanDelete(groupCtx, row)
				if err != nil {
					return err
				}

				resultRow.Rights = &PictureRights{
					Move: util.Contains(userCtx.Roles, users.RolePicturesModer),
					Unaccept: (row.Status == schema.PictureStatusAccepted) &&
						util.Contains(userCtx.Roles, users.RolePicturesModer),
					Accept: canAccept && util.Contains(userCtx.Roles, users.RolePicturesModer),
					Restore: (row.Status == schema.PictureStatusRemoving) &&
						util.Contains(userCtx.Roles, users.RoleAdmin),
					Normalize: (row.Status == schema.PictureStatusInbox) &&
						util.Contains(userCtx.Roles, users.RolePicturesModer),
					Flop: (row.Status == schema.PictureStatusInbox) &&
						util.Contains(userCtx.Roles, users.RolePicturesModer),
					Crop:   util.Contains(userCtx.Roles, users.RolePicturesModer),
					Delete: canDelete,
				}

				return nil
			})
		}

		siblings := fields.GetSiblings()
		if siblings != nil {
			group.Go(func() error {
				resultRow.Siblings = &PictureSiblings{
					Prev:    nil,
					Next:    nil,
					PrevNew: nil,
					NextNew: nil,
				}

				sFields := siblings.GetFields()
				scFields := convertPictureFields(sFields)

				prevPicture, err := picturesRepository.Picture(groupCtx, &query.PictureListOptions{
					IDLt: row.ID,
				}, scFields, pictures.OrderByIDDesc)
				if err != nil && !errors.Is(err, sql.ErrNoRows) {
					return err
				}

				if err == nil {
					resultRow.Siblings.Prev, err = s.Extract(groupCtx, prevPicture, sFields, lang, userCtx)
					if err != nil {
						return err
					}
				}

				nextPicture, err := picturesRepository.Picture(groupCtx, &query.PictureListOptions{
					IDGt: row.ID,
				}, scFields, pictures.OrderByIDAsc)
				if err != nil && !errors.Is(err, sql.ErrNoRows) {
					return err
				}

				if err == nil {
					resultRow.Siblings.Next, err = s.Extract(groupCtx, nextPicture, sFields, lang, userCtx)
					if err != nil {
						return err
					}
				}

				prevNewPicture, err := picturesRepository.Picture(groupCtx, &query.PictureListOptions{
					IDLt:   row.ID,
					Status: schema.PictureStatusInbox,
				}, scFields, pictures.OrderByIDDesc)
				if err != nil && !errors.Is(err, sql.ErrNoRows) {
					return err
				}

				if err == nil {
					resultRow.Siblings.PrevNew, err = s.Extract(
						groupCtx,
						prevNewPicture,
						sFields,
						lang,
						userCtx,
					)
					if err != nil {
						return err
					}
				}

				nextNewPicture, err := picturesRepository.Picture(groupCtx, &query.PictureListOptions{
					IDGt:   row.ID,
					Status: schema.PictureStatusInbox,
				}, scFields, pictures.OrderByIDAsc)
				if err != nil && !errors.Is(err, sql.ErrNoRows) {
					return err
				}

				if err == nil {
					resultRow.Siblings.NextNew, err = s.Extract(
						groupCtx,
						nextNewPicture,
						sFields,
						lang,
						userCtx,
					)
					if err != nil {
						return err
					}
				}

				return nil
			})
		}

		if row.IP.Status == pgtype.Present && row.IP.IPNet != nil && util.Contains(userCtx.Roles, users.RoleModer) {
			resultRow.IpAddress = row.IP.IPNet.IP.String()
		}

		if fields.GetSubscribed() && userCtx.UserID > 0 {
			group.Go(func() error {
				subscribed, err := commentsRepository.IsSubscribed(groupCtx, userCtx.UserID,
					schema.CommentMessageTypeIDPictures, row.ID)
				if err != nil {
					return err
				}

				resultRow.Subscribed = subscribed

				return nil
			})
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
func (s *PictureExtractor) buildPicturesPaginator(
	ctx context.Context,
	row *schema.PictureRow,
	paginatorRequest *PicturesRequest,
	picturesRepository *pictures.Repository,
) (*PicturesPages, error) {
	if paginatorRequest == nil {
		return nil, nil //nolint:nilnil
	}

	filter, err := convertPictureListOptions(paginatorRequest.GetOptions())
	if err != nil {
		return nil, err
	}

	if filter == nil {
		return nil, nil //nolint:nilnil
	}

	filter.Status = row.Status
	filter.Limit = 1
	orderBy := convertPicturesOrder(paginatorRequest.GetOrder())

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

	picturesPages := PicturesPages{
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

	pagesInRange := make([]*PicturesPagesPage, 0, len(siblingPageNumbers))
	for _, i := range siblingPageNumbers {
		pagesInRange = append(pagesInRange, &PicturesPagesPage{
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

func (s *PictureExtractor) preloadTopicsStat(
	ctx context.Context, itemIDs []int64, userID int64,
) (map[int64]comments.TopicStat, error) {
	commentsRepository, err := s.container.CommentsRepository(ctx)
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

// extractAuthorSuggestions loads the stored EXIF-derived author candidates for a picture and
// resolves each to a localized Item for display in the upload grid.
func (s *PictureExtractor) extractAuthorSuggestions(
	ctx context.Context,
	picturesRepository *pictures.Repository,
	pictureID int64,
	lang string,
	userCtx UserContext,
) ([]*PictureAuthorSuggestion, error) {
	rows, err := picturesRepository.PictureAuthorSuggestions(ctx, pictureID)
	if err != nil {
		return nil, err
	}

	if len(rows) == 0 {
		return nil, nil
	}

	itemsRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	ids := make([]int64, 0, len(rows))
	for _, row := range rows {
		ids = append(ids, row.ItemID)
	}

	itemFields := &ItemFields{NameHtml: true, NameText: true}

	itemRows, _, err := itemsRepository.List(
		ctx,
		&query.ItemListOptions{ItemIDs: ids},
		convertItemFields(itemFields),
		items.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	extractedItems, err := s.container.ItemExtractor().ExtractRows(ctx, itemRows, itemFields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	itemByID := make(map[int64]*Item, len(extractedItems))
	for _, item := range extractedItems {
		itemByID[item.GetId()] = item
	}

	suggestions := make([]*PictureAuthorSuggestion, 0, len(rows))
	for _, row := range rows {
		suggestions = append(suggestions, &PictureAuthorSuggestion{
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
	rows []*schema.PictureRow, fields *PictureFields, images map[int]*storage.Image,
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
		requests[pictureThumbMediumFormat] = imageIDs
	}

	if fields.GetThumbLarge() {
		requests[pictureThumbLargeFormat] = imageIDs
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
func (s *PictureExtractor) preloadPaths(
	ctx context.Context, rows []*schema.PictureRow, targetItemID int64,
) (map[int64][]*PathTreePictureItem, error) {
	picturesRepository, err := s.container.PicturesRepository(ctx)
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
		built:        make(map[int64]*PathTreeItem),
		building:     make(map[int64]bool),
		targetItemID: targetItemID,
	}

	result := make(map[int64][]*PathTreePictureItem, len(rows))

	for _, piRow := range piRows {
		item := builder.route(piRow.ItemID)

		if item != nil {
			result[piRow.PictureID] = append(result[piRow.PictureID], &PathTreePictureItem{
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
func (s *PictureExtractor) collectPathTree(ctx context.Context, itemIDs []int64) (*pathTreeGraph, error) {
	itemsRepository, err := s.container.ItemsRepository(ctx)
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
	built        map[int64]*PathTreeItem
	building     map[int64]bool
	targetItemID int64
}

func (s *pathTreeBuilder) route(itemID int64) *PathTreeItem {
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

	parents := make([]*PathTreeItemParent, 0, len(s.graph.parents[itemID]))

	for _, parentRow := range s.graph.parents[itemID] {
		item := s.route(parentRow.ParentID)

		if item != nil {
			parents = append(parents, &PathTreeItemParent{
				Catname: parentRow.Catname,
				Item:    item,
			})
		}
	}

	// A route that reaches neither the item asked about nor anything above it is not a route to it.
	if len(parents) == 0 && s.targetItemID != 0 && itemID != s.targetItemID {
		return nil
	}

	result := &PathTreeItem{
		ItemTypeId: extractItemTypeID(row.ItemTypeID),
		Catname:    util.NullStringToString(row.Catname),
		Parents:    parents,
	}

	s.built[itemID] = result

	return result
}
