package itemextractor

import (
	"context"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/protobuf/proto"
)

func (s *Extractor) prepareLocationContributorLookups(
	repository *items.Repository, ids []int64, fields *pb.ItemFields, result *itemBatchFields,
) []func(context.Context) error {
	var lookups []func(context.Context) error
	if fields.GetLocation() {
		lookups = append(lookups, func(ctx context.Context) error {
			locations, err := repository.ItemLocations(ctx, ids)
			if err != nil {
				return err
			}

			result.locations = make(map[int64]*latlng.LatLng, len(locations))
			for id, location := range locations {
				if location.Valid {
					result.locations[id] = &latlng.LatLng{Latitude: location.Point.Y(), Longitude: location.Point.X()}
				}
			}

			return nil
		})
	}

	if fields.GetSpecsContributors() {
		lookups = append(lookups, func(ctx context.Context) error {
			attrsRepository, err := s.dependencies.AttrsRepository(ctx)
			if err != nil {
				return err
			}

			contributors, err := attrsRepository.ContributorsBatch(ctx, ids)
			if err != nil {
				return err
			}

			result.contributors = make(map[int64][]*pb.SpecsContributor, len(ids))
			for _, id := range ids {
				values := make([]*pb.SpecsContributor, 0, len(contributors[id]))
				for _, contributor := range contributors[id] {
					values = append(values, &pb.SpecsContributor{UserId: contributor.UserID, Count: contributor.Count})
				}

				result.contributors[id] = values
			}

			return nil
		})
	}

	return lookups
}

func itemPlainFields(fields *pb.ItemFields) *pb.ItemFields {
	if !fields.GetAltNames() && !fields.GetOtherNames() && !fields.GetLocation() && !fields.GetSpecsContributors() &&
		!fields.GetDesign() && !fields.GetIsCompilesItemOfDay() && !fields.GetPublicRoutes() &&
		fields.GetTwins() == nil && fields.GetCategories() == nil && fields.GetEngineVehicles() == nil {
		return fields
	}

	selected, _ := proto.Clone(fields).(*pb.ItemFields)
	selected.AltNames = false
	selected.OtherNames = false
	selected.Location = false
	selected.SpecsContributors = false
	selected.Design = false
	selected.IsCompilesItemOfDay = false
	selected.PublicRoutes = false
	selected.Twins = nil
	selected.Categories = nil
	selected.EngineVehicles = nil

	return selected
}

func (data *itemBatchFields) applyExtraFields(row *items.Item, resultRow *pb.Item, fields *pb.ItemFields, lang string) {
	if fields.GetTwins() != nil {
		resultRow.Twins = data.twins[row.ID]
	}

	if fields.GetCategories() != nil {
		resultRow.Categories = data.categories[row.ID]
	}

	if fields.GetEngineVehicles() != nil && row.ItemTypeID == schema.ItemTableItemTypeIDEngine {
		resultRow.EngineVehicles = data.engineVehicles[row.ID]
	}

	if fields.GetDesign() {
		resultRow.Design = data.designs[row.ID]
	}

	if fields.GetIsCompilesItemOfDay() {
		resultRow.IsCompilesItemOfDay = data.complies[row.ID]
	}

	if fields.GetPublicRoutes() {
		resultRow.PublicRoutes = data.publicRoutes[row.ID]
	}

	if fields.GetLocation() {
		resultRow.Location = data.locations[row.ID]
	}

	if fields.GetSpecsContributors() {
		resultRow.SpecsContributors = data.contributors[row.ID]
	}

	if fields.GetAltNames() {
		resultRow.AltNames = formatAltNames(data.allNames[row.ID], lang)
	}

	if fields.GetOtherNames() {
		resultRow.OtherNames = formatOtherNames(data.allNames[row.ID], row.NameOnly)
	}
}
