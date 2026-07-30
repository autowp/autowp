package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"html"
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
	"google.golang.org/genproto/googleapis/type/date"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const maxPaginatorLength = 500

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
			image, err := imageStorage.FormattedImage(
				ctx,
				int(row.ImageID.Int64),
				"picture-thumb-medium",
			)
			if err != nil {
				return nil, err
			}

			resultRow.ThumbMedium = APIImageToGRPC(image)
		}

		if fields.GetThumbLarge() && row.ImageID.Valid {
			image, err := imageStorage.FormattedImage(
				ctx,
				int(row.ImageID.Int64),
				"picture-thumb-large",
			)
			if err != nil {
				return nil, err
			}

			resultRow.ThumbLarge = APIImageToGRPC(image)
		}

		if fields.GetImageGalleryFull() && row.ImageID.Valid {
			image, err := imageStorage.FormattedImage(
				ctx,
				int(row.ImageID.Int64),
				"picture-gallery-full",
			)
			if err != nil {
				return nil, err
			}

			resultRow.ImageGalleryFull = APIImageToGRPC(image)
		}

		if fields.GetImageGallery() && row.ImageID.Valid {
			if img, ok := images[int(row.ImageID.Int64)]; ok && img.CropHeight() > 0 &&
				img.CropWidth() > 0 {
				image, err := imageStorage.FormattedImage(
					ctx,
					int(row.ImageID.Int64),
					"picture-gallery",
				)
				if err != nil {
					return nil, err
				}

				resultRow.ImageGallery = APIImageToGRPC(image)
			}
		}

		if fields.GetPreviewLarge() && row.ImageID.Valid {
			image, err := imageStorage.FormattedImage(
				ctx,
				int(row.ImageID.Int64),
				"picture-preview-large",
			)
			if err != nil {
				return nil, err
			}

			resultRow.PreviewLarge = APIImageToGRPC(image)
		}

		if fields.GetViews() {
			resultRow.Views, err = picturesRepository.PictureViews(ctx, row.ID)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetVotes() {
			vote, err := picturesRepository.GetVote(ctx, row.ID, userCtx.UserID)
			if err != nil {
				return nil, err
			}

			resultRow.Votes = &PicturesVoteSummary{
				Value:    vote.Value,
				Positive: vote.Positive,
				Negative: vote.Negative,
			}
		}

		if fields.GetCommentsCount() {
			stat := stats[row.ID]

			resultRow.CommentsCountTotal = stat.Messages
			resultRow.CommentsCountNew = stat.NewMessages
		}

		if fields.GetModerVote() {
			count, sum, err := picturesRepository.ModerVoteCount(ctx, row.ID)
			if err != nil {
				return nil, err
			}

			resultRow.ModerVoteCount = count
			resultRow.ModerVoteVote = sum
		}

		path := fields.GetPath()
		if path != nil {
			resultRow.Path, err = s.path(ctx, row.ID, path.GetParentId())
			if err != nil {
				return nil, err
			}
		}

		pictureItemRequest := fields.GetPictureItem()
		if pictureItemRequest != nil {
			piOptions, err := convertPictureItemListOptions(pictureItemRequest.GetOptions())
			if err != nil {
				return nil, err
			}

			if piOptions == nil {
				piOptions = &query.PictureItemListOptions{}
			}

			piOptions.PictureID = row.ID

			order := convertPictureItemsOrder(pictureItemRequest.GetOrder())

			piRows, err := picturesRepository.PictureItems(ctx, piOptions, order, 0)
			if err != nil {
				return nil, err
			}

			extractor := s.container.PictureItemExtractor()

			res, err := extractor.ExtractRows(
				ctx,
				piRows,
				pictureItemRequest.GetFields(),
				lang,
				userCtx,
			)
			if err != nil {
				return nil, err
			}

			resultRow.PictureItems = &PictureItems{
				Items: res,
			}
		}

		dfDistanceRequest := fields.GetDfDistance()
		if dfDistanceRequest != nil {
			ddOptions, err := convertDfDistanceListOptions(dfDistanceRequest.GetOptions())
			if err != nil {
				return nil, err
			}

			if ddOptions == nil {
				ddOptions = &query.DfDistanceListOptions{}
			}

			ddOptions.SrcPictureID = row.ID

			ddRows, err := picturesRepository.DfDistances(
				ctx,
				ddOptions,
				dfDistanceRequest.GetLimit(),
			)
			if err != nil {
				return nil, err
			}

			dfDistanceExtractor := s.container.DfDistanceExtractor()

			res, err := dfDistanceExtractor.ExtractRows(
				ctx,
				ddRows,
				dfDistanceRequest.GetFields(),
				lang,
				userCtx,
			)
			if err != nil {
				return nil, err
			}

			resultRow.DfDistances = &DfDistances{
				Items: res,
			}
		}

		if fields.GetAcceptedCount() {
			acceptedCount, err := picturesRepository.Count(ctx, &query.PictureListOptions{
				Status: schema.PictureStatusAccepted,
				PictureItem: &query.PictureItemListOptions{
					PictureItemByItemID: &query.PictureItemListOptions{
						PictureID: row.ID,
					},
				},
			})
			if err != nil {
				return nil, err
			}

			resultRow.AcceptedCount = int32(acceptedCount) //nolint: gosec
		}

		if fields.GetCopyrights() {
			if row.CopyrightsTextID.Valid {
				copyrights, err := textstorageRepository.Text(ctx, row.CopyrightsTextID.Int32)
				if err != nil && !errors.Is(err, textstorage.ErrTextNotFound) {
					return nil, err
				}

				if err == nil {
					resultRow.Copyrights = copyrights
				}
			}
		}

		if fields.GetExif() && row.ImageID.Valid {
			exif, err := imageStorage.ImageEXIF(ctx, int(row.ImageID.Int64))
			if err != nil {
				return nil, err
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
		}

		if fields.GetIsLast() {
			hasOtherPicture := true

			if row.Status == schema.PictureStatusAccepted {
				hasOtherPicture, err = picturesRepository.Exists(ctx, &query.PictureListOptions{
					ExcludeID: row.ID,
					Status:    schema.PictureStatusAccepted,
					PictureItem: &query.PictureItemListOptions{
						PictureItemByItemID: &query.PictureItemListOptions{
							PictureID: row.ID,
						},
					},
				})
				if err != nil {
					return nil, err
				}
			}

			resultRow.IsLast = !hasOtherPicture
		}

		if fields.GetModerVoted() && userCtx.UserID != 0 {
			resultRow.ModerVoted, err = picturesRepository.HasModerVote(ctx, row.ID, userCtx.UserID)
			if err != nil {
				return nil, err
			}
		}

		pictureModerVoteRequest := fields.GetPictureModerVotes()
		if pictureModerVoteRequest != nil {
			pmvOptions := convertPictureModerVoteListOptions(pictureModerVoteRequest.GetOptions())
			if pmvOptions == nil {
				pmvOptions = &query.PictureModerVoteListOptions{}
			}

			pmvOptions.PictureID = row.ID

			pmvRows, err := picturesRepository.PictureModerVotes(ctx, pmvOptions)
			if err != nil {
				return nil, err
			}

			pmvExtractor := NewPictureModerVoteExtractor()

			res, err := pmvExtractor.ExtractRows(pmvRows)
			if err != nil {
				return nil, err
			}

			resultRow.PictureModerVotes = &PictureModerVotes{
				Items: res,
			}
		}

		replaceableRequest := fields.GetReplaceable()
		if replaceableRequest != nil && row.ReplacePictureID.Valid {
			pOptions, err := convertPictureListOptions(replaceableRequest.GetOptions())
			if err != nil {
				return nil, err
			}

			if pOptions == nil {
				pOptions = &query.PictureListOptions{}
			}

			pOptions.ID = row.ReplacePictureID.Int64

			pFields := convertPictureFields(replaceableRequest.GetFields())

			pRow, err := picturesRepository.Picture(ctx, pOptions, pFields, pictures.OrderByNone)
			if err != nil {
				return nil, err
			}

			res, err := s.Extract(ctx, pRow, replaceableRequest.GetFields(), lang, userCtx)
			if err != nil {
				return nil, err
			}

			resultRow.Replaceable = res
		}

		if fields.GetRights() {
			canAccept, err := picturesRepository.CanAccept(ctx, row)
			if err != nil {
				return nil, err
			}

			canDelete, err := picturesRepository.CanDelete(ctx, row)
			if err != nil {
				return nil, err
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
		}

		siblings := fields.GetSiblings()
		if siblings != nil {
			resultRow.Siblings = &PictureSiblings{
				Prev:    nil,
				Next:    nil,
				PrevNew: nil,
				NextNew: nil,
			}

			sFields := siblings.GetFields()
			scFields := convertPictureFields(sFields)

			prevPicture, err := picturesRepository.Picture(ctx, &query.PictureListOptions{
				IDLt: row.ID,
			}, scFields, pictures.OrderByIDDesc)
			if err != nil && !errors.Is(err, sql.ErrNoRows) {
				return nil, err
			}

			if err == nil {
				resultRow.Siblings.Prev, err = s.Extract(ctx, prevPicture, sFields, lang, userCtx)
				if err != nil {
					return nil, err
				}
			}

			nextPicture, err := picturesRepository.Picture(ctx, &query.PictureListOptions{
				IDGt: row.ID,
			}, scFields, pictures.OrderByIDAsc)
			if err != nil && !errors.Is(err, sql.ErrNoRows) {
				return nil, err
			}

			if err == nil {
				resultRow.Siblings.Next, err = s.Extract(ctx, nextPicture, sFields, lang, userCtx)
				if err != nil {
					return nil, err
				}
			}

			prevNewPicture, err := picturesRepository.Picture(ctx, &query.PictureListOptions{
				IDLt:   row.ID,
				Status: schema.PictureStatusInbox,
			}, scFields, pictures.OrderByIDDesc)
			if err != nil && !errors.Is(err, sql.ErrNoRows) {
				return nil, err
			}

			if err == nil {
				resultRow.Siblings.PrevNew, err = s.Extract(
					ctx,
					prevNewPicture,
					sFields,
					lang,
					userCtx,
				)
				if err != nil {
					return nil, err
				}
			}

			nextNewPicture, err := picturesRepository.Picture(ctx, &query.PictureListOptions{
				IDGt:   row.ID,
				Status: schema.PictureStatusInbox,
			}, scFields, pictures.OrderByIDAsc)
			if err != nil && !errors.Is(err, sql.ErrNoRows) {
				return nil, err
			}

			if err == nil {
				resultRow.Siblings.NextNew, err = s.Extract(
					ctx,
					nextNewPicture,
					sFields,
					lang,
					userCtx,
				)
				if err != nil {
					return nil, err
				}
			}
		}

		if row.IP.Status == pgtype.Present && row.IP.IPNet != nil && util.Contains(userCtx.Roles, users.RoleModer) {
			resultRow.IpAddress = row.IP.IPNet.IP.String()
		}

		if fields.GetSubscribed() && userCtx.UserID > 0 {
			commentsRepo, err := s.container.CommentsRepository(ctx)
			if err != nil {
				return nil, err
			}

			resultRow.Subscribed, err = commentsRepo.IsSubscribed(ctx, userCtx.UserID,
				schema.CommentMessageTypeIDPictures, row.ID)
			if err != nil {
				return nil, err
			}
		}

		resultRow.Paginator, err = s.buildPicturesPaginator(ctx, row, fields.GetPaginator(), picturesRepository)
		if err != nil {
			return nil, err
		}

		result = append(result, resultRow)
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

	paginator.PageRange = 15
	paginator.CurrentPageNumber = pageNumber

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, err
	}

	picturesPages := PicturesPages{
		PageCount:      pages.PageCount,
		TotalItemCount: pages.TotalItemCount,
	}

	if pages.Previous > 0 {
		picturesPages.Previous = paginatorPictures[pages.Previous-1].Identity
	}

	if pages.Next > 0 {
		picturesPages.Next = paginatorPictures[pages.Next-1].Identity
	}

	if pages.First > 0 {
		picturesPages.First = paginatorPictures[pages.First-1].Identity
	}

	if pages.Last > 0 {
		picturesPages.Last = paginatorPictures[pages.Last-1].Identity
	}

	if pages.Current > 0 {
		picturesPages.Current = paginatorPictures[pages.Current-1].Identity
	}

	pagesInRange := make([]*PicturesPagesPage, 0)
	for _, i := range pages.PagesInRange {
		pagesInRange = append(pagesInRange, &PicturesPagesPage{
			Page:     i,
			Identity: paginatorPictures[i-1].Identity,
		})
	}

	picturesPages.PagesInRange = pagesInRange

	return &picturesPages, nil
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

func (s *PictureExtractor) path(
	ctx context.Context, pictureID int64, targetItemID int64,
) ([]*PathTreePictureItem, error) {
	picturesRepositury, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	piRows, err := picturesRepositury.PictureItems(ctx, &query.PictureItemListOptions{
		PictureID: pictureID,
		TypeID:    schema.PictureItemTypeContent,
	}, pictures.PictureItemOrderByNone, 0)
	if err != nil {
		return nil, err
	}

	result := make([]*PathTreePictureItem, 0)

	for _, piRow := range piRows {
		item, err := s.itemRoute(ctx, piRow.ItemID, targetItemID)
		if err != nil {
			return nil, err
		}

		if item != nil {
			result = append(result, &PathTreePictureItem{
				PerspectiveId: util.NullInt32ToScalar(piRow.PerspectiveID),
				Item:          item,
			})
		}
	}

	return result, nil
}

func (s *PictureExtractor) itemRoute(
	ctx context.Context,
	itemID int64,
	targetItemID int64,
) (*PathTreeItem, error) {
	itemsRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	row, err := itemsRepository.Item(ctx, &query.ItemListOptions{
		ItemID: itemID,
	}, nil)
	if err != nil {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, nil //nolint: nilnil
		}

		return nil, err
	}

	parentItemTypes := []schema.ItemTableItemTypeID{
		schema.ItemTableItemTypeIDCategory,
		schema.ItemTableItemTypeIDEngine,
		schema.ItemTableItemTypeIDVehicle,
	}

	parents := make([]*PathTreeItemParent, 0)
	if util.Contains(parentItemTypes, row.ItemTypeID) {
		parents, err = s.itemParentRoute(ctx, row.ID, targetItemID)
		if err != nil {
			return nil, err
		}
	}

	if len(parents) == 0 && targetItemID != 0 && itemID != targetItemID {
		return nil, nil //nolint: nilnil
	}

	return &PathTreeItem{
		ItemTypeId: extractItemTypeID(row.ItemTypeID),
		Catname:    util.NullStringToString(row.Catname),
		Parents:    parents,
	}, nil
}

func (s *PictureExtractor) itemParentRoute(
	ctx context.Context, itemID int64, targetItemID int64,
) ([]*PathTreeItemParent, error) {
	itemsRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	result := make([]*PathTreeItemParent, 0)

	if itemID > 0 {
		rows, _, err := itemsRepository.ItemParents(ctx, &query.ItemParentListOptions{
			ItemID: itemID,
		}, items.ItemParentFields{}, items.ItemParentOrderByNone)
		if err != nil {
			return nil, err
		}

		for _, row := range rows {
			item, err := s.itemRoute(ctx, row.ParentID, targetItemID)
			if err != nil {
				return nil, err
			}

			if item != nil {
				result = append(result, &PathTreeItemParent{
					Catname: row.Catname,
					Item:    item,
				})
			}
		}
	}

	return result, nil
}
