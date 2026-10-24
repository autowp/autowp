package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"maps"
	"slices"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"golang.org/x/sync/errgroup"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/types/known/wrapperspb"
)

const engineVehiclesGroupsLimit = 3

// Bound parallel item branches; nested item lookups reuse their branch sequentially.
const itemExtractorParallelism = 4

var (
	itemTypeCanHaveSpecs = []schema.ItemTableItemTypeID{
		schema.ItemTableItemTypeIDCategory, schema.ItemTableItemTypeIDEngine, schema.ItemTableItemTypeIDTwins,
		schema.ItemTableItemTypeIDVehicle,
	}
	itemTypeCanHaveRoute = []schema.ItemTableItemTypeID{
		schema.ItemTableItemTypeIDCategory, schema.ItemTableItemTypeIDTwins, schema.ItemTableItemTypeIDBrand,
		schema.ItemTableItemTypeIDEngine, schema.ItemTableItemTypeIDVehicle,
	}

	errPreloadNotImplemented = errors.New("preload item_parent with limit not implemented")
)

type ItemExtractor struct {
	container *Container
}

func NewItemExtractor(
	container *Container,
) *ItemExtractor {
	return &ItemExtractor{container: container}
}

// itemLookupContextKey marks a branch that already occupies a parallel lookup slot.
type itemLookupContextKey struct{}

// runItemLookups cancels sibling reads on the first failure and joins all tasks.
// Nested groups run inline: acquiring another slot while a parent waits could
// deadlock, and creating a fresh group would multiply concurrency at every level.
func runItemLookups(ctx context.Context, lookups []func(context.Context) error) error {
	if ctx.Value(itemLookupContextKey{}) != nil {
		for _, lookup := range lookups {
			if err := ctx.Err(); err != nil {
				return err
			}

			if err := lookup(ctx); err != nil {
				return err
			}
		}

		return nil
	}

	ctx = context.WithValue(ctx, itemLookupContextKey{}, true)
	group, lookupCtx := errgroup.WithContext(ctx)
	group.SetLimit(itemExtractorParallelism)

	for _, lookup := range lookups {
		group.Go(func() error {
			if err := lookupCtx.Err(); err != nil {
				return err
			}

			return lookup(lookupCtx)
		})
	}

	return group.Wait()
}

func (s *ItemExtractor) ExtractRows(
	ctx context.Context, rows []*items.Item, fields *ItemFields, lang string, userCtx UserContext,
) ([]*Item, error) {
	isModer := util.Contains(userCtx.Roles, users.RoleModer)

	if fields == nil {
		fields = &ItemFields{}
	}

	ids := make([]int64, 0, len(rows))

	for _, row := range rows {
		ids = append(ids, row.ID)
	}

	var (
		err    error
		result = make([]*Item, 0, len(rows))
	)

	pictureItemRequest := fields.GetPictureItems()

	pictureItemRows, err := s.preloadPictureItems(ctx, pictureItemRequest, ids, lang, userCtx)
	if err != nil {
		return nil, err
	}

	itemParentChildsRequest := fields.GetItemParentChilds()

	itemParentChildRows, err := s.preloadItemParentChilds(
		ctx,
		itemParentChildsRequest,
		ids,
		lang,
		userCtx,
	)
	if err != nil {
		return nil, err
	}

	batch, err := s.preloadFields(ctx, rows, fields, userCtx)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		resultRow := &Item{
			AttrZoneId:                 batch.attrZones[row.ID],
			ChildsCounts:               batch.childsCounts[row.ID],
			EngineVehiclesCount:        batch.engineVehiclesCounts[row.ID],
			LinksCount:                 batch.links[row.ID],
			ItemLanguageCount:          batch.languages[row.ID],
			CommentsCount:              batch.comments[row.ID],
			HasText:                    batch.fullText[row.ID],
			Subscription:               batch.subscriptions[row.ID],
			SpecificationsCount:        batch.specifications[row.ID],
			Id:                         row.ID,
			Catname:                    util.NullStringToString(row.Catname),
			EngineItemId:               util.NullInt64ToScalar(row.EngineItemID),
			EngineInherit:              row.EngineInherit,
			DescendantsCount:           row.DescendantsCount,
			ItemTypeId:                 extractItemTypeID(row.ItemTypeID),
			IsConcept:                  row.IsConcept,
			IsConceptInherit:           row.IsConceptInherit,
			SpecId:                     util.NullInt32ToScalar(row.SpecID),
			SpecInherit:                row.SpecInherit,
			Description:                row.Description,
			FullText:                   row.FullText,
			DescendantPicturesCount:    row.DescendantPicturesCount,
			ChildsCount:                row.ChildsCount,
			ParentsCount:               row.ParentsCount,
			DescendantTwinsGroupsCount: row.DescendantTwinsGroupsCount,
			InboxPicturesCount:         row.InboxPicturesCount,
			AcceptedPicturesCount:      row.AcceptedPicturesCount,
			ExactPicturesCount:         row.ExactPicturesCount,
			FullName:                   row.FullName,
			MostsActive:                row.MostsActive,
			CommentsAttentionsCount:    row.CommentsAttentionsCount,
			HasChildSpecs:              row.HasChildSpecs,
			HasSpecs:                   row.HasSpecs,
			ProducedExactly:            row.ProducedExactly,
			IsGroup:                    row.IsGroup,
			NameOnly:                   row.NameOnly,
			NameDefault:                row.NameDefault,
		}

		if row.Produced.Valid {
			resultRow.Produced = &wrapperspb.Int32Value{Value: row.Produced.Int32}
		}

		if fields.GetMeta() && isModer {
			resultRow.Name = batch.names[row.ID]

			resultRow.Body = row.Body
			resultRow.BeginYear = util.NullInt32ToScalar(row.BeginYear)
			resultRow.EndYear = util.NullInt32ToScalar(row.EndYear)
			resultRow.BeginMonth = int32(util.NullInt16ToScalar(row.BeginMonth))
			resultRow.EndMonth = int32(util.NullInt16ToScalar(row.EndMonth))
			resultRow.BeginModelYear = util.NullInt32ToScalar(row.BeginModelYear)
			resultRow.EndModelYear = util.NullInt32ToScalar(row.EndModelYear)
			resultRow.BeginModelYearFraction = util.NullStringToString(row.BeginModelYearFraction)
			resultRow.EndModelYearFraction = util.NullStringToString(row.EndModelYearFraction)

			if row.Today.Valid {
				resultRow.Today = &wrapperspb.BoolValue{
					Value: row.Today.Bool,
				}
			}
		}

		if pictureItemRequest != nil {
			resultRow.PictureItems = &PictureItems{
				Items: pictureItemRows[row.ID],
			}
		}

		if itemParentChildsRequest != nil {
			resultRow.ItemParentChilds = &ItemParents{
				Items: itemParentChildRows[row.ID],
			}
		}

		err = s.extractPlain(ctx, fields, row, resultRow, lang, userCtx)
		if err != nil {
			return nil, err
		}

		result = append(result, resultRow)
	}

	return result, nil
}

