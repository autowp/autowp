package goautowp

import (
	"context"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
)

// itemBatchFields belongs to one extraction, including its field selection and user.
// Nothing is cached across requests, so private subscriptions and moderator fields stay isolated.
type itemBatchFields struct {
	names                map[int64]string
	comments             map[int64]int32
	specifications       map[int64]int32
	languages            map[int64]int32
	links                map[int64]int32
	subscriptions        map[int64]bool
	fullText             map[int64]bool
	attrZones            map[int64]int64
	childsCounts         map[int64][]*ChildsCount
	engineVehiclesCounts map[int64]int32
}

func (s *ItemExtractor) preloadFields(
	ctx context.Context, rows []*items.Item, fields *ItemFields, userCtx UserContext,
) (*itemBatchFields, error) {
	result := &itemBatchFields{}
	if len(rows) == 0 {
		return result, nil
	}

	ids := make([]int64, 0, len(rows))

	engineIDs := make([]int64, 0, len(rows))
	for _, row := range rows {
		ids = append(ids, row.ID)
		if row.ItemTypeID == schema.ItemTableItemTypeIDEngine {
			engineIDs = append(engineIDs, row.ID)
		}
	}

	repository, err := s.container.ItemsRepository(ctx)
	if err != nil {
		return nil, err
	}

	if fields.GetAttrZoneId() {
		vehicleTypes, err := repository.VehicleTypeIDsBatch(ctx, ids, false)
		if err != nil {
			return nil, err
		}

		attrsRepository, err := s.container.AttrsRepository(ctx)
		if err != nil {
			return nil, err
		}

		result.attrZones = make(map[int64]int64, len(rows))
		for _, row := range rows {
			result.attrZones[row.ID] = attrsRepository.ZoneIDByVehicleTypeIDs(row.ItemTypeID, vehicleTypes[row.ID])
		}
	}

	if fields.GetChildsCounts() {
		counts, err := repository.ChildsCountsBatch(ctx, ids)
		if err != nil {
			return nil, err
		}

		result.childsCounts = make(map[int64][]*ChildsCount, len(ids))
		for _, id := range ids {
			result.childsCounts[id] = convertChildsCounts(counts[id])
		}
	}

	if util.Contains(userCtx.Roles, users.RoleModer) {
		if fields.GetEngineVehiclesCount() && len(engineIDs) > 0 {
			result.engineVehiclesCounts, err = repository.EngineVehiclesCounts(ctx, engineIDs)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetMeta() {
			result.names, err = repository.LanguageNames(ctx, ids, schema.DefaultLanguageCode)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetLinksCount() {
			result.links, err = repository.LinksCounts(ctx, ids)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetItemLanguageCount() {
			result.languages, err = repository.ItemLanguageCounts(ctx, ids)
			if err != nil {
				return nil, err
			}
		}

		if fields.GetSpecificationsCount() {
			attrsRepository, err := s.container.AttrsRepository(ctx)
			if err != nil {
				return nil, err
			}

			result.specifications, err = attrsRepository.ValuesCountsByItemIDs(ctx, ids)
			if err != nil {
				return nil, err
			}
		}
	}

	if fields.GetHasText() {
		result.fullText, err = repository.ItemsWithFullText(ctx, ids)
		if err != nil {
			return nil, err
		}
	}

	if fields.GetSubscription() && userCtx.UserID != 0 {
		result.subscriptions, err = repository.SubscribedItems(ctx, ids, userCtx.UserID)
		if err != nil {
			return nil, err
		}
	}

	if fields.GetCommentsCount() {
		commentsRepository, err := s.container.CommentsRepository(ctx)
		if err != nil {
			return nil, err
		}

		result.comments, err = commentsRepository.TopicStats(ctx, ids, schema.CommentMessageTypeIDItems)
		if err != nil {
			return nil, err
		}
	}

	return result, nil
}
