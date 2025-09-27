package goautowp

import (
	"context"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
)

type PictureItemExtractor struct {
	container *Container
}

func NewPictureItemExtractor(container *Container) *PictureItemExtractor {
	return &PictureItemExtractor{container: container}
}

func (s *PictureItemExtractor) ExtractRows(
	ctx context.Context,
	rows []*schema.PictureItemRow,
	fields *PictureItemFields,
	lang string,
	userCtx UserContext,
) ([]*PictureItem, error) {
	if fields == nil {
		fields = &PictureItemFields{}
	}

	ids := make([]int64, 0, len(rows))

	for _, row := range rows {
		ids = append(ids, row.ItemID)
	}

	itemRequest := fields.GetItem()

	itemRows, err := s.preloadItems(ctx, itemRequest, ids, lang, userCtx)
	if err != nil {
		return nil, err
	}

	itemParentCacheAncestorRequest := fields.GetItemParentCacheAncestor()

	itemParentCacheAncestorRows, err := s.preloadItemParentCache(
		ctx, itemParentCacheAncestorRequest, ids, lang, userCtx,
	)
	if err != nil {
		return nil, err
	}

	pictureRequest := fields.GetPicture()

	pictureRows, err := s.preloadPictures(ctx, pictureRequest, ids, lang, userCtx)
	if err != nil {
		return nil, err
	}

	result := make([]*PictureItem, 0, len(rows))

	for _, row := range rows {
		resultRow := &PictureItem{
			PictureId:     row.PictureID,
			ItemId:        row.ItemID,
			Type:          extractPictureItemType(row.Type),
			CropLeft:      uint32(util.NullInt32ToScalar(row.CropLeft)),   //nolint:gosec
			CropTop:       uint32(util.NullInt32ToScalar(row.CropTop)),    //nolint:gosec
			CropWidth:     uint32(util.NullInt32ToScalar(row.CropWidth)),  //nolint:gosec
			CropHeight:    uint32(util.NullInt32ToScalar(row.CropHeight)), //nolint:gosec
			PerspectiveId: util.NullInt32ToScalar(row.PerspectiveID),
		}

		if itemRequest != nil {
			resultRow.Item = itemRows[row.ItemID]
		}

		if pictureRequest != nil {
			resultRow.Picture = pictureRows[row.PictureID]
		}

		if itemParentCacheAncestorRequest != nil {
			resultRow.ItemParentCacheAncestors = &ItemParentCaches{
				Items: itemParentCacheAncestorRows[row.ItemID],
			}
		}

		result = append(result, resultRow)
	}

	return result, nil
}

func (s *PictureItemExtractor) Extract(
	ctx context.Context,
	row *schema.PictureItemRow,
	fields *PictureItemFields,
	lang string,
	userCtx UserContext,
) (*PictureItem, error) {
	result, err := s.ExtractRows(ctx, []*schema.PictureItemRow{row}, fields, lang, userCtx)

	return result[0], err
}

func (s *PictureItemExtractor) preloadPictures(
	ctx context.Context, request *PicturesRequest, ids []int64, lang string, userCtx UserContext,
) (map[int64]*Picture, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64]*Picture, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	fields := request.GetFields()

	options, err := convertPictureListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.PictureListOptions{}
	}

	options.IDs = ids

	repository, err := s.container.PicturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, _, err := repository.Pictures(
		ctx,
		options,
		convertPictureFields(fields),
		pictures.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	extractor := s.container.PictureExtractor()

	for _, row := range rows {
		result[row.ID], err = extractor.Extract(ctx, row, fields, lang, userCtx)
		if err != nil {
			return nil, err
		}
	}

	return result, nil
}

func (s *PictureItemExtractor) preloadItems(
	ctx context.Context, request *ItemsRequest, ids []int64, lang string, userCtx UserContext,
) (map[int64]*APIItem, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64]*APIItem, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	itemFields := request.GetFields()

	options, err := convertItemListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.ItemListOptions{}
	}

	options.ItemIDs = ids
	options.Language = lang

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, _, err := itemRepository.List(
		ctx,
		options,
		convertItemFields(itemFields),
		items.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	itemExtractor := s.container.ItemExtractor()

	for _, row := range rows {
		result[row.ID], err = itemExtractor.Extract(ctx, row, itemFields, lang, userCtx)
		if err != nil {
			return nil, err
		}
	}

	return result, nil
}

func (s *PictureItemExtractor) preloadItemParentCache(
	ctx context.Context,
	request *ItemParentCacheRequest,
	ids []int64,
	lang string,
	userCtx UserContext,
) (map[int64][]*ItemParentCache, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64][]*ItemParentCache, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	options, err := convertItemParentCacheListOptions(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.ItemParentCacheListOptions{}
	}

	options.ItemIDs = ids

	itemRepository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, err := itemRepository.ItemParentCaches(ctx, options)
	if err != nil {
		return nil, err
	}

	itemParentCacheExtractor := s.container.ItemParentCacheExtractor()

	extractedRows, err := itemParentCacheExtractor.ExtractRows(
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
			result[itemID] = make([]*ItemParentCache, 0)
		}

		result[itemID] = append(result[itemID], row)
	}

	return result, nil
}