func (s *ItemExtractor) Extract(
	ctx context.Context, row *items.Item, fields *ItemFields, lang string, userCtx UserContext,
) (*Item, error) {
	result, err := s.ExtractRows(ctx, []*items.Item{row}, fields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	if len(result) == 0 {
		return nil, errItemNotFound
	}

	return result[0], nil
}

func (s *ItemExtractor) preloadItemParentChilds(
	ctx context.Context, request *ItemParentsRequest, ids []int64, lang string, userCtx UserContext,
) (map[int64][]*ItemParent, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64][]*ItemParent, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	options, err := convertItemParentListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.ItemParentListOptions{}
	}

	itemsRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	fields := convertItemParentFields(request.GetFields())
	orderBy := convertItemParentOrder(request.GetOrder())

	var rows []*items.ItemParent

	limit := request.GetLimit()
	if limit > 0 {
		return nil, errPreloadNotImplemented
	}

	options.ParentIDs = ids

	rows, _, err = itemsRepository.ItemParents(ctx, options, fields, orderBy)
	if err != nil {
		return nil, err
	}

	itemParentExtractor := s.container.ItemParentExtractor()

	extractedRows, err := itemParentExtractor.ExtractRows(
		ctx,
		rows,
		request.GetFields(),
		lang,
		userCtx,
	)
	if err != nil {
		return nil, err
	}

	for _, row := range extractedRows {
		parentID := row.GetParentId()
		if _, ok := result[parentID]; !ok {
			result[parentID] = make([]*ItemParent, 0)
		}

		result[parentID] = append(result[parentID], row)
	}

	return result, nil
}

func (s *ItemExtractor) preloadPictureItems(
	ctx context.Context,
	request *PictureItemsRequest,
	ids []int64,
	lang string,
	userCtx UserContext,
) (map[int64][]*PictureItem, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64][]*PictureItem, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	options, err := convertPictureItemListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.PictureItemListOptions{}
	}

	order := convertPictureItemsOrder(request.GetOrder())

	picturesRepository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	var rows []*schema.PictureItemRow

	limit := request.GetLimit()
	if limit > 0 {
		optionsArr := make([]*query.PictureItemListOptions, 0, len(ids))

		for _, id := range ids {
			cOptions := *options
			cOptions.ItemID = id

			optionsArr = append(optionsArr, &cOptions)
		}

		rows, err = picturesRepository.PictureItemsBatch(ctx, optionsArr, limit)
		if err != nil {
			return nil, err
		}
	} else {
		options.ItemIDs = ids

		rows, err = picturesRepository.PictureItems(ctx, options, order, 0)
		if err != nil {
			return nil, err
		}
	}

	pictureItemExtractor := s.container.PictureItemExtractor()

	extractedRows, err := pictureItemExtractor.ExtractRows(
		ctx,
		rows,
		request.GetFields(),
		lang,
		userCtx,
	)
	if err != nil {
		return nil, err
	}

	for _, row := range extractedRows {
		itemID := row.GetItemId()
		if _, ok := result[itemID]; !ok {
			result[itemID] = make([]*PictureItem, 0)
		}

		result[itemID] = append(result[itemID], row)
	}

	return result, nil
}

