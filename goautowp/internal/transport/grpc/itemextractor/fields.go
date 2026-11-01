package itemextractor

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/itemmapper"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/genproto/googleapis/type/latlng"
)

// itemBatchFields belongs to one extraction, including its field selection and user.
// Nothing is cached across requests, so private subscriptions and moderator fields stay isolated.
type itemBatchFields struct {
	twins                map[int64][]*pb.Item
	categories           map[int64][]*pb.Item
	engineVehicles       map[int64][]*pb.Item
	names                map[int64]string
	allNames             map[int64]map[string]string
	designs              map[int64]*pb.Design
	complies             map[int64]bool
	publicRoutes         map[int64][]*pb.PublicRoute
	locations            map[int64]*latlng.LatLng
	contributors         map[int64][]*pb.SpecsContributor
	comments             map[int64]int32
	specifications       map[int64]int32
	languages            map[int64]int32
	links                map[int64]int32
	subscriptions        map[int64]bool
	fullText             map[int64]bool
	attrZones            map[int64]int64
	childsCounts         map[int64][]*pb.ChildsCount
	engineVehiclesCounts map[int64]int32
}

func (s *Extractor) preloadFields(
	ctx context.Context, rows []*items.Item, fields *pb.ItemFields, userCtx authn.User,
) (*itemBatchFields, error) {
	result, lookups, err := s.prepareFieldLookups(ctx, rows, fields, "", userCtx)
	if err != nil {
		return nil, err
	}

	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, err
	}

	return result, nil
}

// prepareFieldLookups lets batch fields and relation preloads share one bounded
// group, keeping fields parallel without creating a nested parallel group.
func (s *Extractor) prepareFieldLookups(
	ctx context.Context, rows []*items.Item, fields *pb.ItemFields, lang string, userCtx authn.User,
) (*itemBatchFields, []func(context.Context) error, error) {
	result := &itemBatchFields{}
	if len(rows) == 0 {
		return result, nil, nil
	}

	ids := make([]int64, 0, len(rows))

	engineIDs := make([]int64, 0, len(rows))
	for _, row := range rows {
		ids = append(ids, row.ID)
		if row.ItemTypeID == schema.ItemTableItemTypeIDEngine {
			engineIDs = append(engineIDs, row.ID)
		}
	}

	repository, err := s.dependencies.ItemsRepository(ctx)
	if err != nil {
		return nil, nil, err
	}

	// Tasks own separate maps; consumers only read them after every task has joined.
	var lookups []func(context.Context) error

	lookups = append(lookups, s.prepareConnectedLookups(repository, rows, fields, lang, userCtx, result)...)

	lookups = append(lookups, s.prepareDesignRouteLookups(repository, rows, ids, fields, lang, result)...)

	lookups = append(lookups, s.prepareLocationContributorLookups(repository, ids, fields, result)...)

	if fields.GetAltNames() || fields.GetOtherNames() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			result.allNames, err = repository.NamesBatch(ctx, ids)

			return err
		})
	}

	if fields.GetAttrZoneId() {
		lookups = append(lookups, func(ctx context.Context) error {
			vehicleTypes, err := repository.VehicleTypeIDsBatch(ctx, ids, false)
			if err != nil {
				return err
			}

			attrsRepository, err := s.dependencies.AttrsRepository(ctx)
			if err != nil {
				return err
			}

			result.attrZones = make(map[int64]int64, len(rows))
			for _, row := range rows {
				result.attrZones[row.ID] = attrsRepository.ZoneIDByVehicleTypeIDs(row.ItemTypeID, vehicleTypes[row.ID])
			}

			return nil
		})
	}

	if fields.GetChildsCounts() {
		lookups = append(lookups, func(ctx context.Context) error {
			counts, err := repository.ChildsCountsBatch(ctx, ids)
			if err != nil {
				return err
			}

			result.childsCounts = make(map[int64][]*pb.ChildsCount, len(ids))
			for _, id := range ids {
				result.childsCounts[id] = itemmapper.ChildCountsToProto(counts[id])
			}

			return nil
		})
	}

	if util.Contains(userCtx.Roles, users.RoleModer) {
		if fields.GetEngineVehiclesCount() && len(engineIDs) > 0 {
			lookups = append(lookups, func(ctx context.Context) error {
				var err error

				result.engineVehiclesCounts, err = repository.EngineVehiclesCounts(ctx, engineIDs)

				return err
			})
		}

		if fields.GetMeta() {
			lookups = append(lookups, func(ctx context.Context) error {
				var err error

				result.names, err = repository.LanguageNames(ctx, ids, schema.DefaultLanguageCode)

				return err
			})
		}

		if fields.GetLinksCount() {
			lookups = append(lookups, func(ctx context.Context) error {
				var err error

				result.links, err = repository.LinksCounts(ctx, ids)

				return err
			})
		}

		if fields.GetItemLanguageCount() {
			lookups = append(lookups, func(ctx context.Context) error {
				var err error

				result.languages, err = repository.ItemLanguageCounts(ctx, ids)

				return err
			})
		}

		if fields.GetSpecificationsCount() {
			lookups = append(lookups, func(ctx context.Context) error {
				var err error

				attrsRepository, err := s.dependencies.AttrsRepository(ctx)
				if err != nil {
					return err
				}

				result.specifications, err = attrsRepository.ValuesCountsByItemIDs(ctx, ids)

				return err
			})
		}
	}

	if fields.GetHasText() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			result.fullText, err = repository.ItemsWithFullText(ctx, ids)

			return err
		})
	}

	if fields.GetSubscription() && userCtx.UserID != 0 {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			result.subscriptions, err = repository.SubscribedItems(ctx, ids, userCtx.UserID)

			return err
		})
	}

	if fields.GetCommentsCount() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			commentsRepository, err := s.dependencies.CommentsRepository(ctx)
			if err != nil {
				return err
			}

			result.comments, err = commentsRepository.TopicStats(ctx, ids, schema.CommentMessageTypeIDItems)

			return err
		})
	}

	return result, lookups, nil
}
