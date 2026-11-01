package pictureitemextractor

import (
	"context"
	"maps"
	"slices"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/autowp/goautowp/internal/transport/grpc/parentcacheextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type ItemExtractor interface {
	ExtractRows(
		ctx context.Context,
		rows []*items.Item,
		fields *pb.ItemFields,
		lang string,
		userCtx authn.User,
	) ([]*pb.Item, error)
}

type PictureExtractor interface {
	ExtractRows(
		ctx context.Context,
		rows []*schema.PictureRow,
		fields *pb.PictureFields,
		lang string,
		userCtx authn.User,
	) ([]*pb.Picture, error)
}

type Extractor struct {
	itemsRepository      func(ctx context.Context) (*items.Repository, error)
	picturesRepository   func(ctx context.Context) (*pictures.Repository, error)
	itemExtractor        ItemExtractor
	pictureExtractor     PictureExtractor
	parentCacheExtractor func(ctx context.Context) (*parentcacheextractor.Extractor, error)
}

func New(
	itemsRepository func(ctx context.Context) (*items.Repository, error),
	picturesRepository func(ctx context.Context) (*pictures.Repository, error),
	itemExtractor ItemExtractor,
	pictureExtractor PictureExtractor,
	parentCacheExtractor func(ctx context.Context) (*parentcacheextractor.Extractor, error),
) *Extractor {
	return &Extractor{
		itemsRepository:      itemsRepository,
		picturesRepository:   picturesRepository,
		itemExtractor:        itemExtractor,
		pictureExtractor:     pictureExtractor,
		parentCacheExtractor: parentCacheExtractor,
	}
}

func (s *Extractor) ExtractRows(
	ctx context.Context,
	rows []*schema.PictureItemRow,
	fields *pb.PictureItemFields,
	lang string,
	userCtx authn.User,
) ([]*pb.PictureItem, error) {
	if len(rows) == 0 {
		return []*pb.PictureItem{}, nil
	}

	if fields == nil {
		fields = &pb.PictureItemFields{}
	}

	itemIDs := make(map[int64]struct{}, len(rows))
	pictureIDs := make(map[int64]struct{}, len(rows))

	for _, row := range rows {
		itemIDs[row.ItemID] = struct{}{}
		pictureIDs[row.PictureID] = struct{}{}
	}

	ids := slices.Sorted(maps.Keys(itemIDs))

	itemRequest := fields.GetItem()
	itemParentCacheAncestorRequest := fields.GetItemParentCacheAncestor()
	pictureRequest := fields.GetPicture()

	var (
		itemRows                    map[int64]*pb.Item
		itemParentCacheAncestorRows map[int64][]*pb.ItemParentCache
		pictureRows                 map[int64]*pb.Picture
		lookups                     []func(context.Context) error
	)

	// Independent maps are read only after joining. Reuse the item lookup runner
	// so recursive item/picture-item extraction cannot multiply its parallel groups.
	if itemRequest != nil {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			itemRows, err = s.preloadItems(ctx, itemRequest, ids, lang, userCtx)

			return err
		})
	}

	if itemParentCacheAncestorRequest != nil {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			itemParentCacheAncestorRows, err = s.preloadItemParentCache(
				ctx, itemParentCacheAncestorRequest, ids, lang, userCtx,
			)

			return err
		})
	}

	if pictureRequest != nil {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			pictureRows, err = s.preloadPictures(
				ctx, pictureRequest, slices.Sorted(maps.Keys(pictureIDs)), lang, userCtx,
			)

			return err
		})
	}

	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	result := make([]*pb.PictureItem, 0, len(rows))

	for _, row := range rows {
		resultRow := &pb.PictureItem{
			PictureId:         row.PictureID,
			ItemId:            row.ItemID,
			Type:              picturemapper.ItemTypeToProto(row.Type),
			CropLeft:          uint32(util.NullInt32ToScalar(row.CropLeft)),   //nolint:gosec
			CropTop:           uint32(util.NullInt32ToScalar(row.CropTop)),    //nolint:gosec
			CropWidth:         uint32(util.NullInt32ToScalar(row.CropWidth)),  //nolint:gosec
			CropHeight:        uint32(util.NullInt32ToScalar(row.CropHeight)), //nolint:gosec
			PerspectiveId:     util.NullInt32ToScalar(row.PerspectiveID),
			AddUserId:         util.NullInt64ToScalar(row.AddUserID),
			PerspectiveUserId: util.NullInt64ToScalar(row.PerspectiveUserID),
			CreateTime:        timestamppb.New(row.CreatedAt),
		}

		if itemRequest != nil {
			resultRow.Item = itemRows[row.ItemID]
		}

		if pictureRequest != nil {
			resultRow.Picture = pictureRows[row.PictureID]
		}

		if itemParentCacheAncestorRequest != nil {
			resultRow.ItemParentCacheAncestors = &pb.ItemParentCaches{
				Items: itemParentCacheAncestorRows[row.ItemID],
			}
		}

		result = append(result, resultRow)
	}

	return result, nil
}