func (s *ItemExtractor) extractPlain(
	ctx context.Context,
	fields *ItemFields,
	row *items.Item,
	resultRow *Item,
	lang string,
	userCtx UserContext,
) error {
	var err error

	resultRow.NameText, resultRow.NameHtml, err = s.extractNames(fields, row, lang)
	if err != nil {
		return err
	}

	if fields.GetCanEditSpecs() {
		resultRow.CanEditSpecs = util.Contains(itemTypeCanHaveSpecs, row.ItemTypeID) && userCtx.UserID != 0
	}

	// Each branch owns separate result fields; results are consumed only after joining.
	var lookups []func(context.Context) error

	if row.LogoID.Valid {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.Logo, resultRow.Logo120, resultRow.Brandicon, err = s.extractLogos(ctx, fields, row)

			return err
		})
	}

	if fields.GetIsCompilesItemOfDay() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.IsCompilesItemOfDay, err = s.extractIsCompilesItemOfDay(ctx, fields, row)

			return err
		})
	}

	if fields.GetLocation() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.Location, err = s.extractLocation(ctx, fields, row)

			return err
		})
	}

	if fields.GetOtherNames() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.OtherNames, err = s.extractOtherNames(ctx, fields, row)

			return err
		})
	}

	if fields.GetAltNames() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.AltNames, err = s.extractAltNames(ctx, fields, row, lang)

			return err
		})
	}

	if fields.GetDesign() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.Design, err = s.extractDesignInfo(ctx, fields, row, lang)

			return err
		})
	}

	if fields.GetPublicRoutes() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.PublicRoutes, err = s.itemPublicRoutes(ctx, row)

			return err
		})
	}

	if fields.GetRoute() || fields.GetSpecsRoute() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.Route, resultRow.SpecsRoute, err = s.extractRoutes(ctx, fields, row)

			return err
		})
	}

	if fields.GetCategories() != nil {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.Categories, err = s.extractCategories(ctx, fields, row, lang, userCtx)

			return err
		})
	}

	if fields.GetTwins() != nil {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.Twins, err = s.extractTwins(ctx, fields, row, lang, userCtx)

			return err
		})
	}

	if fields.GetPreviewPictures() != nil {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.PreviewPictures, err = s.extractPreviewPictures(ctx, fields, row, lang, userCtx)

			return err
		})
	}

	if fields.GetEngineVehicles() != nil && row.ItemTypeID == schema.ItemTableItemTypeIDEngine {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.EngineVehicles, err = s.extractEngineVehicles(ctx, fields, row, lang, userCtx)

			return err
		})
	}

	if fields.GetRelatedGroupPictures() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.RelatedGroupPictures, err = s.extractRelatedGroupsPictures(ctx, fields, row, lang)

			return err
		})
	}

	if fields.GetItemOfDayPictures() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.ItemOfDayPictures, err = s.extractItemOfDayPictures(ctx, fields, row, lang)

			return err
		})
	}

	if fields.GetSpecsContributors() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			resultRow.SpecsContributors, err = s.extractSpecsContributors(ctx, fields, row)

			return err
		})
	}

	return runItemLookups(ctx, lookups)
}

func (s *ItemExtractor) extractSpecsContributors(
	ctx context.Context, fields *ItemFields, row *items.Item,
) ([]*SpecsContributor, error) {
	if !fields.GetSpecsContributors() {
		return nil, nil
	}

	attrsRepository, err := s.container.AttrsRepository(ctx)
	if err != nil {
		return nil, err
	}

	contributors, err := attrsRepository.Contributors(ctx, row.ID)
	if err != nil {
		return nil, err
	}

	res := make([]*SpecsContributor, 0, len(contributors))

	for _, contributor := range contributors {
		res = append(res, &SpecsContributor{
			UserId: contributor.UserID,
			Count:  contributor.Count,
		})
	}

	return res, nil
}

