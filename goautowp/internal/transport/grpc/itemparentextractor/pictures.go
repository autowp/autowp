package itemparentextractor

import (
	"context"
	"maps"
	"slices"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
)

func (s *Extractor) preloadChildDescendantPictures(
	ctx context.Context, rows []*items.ItemParent, request *pb.PicturesRequest, lang string, userCtx authn.User,
) (map[int64]*pb.PicturesList, error) {
	repository, err := s.picturesRepository(ctx)
	if err != nil {
		return nil, err
	}

	options, err := querymapper.PictureListOptionsFromProto(request.GetOptions())
	if err != nil {
		return nil, err
	}

	if options == nil {
		options = &query.PictureListOptions{}
	}

	if options.PictureItem == nil {
		options.PictureItem = &query.PictureItemListOptions{}
	}

	if options.PictureItem.ItemParentCacheAncestor == nil {
		options.PictureItem.ItemParentCacheAncestor = &query.ItemParentCacheListOptions{}
	}

	groups := make(map[int64]*query.PictureListOptions)
	for _, row := range rows {
		if _, found := groups[row.ItemID]; found {
			continue
		}

		selected := options.Clone()
		selected.PictureItem.ItemParentCacheAncestor.ParentID = row.ItemID
		groups[row.ItemID] = selected
	}

	selected, err := repository.GroupedPictures(
		ctx,
		groups,
		picturemapper.FieldsFromProto(request.GetFields()),
		picturemapper.OrderFromProto(request.GetOrder()),
	)
	if err != nil {
		return nil, err
	}

	pictures := make([]*schema.PictureRow, 0)
	seen := make(map[int64]bool)

	for _, id := range slices.Sorted(maps.Keys(groups)) {
		for _, row := range selected[id] {
			if !seen[row.ID] {
				pictures = append(pictures, row)
				seen[row.ID] = true
			}
		}
	}

	extracted, err := s.pictureExtractor.ExtractRows(ctx, pictures, request.GetFields(), lang, userCtx)
	if err != nil {
		return nil, err
	}

	byID := make(map[int64]*pb.Picture, len(extracted))
	for _, picture := range extracted {
		byID[picture.GetId()] = picture
	}

	result := make(map[int64]*pb.PicturesList, len(groups))
	for id := range groups {
		result[id] = &pb.PicturesList{Items: make([]*pb.Picture, 0, len(selected[id]))}
		for _, picture := range selected[id] {
			result[id].Items = append(result[id].Items, byID[picture.ID])
		}
	}

	return result, nil
}