func (s *Extractor) Extract(
	ctx context.Context,
	row *schema.PictureItemRow,
	fields *pb.PictureItemFields,
	lang string,
	userCtx authn.User,
) (*pb.PictureItem, error) {
	result, err := s.ExtractRows(ctx, []*schema.PictureItemRow{row}, fields, lang, userCtx)

	return result[0], err
}

func (s *Extractor) preloadPictures(
	ctx context.Context, request *pb.PicturesRequest, ids []int64, lang string, userCtx authn.User,
) (map[int64]*pb.Picture, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64]*pb.Picture, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	fields := request.GetFields()

	options, err := querymapper.PictureListOptionsFromProto(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.PictureListOptions{}
	}

	options.IDs = ids

	repository, err := s.picturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, _, err := repository.Pictures(
		ctx,
		options,
		picturemapper.FieldsFromProto(fields),
		pictures.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	extracted, err := s.pictureExtractor.ExtractRows(ctx, rows, fields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	for _, picture := range extracted {
		result[picture.GetId()] = picture
	}

	return result, nil
}

func (s *Extractor) preloadItems(
	ctx context.Context, request *pb.ItemsRequest, ids []int64, lang string, userCtx authn.User,
) (map[int64]*pb.Item, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64]*pb.Item, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	itemFields := request.GetFields()

	options, err := querymapper.ItemListOptionsFromProto(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.ItemListOptions{}
	}

	options.ItemIDs = ids
	options.Language = lang

	repository, err := s.itemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, _, err := repository.List(
		ctx,
		options,
		itemmapper.FieldsFromProto(itemFields),
		items.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	extracted, err := s.itemExtractor.ExtractRows(ctx, rows, itemFields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	for _, item := range extracted {
		result[item.GetId()] = item
	}

	return result, nil
}

func (s *Extractor) preloadItemParentCache(
	ctx context.Context,
	request *pb.ItemParentCacheRequest,
	ids []int64,
	lang string,
	userCtx authn.User,
) (map[int64][]*pb.ItemParentCache, error) {
	if request == nil {
		return nil, nil //nolint: nilnil
	}

	result := make(map[int64][]*pb.ItemParentCache, len(ids))

	if len(ids) == 0 {
		return result, nil
	}

	options, err := querymapper.ItemParentCacheListOptionsFromProto(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.ItemParentCacheListOptions{}
	}

	options.ItemIDs = ids

	repository, err := s.itemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	rows, err := repository.ItemParentCaches(ctx, options)
	if err != nil {
		return nil, err
	}

	extractor, err := s.parentCacheExtractor(ctx)
	if err != nil {
		return nil, err
	}

	extractedRows, err := extractor.ExtractRows(
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
			result[itemID] = make([]*pb.ItemParentCache, 0)
		}

		result[itemID] = append(result[itemID], row)
	}

	return result, nil
}