func (s *ItemExtractor) extractItemOfDayPictures(
	ctx context.Context, fields *ItemFields, carOfDay *items.Item, lang string,
) ([]*ItemOfDayPicture, error) {
	if !fields.GetItemOfDayPictures() {
		return nil, nil
	}

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	carOfDayPictures, err := s.orientedPictureList(ctx, carOfDay.ID)
	if err != nil {
		return nil, err
	}

	// images
	formatRequests := make(map[string]map[int]int)

	for idx, picture := range carOfDayPictures {
		if picture != nil && picture.ImageID.Valid {
			format := "picture-thumb-medium"
			if idx == 0 {
				format = "picture-thumb-large"
			}

			if _, ok := formatRequests[format]; !ok {
				formatRequests[format] = make(map[int]int)
			}

			formatRequests[format][idx] = int(picture.ImageID.Int64)
		}
	}

	imageStorage, err := s.container.ImageStorage(ctx)
	if err != nil {
		return nil, err
	}

	imagesInfo := make(map[string]map[int]storage.Image)
	for format, requests := range formatRequests {
		imagesInfo[format], err = imageStorage.FormattedImages(
			ctx,
			slices.Collect(maps.Values(requests)),
			format,
		)
		if err != nil {
			return nil, err
		}
	}

	// names
	notEmptyPics := make([]*schema.PictureRow, 0)

	for _, picture := range carOfDayPictures {
		if picture != nil {
			notEmptyPics = append(notEmptyPics, picture)
		}
	}

	pictureRepository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	names, err := pictureRepository.NameData(ctx, notEmptyPics, pictures.NameDataOptions{
		Language: lang,
	})
	if err != nil {
		return nil, err
	}

	paths, err := itemRepository.CataloguePaths(ctx, carOfDay.ID, items.CataloguePathOptions{
		BreakOnFirst: true,
		ToBrand:      false,
	})
	if err != nil {
		return nil, err
	}

	i18nBundle, err := s.container.I18n()
	if err != nil {
		return nil, err
	}

	pictureNameFormatter := pictures.NewPictureNameFormatter(
		items.NewItemNameFormatter(i18nBundle),
		i18nBundle,
	)

	result := make([]*ItemOfDayPicture, 0)

	for idx, row := range carOfDayPictures {
		if row != nil {
			var route []string

			switch carOfDay.ItemTypeID {
			case schema.ItemTableItemTypeIDTwins:
				route = frontend.TwinsGroupPictureRoute(carOfDay.ID, row.Identity)
			case schema.ItemTableItemTypeIDVehicle,
				schema.ItemTableItemTypeIDEngine,
				schema.ItemTableItemTypeIDCategory,
				schema.ItemTableItemTypeIDBrand,
				schema.ItemTableItemTypeIDFactory,
				schema.ItemTableItemTypeIDMuseum,
				schema.ItemTableItemTypeIDPerson,
				schema.ItemTableItemTypeIDCopyright:
				for _, path := range paths {
					switch path.Type {
					case items.CataloguePathResultTypeBrand:
						route = frontend.PictureRoute(row.Identity)
					case items.CataloguePathResultTypeBrandItem:
						route = frontend.BrandItemPathPicturesPictureRoute(
							path.BrandCatname,
							path.CarCatname,
							path.Path,
							row.Identity,
						)
					case items.CataloguePathResultTypeCategory:
						route = frontend.CategoryPictureRoute(path.CategoryCatname, row.Identity)
					case items.CataloguePathResultTypePerson:
						route = frontend.PersonPictureRoute(path.ID, row.Identity)
					}
				}
			}

			format := "picture-thumb-medium"
			if idx == 0 {
				format = "picture-thumb-large"
			}

			var imageID int
			if idx < len(carOfDayPictures) && carOfDayPictures[idx].ImageID.Valid {
				imageID = int(carOfDayPictures[idx].ImageID.Int64)
			}

			imageInfo, ok := imagesInfo[format][imageID]

			var thumb *Image
			if ok {
				thumb = APIImageToGRPC(&imageInfo)
			}

			name := ""
			if n, ok := names[row.ID]; ok {
				name, err = pictureNameFormatter.FormatText(n, lang)
				if err != nil {
					return nil, err
				}
			}

			result = append(result, &ItemOfDayPicture{
				Thumb: thumb,
				Name:  name,
				Route: route,
			})
		}
	}

	return result, nil
}

func (s *ItemExtractor) orientedPictureList(
	ctx context.Context,
	itemID int64,
) ([]*schema.PictureRow, error) {
	result := make([]*schema.PictureRow, 0)
	usedIDs := make([]int64, 0)

	pictureRepository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	perspectivesGroupIDs, err := pictureRepository.PerspectivePageGroupIDs(
		ctx,
		schema.PerspectivePageFivePics,
	)
	if err != nil {
		return nil, err
	}

	for _, groupID := range perspectivesGroupIDs {
		sqSelect := query.PictureListOptions{
			ExcludeIDs: usedIDs,
			Status:     schema.PictureStatusAccepted,
			PictureItem: &query.PictureItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
					ParentID: itemID,
				},
				PerspectiveGroupPerspective: &query.PerspectiveGroupPerspectiveListOptions{
					GroupID: groupID,
				},
			},
			Limit: 1,
		}

		picture, err := pictureRepository.Picture(
			ctx,
			&sqSelect,
			nil,
			pictures.OrderByVotesAndPerspectivesGroupPerspectives,
		)
		if err != nil && !errors.Is(err, sql.ErrNoRows) {
			return nil, err
		}

		if picture != nil {
			result = append(result, picture)
			usedIDs = append(usedIDs, picture.ID)
		} else {
			result = append(result, nil)
		}
	}

	resorted := make([]*schema.PictureRow, 0)

	for _, picture := range result {
		if picture != nil {
			resorted = append(resorted, picture)
		}
	}

	for _, picture := range result {
		if picture == nil {
			resorted = append(resorted, nil)
		}
	}

	result = resorted
	left := make([]int, 0)

	for key, picture := range result {
		if picture == nil {
			left = append(left, key)
		}
	}

	if len(left) > 0 {
		rows, _, err := pictureRepository.Pictures(ctx, &query.PictureListOptions{
			ExcludeIDs: usedIDs,
			Status:     schema.PictureStatusAccepted,
			PictureItem: &query.PictureItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
					ParentID: itemID,
				},
			},
			Limit: uint32(len(left)), //nolint: gosec
		}, nil, pictures.OrderByResolutionDesc, false)
		if err != nil {
			return nil, err
		}

		for _, pic := range rows {
			key := left[0]
			left = left[1:]
			result[key] = pic
		}
	}

	return result, nil
}

