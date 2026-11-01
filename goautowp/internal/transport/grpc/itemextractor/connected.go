package itemextractor

import (
	"context"
	"maps"
	"slices"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
)

func (s *Extractor) prepareConnectedLookups(
	repository *items.Repository, rows []*items.Item, fields *pb.ItemFields, lang string, user authn.User,
	result *itemBatchFields,
) []func(context.Context) error {
	var lookups []func(context.Context) error

	for _, selection := range []struct {
		request    *pb.ItemsRequest
		categories bool
		target     *map[int64][]*pb.Item
	}{
		{fields.GetTwins(), false, &result.twins},
		{fields.GetCategories(), true, &result.categories},
	} {
		if selection.request == nil {
			continue
		}

		lookups = append(lookups, func(ctx context.Context) error {
			groups := make(map[int64]*query.ItemListOptions, len(rows))

			order, natural := itemmapper.OrderFromProto(selection.request.GetOrder())
			for _, row := range rows {
				// Connected requests historically use only Fields and Order.
				options := connectedItemOptions(row.ID, selection.categories)
				options.Language = lang
				options.SortByName = natural
				groups[row.ID] = options
			}

			extracted, err := s.extractConnectedGroups(
				ctx,
				repository,
				groups,
				selection.request.GetFields(),
				order,
				lang,
				user,
			)
			if err != nil {
				return err
			}

			*selection.target = extracted

			return nil
		})
	}

	if request := fields.GetEngineVehicles(); request != nil {
		lookups = append(lookups, func(ctx context.Context) error {
			var ids []int64

			for _, row := range rows {
				if row.ItemTypeID == schema.ItemTableItemTypeIDEngine {
					ids = append(ids, row.ID)
				}
			}

			vehicles, err := repository.EngineVehiclesGroupsBatch(ctx, ids, engineVehiclesGroupsLimit)
			if err != nil {
				return err
			}

			groups := make(map[int64]*query.ItemListOptions, len(vehicles))
			for id, vehicleIDs := range vehicles {
				if len(vehicleIDs) == 0 {
					continue
				}

				options := request.GetOptions()
				if options == nil {
					options = &pb.ItemListOptions{}
				}

				converted, err := querymapper.ItemListOptionsFromProto(options)
				if err != nil {
					return err
				}

				converted.ItemIDs = vehicleIDs
				// Keep the engine request's legacy default SQL language and ignored Order.
				groups[id] = converted
			}

			result.engineVehicles, err = s.extractConnectedGroups(
				ctx,
				repository,
				groups,
				request.GetFields(),
				items.OrderByNone,
				lang,
				user,
			)

			return err
		})
	}

	return lookups
}

func connectedItemOptions(id int64, categories bool) *query.ItemListOptions {
	if !categories {
		return &query.ItemListOptions{
			ItemParentCacheDescendant: &query.ItemParentCacheListOptions{ItemID: id},
			TypeID:                    []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDTwins},
		}
	}

	return &query.ItemListOptions{
		ItemParentChild: &query.ItemParentListOptions{
			ChildItems: &query.ItemListOptions{
				TypeID: []schema.ItemTableItemTypeID{
					schema.ItemTableItemTypeIDVehicle,
					schema.ItemTableItemTypeIDEngine,
				},
				ItemParentCacheDescendant: &query.ItemParentCacheListOptions{ItemID: id},
			},
		},
		TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDCategory},
	}
}

func (s *Extractor) extractConnectedGroups(
	ctx context.Context, repository *items.Repository, groups map[int64]*query.ItemListOptions,
	fields *pb.ItemFields, order items.OrderBy, lang string, user authn.User,
) (map[int64][]*pb.Item, error) {
	selected, err := repository.GroupedList(ctx, groups, itemmapper.FieldsFromProto(fields), order)
	if err != nil {
		return nil, err
	}
	// Keep every row: SQL-derived fields can differ between selections of the same ID.
	rows := make([]*items.Item, 0)

	keys := slices.Sorted(maps.Keys(groups))
	for _, id := range keys {
		rows = append(rows, selected[id]...)
	}

	extracted, err := s.ExtractRows(ctx, rows, fields, lang, user)
	if err != nil {
		return nil, err
	}

	result := make(map[int64][]*pb.Item, len(groups))
	offset := 0

	for _, id := range keys {
		count := len(selected[id])
		result[id] = extracted[offset : offset+count]
		offset += count
	}

	return result, nil
}
