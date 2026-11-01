package parentcacheextractor

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
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

type Extractor struct {
	repository *items.Repository
	extractor  ItemExtractor
}

func New(repository *items.Repository, extractor ItemExtractor) *Extractor {
	return &Extractor{repository: repository, extractor: extractor}
}

func (s *Extractor) ExtractRows(
	ctx context.Context,
	rows []*schema.ItemParentCacheRow,
	fields *pb.ItemParentCacheFields,
	lang string,
	userCtx authn.User,
) ([]*pb.ItemParentCache, error) {
	parentIDs := make([]int64, 0, len(rows))

	for _, row := range rows {
		if row.ParentID != 0 {
			parentIDs = append(parentIDs, row.ParentID)
		}
	}

	itemRequest := fields.GetParentItem()

	parentItemRows, err := s.preloadItems(ctx, itemRequest, parentIDs, lang, userCtx)
	if err != nil {
		return nil, err
	}

	result := make([]*pb.ItemParentCache, 0, len(rows))

	for _, row := range rows {
		resultRow := &pb.ItemParentCache{
			ItemId:   row.ItemID,
			ParentId: row.ParentID,
		}

		if itemRequest != nil {
			resultRow.ParentItem = parentItemRows[row.ParentID]
		}

		result = append(result, resultRow)
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

	rows, _, err := s.repository.List(
		ctx,
		options,
		itemmapper.FieldsFromProto(itemFields),
		items.OrderByNone,
		false,
	)
	if err != nil {
		return nil, err
	}

	extracted, err := s.extractor.ExtractRows(ctx, rows, itemFields, lang, userCtx)
	if err != nil {
		return nil, err
	}

	for _, item := range extracted {
		result[item.GetId()] = item
	}

	return result, nil
}