func (s *ItemExtractor) extractRelatedGroupsPictures(
	ctx context.Context, fields *ItemFields, row *items.Item, lang string,
) ([]*RelatedGroupPicture, error) {
	if !fields.GetRelatedGroupPictures() {
		return nil, nil
	}

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	groups, err := itemRepository.RelatedCarGroups(ctx, row.ID)
	if err != nil {
		return nil, err
	}

	carPictures := make([]*RelatedGroupPicture, 0, len(groups))
	if len(groups) == 0 {
		return carPictures, nil
	}

	ids := slices.Collect(maps.Keys(groups))

	cars, _, err := itemRepository.List(ctx, &query.ItemListOptions{ItemIDs: ids, Language: lang},
		&items.ItemFields{NameText: true}, items.OrderByAge, false)
	if err != nil {
		return nil, err
	}

	pictureRepository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	// A single-child group looks under the representative; merged groups look
	// under all their children, preserving the old ancestor selection.
	ancestors := make(map[int64][]int64, len(cars))
	for _, car := range cars {
		ancestors[car.ID] = []int64{car.ID}
		if len(groups[car.ID]) > 1 {
			ancestors[car.ID] = groups[car.ID]
		}
	}

	previews, err := pictureRepository.RelatedGroupPreviews(ctx, ancestors)
	if err != nil {
		return nil, err
	}

	imageIDs := make([]int, 0, len(previews))
	seen := make(map[int]bool)

	for _, preview := range previews {
		if preview.ImageID.Valid {
			id := int(preview.ImageID.Int64)
			if !seen[id] {
				seen[id] = true
				imageIDs = append(imageIDs, id)
			}
		}
	}

	imageStorage, err := s.container.ImageStorage(ctx)
	if err != nil {
		return nil, err
	}

	images := make(map[int]storage.Image)
	if len(imageIDs) > 0 {
		images, err = imageStorage.FormattedImages(ctx, imageIDs, pictureThumbLargeFormat)
		if err != nil {
			return nil, err
		}
	}

	paths, err := itemRepository.FirstCataloguePaths(ctx, ids)
	if err != nil {
		return nil, err
	}

	i18nBundle, err := s.container.I18n()
	if err != nil {
		return nil, err
	}

	itemNameFormatter := items.NewItemNameFormatter(i18nBundle)

	for _, car := range cars {
		src := ""

		if preview := previews[car.ID]; preview != nil && preview.ImageID.Valid {
			image := images[int(preview.ImageID.Int64)]
			src = image.Src()
		}

		var route []string
		if path := paths[car.ID]; path != nil {
			route = append([]string{"/", path.BrandCatname, path.CarCatname}, path.Path...)
		}

		formatterOptions := items.ItemNameFormatterOptions{
			BeginModelYear:         util.NullInt32ToScalar(car.BeginModelYear),
			EndModelYear:           util.NullInt32ToScalar(car.EndModelYear),
			BeginModelYearFraction: util.NullStringToString(car.BeginModelYearFraction),
			EndModelYearFraction:   util.NullStringToString(car.EndModelYearFraction),
			Spec:                   car.SpecShortName,
			SpecFull:               car.SpecName,
			Body:                   car.Body,
			Name:                   car.NameOnly,
			BeginYear:              util.NullInt32ToScalar(car.BeginYear),
			EndYear:                util.NullInt32ToScalar(car.EndYear),
			Today:                  util.NullBoolToBoolPtr(car.Today),
			BeginMonth:             util.NullInt16ToScalar(car.BeginMonth),
			EndMonth:               util.NullInt16ToScalar(car.EndMonth),
		}

		formattedName, err := itemNameFormatter.FormatHTML(formatterOptions, lang)
		if err != nil {
			return nil, err
		}

		carPictures = append(carPictures, &RelatedGroupPicture{
			NameHtml: formattedName,
			Src:      src,
			Route:    route,
		})
	}

	return carPictures, nil
}

func (s *ItemExtractor) extractEngineVehicles(
	ctx context.Context, fields *ItemFields, row *items.Item, lang string, userCtx UserContext,
) ([]*Item, error) {
	evs := fields.GetEngineVehicles()
	if evs == nil {
		return nil, nil
	}

	if row.ItemTypeID != schema.ItemTableItemTypeIDEngine {
		return nil, nil
	}

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	ids, err := itemRepository.EngineVehiclesGroups(ctx, row.ID, engineVehiclesGroupsLimit)
	if err != nil {
		return nil, err
	}

	if len(ids) == 0 {
		return nil, nil
	}

	itemExtractor := s.container.ItemExtractor()

	itemFields := evs.GetFields()
	if itemFields == nil {
		itemFields = &ItemFields{}
	}

	listOptions := evs.GetOptions()
	if listOptions == nil {
		listOptions = &ItemListOptions{}
	}

	repoListOptions, err := convertItemListOptions(listOptions)
	if err != nil {
		return nil, err
	}

	repoListOptions.ItemIDs = ids

	rows, _, err := itemRepository.List(
		ctx,
		repoListOptions,
		convertItemFields(itemFields),
		items.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	return itemExtractor.ExtractRows(ctx, rows, itemFields, lang, userCtx)
}

// previewPictureFields is the field set a preview picture is extracted with: what the caller asked
// for, plus the name every caller of preview pictures displays, and exactly one of the two
// thumbnail sizes.
func previewPictureFields(requested *PictureFields, thumbLarge bool) *PictureFields {
	fields, _ := proto.Clone(requested).(*PictureFields)
	if fields == nil {
		fields = &PictureFields{}
	}

	fields.NameText = true
	fields.ThumbLarge = thumbLarge
	fields.ThumbMedium = !thumbLarge

	return fields
}

func (s *ItemExtractor) extractPreviewPictures(
	ctx context.Context, fields *ItemFields, row *items.Item, lang string, userCtx UserContext,
) (*PreviewPictures, error) {
	pps := fields.GetPreviewPictures()
	if pps == nil {
		return nil, nil //nolint: nilnil
	}

	pictureRepository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	cFetcher := NewPerspectivePictureFetcher(pictureRepository)

	picturesRequest := pps.GetPictures()
	if picturesRequest == nil {
		picturesRequest = &PicturesRequest{}
	}

	picturesOptions := picturesRequest.GetOptions()
	if picturesOptions == nil {
		picturesOptions = &PictureListOptions{}
	}

	listOptions, err := convertPictureListOptions(picturesOptions)
	if err != nil {
		return nil, err
	}

	result, err := cFetcher.Fetch(ctx, row.ItemRow, PerspectivePictureFetcherOptions{
		ListOptions:         listOptions,
		OnlyExactlyPictures: pps.GetOnlyExactlyPictures(),
		PerspectivePageID:   pps.GetPerspectivePageId(),
	})
	if err != nil {
		return nil, err
	}

	// if pps.GetRoute() {
	//	for idx, picture := range result.Pictures {
	//		if picture != nil {
	//			pictures.Pictures[idx].Route = []string{"/picture", picture.Row.Identity}
	//		}
	//	}
	// }

	pictureExtractor := s.container.PictureExtractor()

	// Two field sets rather than one mutated between pictures: only the first picture of a
	// large-format item gets the large thumbnail, the rest get the medium one. Cloned because the
	// message that arrived belongs to the caller's request, and because both are now alive at
	// once - each is used for a whole batch.
	thumbMediumFields := previewPictureFields(picturesRequest.GetFields(), false)
	thumbLargeFields := previewPictureFields(picturesRequest.GetFields(), true)

	// Which is what makes this two batches instead of a call per picture: extracting one at a time
	// meant the picture extractor's preloads - names, images, formatted variants, routes - ran once
	// per preview picture, and a catalogue listing shows several per item and dozens of items.
	extractedPics := make([]*NullPicture, len(result.Pictures))
	for idx := range extractedPics {
		extractedPics[idx] = &NullPicture{Kind: &NullPicture_Null{}}
	}

	largeIdx := -1
	mediumRows := make([]*schema.PictureRow, 0, len(result.Pictures))
	mediumIdxs := make([]int, 0, len(result.Pictures))

	for idx, pic := range result.Pictures {
		if pic == nil || pic.Row == nil {
			continue
		}

		if result.LargeFormat && idx == 0 {
			largeIdx = idx

			continue
		}

		mediumRows = append(mediumRows, pic.Row)
		mediumIdxs = append(mediumIdxs, idx)
	}

	if len(mediumRows) > 0 {
		extracted, err := pictureExtractor.ExtractRows(ctx, mediumRows, thumbMediumFields, lang, userCtx)
		if err != nil {
			return nil, err
		}

		for idx, extractedPic := range extracted {
			extractedPics[mediumIdxs[idx]] = &NullPicture{Kind: &NullPicture_Picture{Picture: extractedPic}}
		}
	}

	if largeIdx >= 0 {
		extracted, err := pictureExtractor.ExtractRows(
			ctx, []*schema.PictureRow{result.Pictures[largeIdx].Row}, thumbLargeFields, lang, userCtx,
		)
		if err != nil {
			return nil, err
		}

		extractedPics[largeIdx] = &NullPicture{Kind: &NullPicture_Picture{Picture: extracted[0]}}
	}

	return &PreviewPictures{
		LargeFormat:   result.LargeFormat,
		Pictures:      extractedPics,
		TotalPictures: result.TotalPictures,
	}, nil
}

func (s *ItemExtractor) extractIsCompilesItemOfDay(
	ctx context.Context, fields *ItemFields, row *items.Item,
) (bool, error) {
	if !fields.GetIsCompilesItemOfDay() {
		return false, nil
	}

	itemOfDayRepository, err := s.container.ItemOfDayRepository(ctx)
	if err != nil {
		return false, err
	}

	return itemOfDayRepository.IsComplies(ctx, row.ID)
}

func (s *ItemExtractor) extractLogos(
	ctx context.Context, fields *ItemFields, row *items.Item,
) (*Image, *Image, *Image, error) {
	if !row.LogoID.Valid {
		return nil, nil, nil, nil
	}

	var (
		logo      *Image
		logo120   *Image
		brandicon *Image
	)

	imageStorage, err := s.container.ImageStorage(ctx)
	if err != nil {
		return nil, nil, nil, err
	}

	if fields.GetLogo() {
		img, err := imageStorage.Image(ctx, int(row.LogoID.Int64))
		if err != nil {
			return nil, nil, nil, err
		}

		logo = APIImageToGRPC(img)
	}

	if fields.GetLogo120() {
		img, err := imageStorage.FormattedImage(ctx, int(row.LogoID.Int64), "logo")
		if err != nil {
			return nil, nil, nil, err
		}

		logo120 = APIImageToGRPC(img)
	}

	if fields.GetBrandicon() {
		img, err := imageStorage.FormattedImage(ctx, int(row.LogoID.Int64), "brandicon2")
		if err != nil {
			return nil, nil, nil, err
		}

		brandicon = APIImageToGRPC(img)
	}

	return logo, logo120, brandicon, nil
}

func (s *ItemExtractor) extractConnectedItems(
	ctx context.Context,
	request *ItemsRequest,
	opts *query.ItemListOptions,
	lang string,
	userCtx UserContext,
) ([]*Item, error) {
	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	var order items.OrderBy

	order, opts.SortByName = convertItemOrder(request.GetOrder())

	opts.Language = lang

	rows, _, err := itemRepository.List(
		ctx,
		opts,
		convertItemFields(request.GetFields()),
		order,
		false,
	)
	if err != nil {
		return nil, err
	}

	return s.ExtractRows(ctx, rows, request.GetFields(), lang, userCtx)
}

func (s *ItemExtractor) extractTwins(
	ctx context.Context, fields *ItemFields, row *items.Item, lang string, userCtx UserContext,
) ([]*Item, error) {
	twinsRequest := fields.GetTwins()
	if twinsRequest == nil {
		return nil, nil
	}

	opts := &query.ItemListOptions{
		ItemParentCacheDescendant: &query.ItemParentCacheListOptions{ItemID: row.ID},
		TypeID:                    []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDTwins},
	}

	return s.extractConnectedItems(ctx, twinsRequest, opts, lang, userCtx)
}

func (s *ItemExtractor) extractCategories(
	ctx context.Context, fields *ItemFields, row *items.Item, lang string, userCtx UserContext,
) ([]*Item, error) {
	categoriesRequest := fields.GetCategories()
	if categoriesRequest == nil {
		return nil, nil
	}

	opts := &query.ItemListOptions{
		ItemParentChild: &query.ItemParentListOptions{
			ChildItems: &query.ItemListOptions{
				TypeID: []schema.ItemTableItemTypeID{
					schema.ItemTableItemTypeIDVehicle,
					schema.ItemTableItemTypeIDEngine,
				},
				ItemParentCacheDescendant: &query.ItemParentCacheListOptions{ItemID: row.ID},
			},
		},
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDCategory},
	}

	return s.extractConnectedItems(ctx, categoriesRequest, opts, lang, userCtx)
}

func (s *ItemExtractor) extractRoutes(
	ctx context.Context, fields *ItemFields, row *items.Item,
) ([]string, []string, error) {
	extractRoute := fields.GetRoute() && util.Contains(itemTypeCanHaveRoute, row.ItemTypeID)
	extractSpecsRoute := fields.GetSpecsRoute() &&
		util.Contains(itemTypeCanHaveSpecs, row.ItemTypeID) &&
		row.HasSpecs

	var (
		route      []string
		specsRoute []string
	)

	if extractSpecsRoute || extractRoute {
		itemRepository, err := s.container.ItemsRepository(ctx)
		if err != nil {
			return nil, nil, err
		}

		cataloguePaths, err := itemRepository.CataloguePath(ctx, row.ID, items.CataloguePathOptions{
			BreakOnFirst: true,
			ToBrand:      true,
			StockFirst:   true,
			ToBrandID:    fields.GetRouteBrandId(),
		})
		if err != nil {
			return nil, nil, err
		}

		if extractRoute {
			switch row.ItemTypeID {
			case schema.ItemTableItemTypeIDCategory:
				route = frontend.CategoryRoute(util.NullStringToString(row.Catname))
			case schema.ItemTableItemTypeIDTwins:
				route = frontend.TwinsGroupRoute(row.ID)

			case schema.ItemTableItemTypeIDBrand:
				route = frontend.BrandRoute(util.NullStringToString(row.Catname))

			case schema.ItemTableItemTypeIDEngine,
				schema.ItemTableItemTypeIDVehicle:
				for _, cPath := range cataloguePaths {
					route = frontend.BrandItemPathRoute(
						cPath.BrandCatname,
						cPath.CarCatname,
						cPath.Path,
					)

					break
				}
			case schema.ItemTableItemTypeIDPerson:
				route = frontend.PersonRoute(row.ID)
			case schema.ItemTableItemTypeIDFactory,
				schema.ItemTableItemTypeIDMuseum,
				schema.ItemTableItemTypeIDCopyright:
			}
		}

		if extractSpecsRoute {
			for _, path := range cataloguePaths {
				specsRoute = frontend.BrandItemPathSpecificationsRoute(
					path.BrandCatname,
					path.CarCatname,
					path.Path,
				)

				break
			}
		}
	}

	return route, specsRoute, nil
}

func (s *ItemExtractor) extractDesignInfo(
	ctx context.Context, fields *ItemFields, row *items.Item, lang string,
) (*Design, error) {
	if !fields.GetDesign() {
		return nil, nil //nolint: nilnil
	}

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	designInfo, err := itemRepository.DesignInfo(ctx, row.ID, lang)
	if err != nil {
		return nil, err
	}

	if designInfo == nil {
		return nil, nil //nolint: nilnil
	}

	return &Design{
		Name:  designInfo.Name,
		Route: designInfo.Route,
	}, nil
}

func (s *ItemExtractor) extractNames(
	fields *ItemFields, row *items.Item, lang string,
) (string, string, error) {
	if !fields.GetNameText() && !fields.GetNameHtml() {
		return "", "", nil
	}

	formatterOptions := items.ItemNameFormatterOptions{
		BeginModelYear:         util.NullInt32ToScalar(row.BeginModelYear),
		EndModelYear:           util.NullInt32ToScalar(row.EndModelYear),
		BeginModelYearFraction: util.NullStringToString(row.BeginModelYearFraction),
		EndModelYearFraction:   util.NullStringToString(row.EndModelYearFraction),
		Spec:                   row.SpecShortName,
		SpecFull:               row.SpecName,
		Body:                   row.Body,
		Name:                   row.NameOnly,
		BeginYear:              util.NullInt32ToScalar(row.BeginYear),
		EndYear:                util.NullInt32ToScalar(row.EndYear),
		Today:                  util.NullBoolToBoolPtr(row.Today),
		BeginMonth:             util.NullInt16ToScalar(row.BeginMonth),
		EndMonth:               util.NullInt16ToScalar(row.EndMonth),
	}

	i18nBundle, err := s.container.I18n()
	if err != nil {
		return "", "", err
	}

	itemNameFormatter := items.NewItemNameFormatter(i18nBundle)

	var nameText, nameHTML string

	if fields.GetNameText() {
		nameText, err = itemNameFormatter.FormatText(formatterOptions, lang)
		if err != nil {
			return "", "", err
		}
	}

	if fields.GetNameHtml() {
		nameHTML, err = itemNameFormatter.FormatHTML(formatterOptions, lang)
		if err != nil {
			return "", "", err
		}
	}

	return nameText, nameHTML, nil
}

func (s *ItemExtractor) extractAltNames(
	ctx context.Context, fields *ItemFields, row *items.Item, lang string,
) ([]*AltName, error) {
	if !fields.GetAltNames() {
		return nil, nil
	}

	// alt names
	altNames := make(map[string][]string)
	altNames2 := make(map[string][]string)

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	langNames, err := itemRepository.Names(ctx, row.ID)
	if err != nil {
		return nil, err
	}

	currentLangName := ""

	for clang, langName := range langNames {
		if clang == schema.DefaultLanguageCode {
			continue
		}

		name := langName
		if _, ok := altNames[name]; !ok {
			altNames[langName] = make([]string, 0)
		}

		altNames[name] = append(altNames[name], clang)

		if lang == clang {
			currentLangName = name
		}
	}

	for name, codes := range altNames {
		if name != currentLangName {
			altNames2[name] = codes
		}
	}

	if len(currentLangName) > 0 {
		delete(altNames2, currentLangName)
	}

	res := make([]*AltName, 0, len(altNames2))
	for name, languages := range altNames2 {
		res = append(res, &AltName{
			Languages: languages,
			Name:      name,
		})
	}

	return res, nil
}

func (s *ItemExtractor) extractOtherNames(
	ctx context.Context, fields *ItemFields, row *items.Item,
) ([]string, error) {
	if !fields.GetOtherNames() {
		return nil, nil
	}

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, err := itemRepository.Names(ctx, row.ID)
	if err != nil {
		return nil, err
	}

	otherNames := make([]string, 0, len(rows))
	for _, name := range rows {
		if row.NameOnly != name && !util.Contains(otherNames, name) {
			otherNames = append(otherNames, name)
		}
	}

	return otherNames, nil
}

func (s *ItemExtractor) extractLocation(
	ctx context.Context, fields *ItemFields, row *items.Item,
) (*latlng.LatLng, error) {
	if !fields.GetLocation() {
		return nil, nil //nolint: nilnil
	}

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	location, err := itemRepository.ItemLocation(ctx, row.ID)
	if err != nil && !errors.Is(err, items.ErrItemNotFound) {
		if errors.Is(err, items.ErrItemNotFound) {
			return nil, nil //nolint: nilnil
		}

		return nil, err
	}

	if !location.Valid {
		return nil, nil //nolint: nilnil
	}

	return &latlng.LatLng{
		Latitude:  location.Point.Y(),
		Longitude: location.Point.X(),
	}, nil
}

func (s *ItemExtractor) itemPublicRoutes(
	ctx context.Context,
	item *items.Item,
) ([]*PublicRoute, error) {
	if item.ItemTypeID == schema.ItemTableItemTypeIDFactory {
		return []*PublicRoute{
			{Route: frontend.FactoryRoute(item.ID)},
		}, nil
	}

	if item.ItemTypeID == schema.ItemTableItemTypeIDCategory {
		return []*PublicRoute{
			{Route: frontend.CategoryRoute(util.NullStringToString(item.Catname))},
		}, nil
	}

	if item.ItemTypeID == schema.ItemTableItemTypeIDTwins {
		return []*PublicRoute{
			{Route: frontend.TwinsGroupRoute(item.ID)},
		}, nil
	}

	if item.ItemTypeID == schema.ItemTableItemTypeIDBrand {
		return []*PublicRoute{
			{Route: frontend.BrandRoute(util.NullStringToString(item.Catname))},
		}, nil
	}

	return s.walkUpUntilBrand(ctx, item.ID, []string{})
}

func (s *ItemExtractor) walkUpUntilBrand(
	ctx context.Context,
	id int64,
	path []string,
) ([]*PublicRoute, error) {
	routes := make([]*PublicRoute, 0)

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	parentRows, _, err := itemRepository.ItemParents(ctx, &query.ItemParentListOptions{
		ItemID: id,
	}, items.ItemParentFields{}, items.ItemParentOrderByNone)
	if err != nil {
		return nil, err
	}

	for _, parentRow := range parentRows {
		brand, err := itemRepository.Item(ctx, &query.ItemListOptions{
			TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDBrand},
			ItemID: parentRow.ParentID,
		}, nil)
		if err != nil && !errors.Is(err, items.ErrItemNotFound) {
			return nil, err
		}

		if err == nil {
			routes = append(routes, &PublicRoute{
				Route: frontend.BrandItemPathRoute(
					util.NullStringToString(brand.Catname),
					parentRow.Catname,
					path,
				),
			})
		}

		nextRoutes, err := s.walkUpUntilBrand(
			ctx,
			parentRow.ParentID,
			append([]string{parentRow.Catname}, path...),
		)
		if err != nil {
			return nil, err
		}

		routes = append(routes, nextRoutes...)
	}

	return routes, nil
}
