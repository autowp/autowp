package goautowp

import (
	"context"

	"github.com/autowp/goautowp/items"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/protobuf/proto"
)

func (s *ItemExtractor) prepareLocationContributorLookups(
	repository *items.Repository, ids []int64, fields *ItemFields, result *itemBatchFields,
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
			attrsRepository, err := s.container.AttrsRepository(ctx)
			if err != nil {
				return err
			}

			contributors, err := attrsRepository.ContributorsBatch(ctx, ids)
			if err != nil {
				return err
			}

			result.contributors = make(map[int64][]*SpecsContributor, len(ids))
			for _, id := range ids {
				values := make([]*SpecsContributor, 0, len(contributors[id]))
				for _, contributor := range contributors[id] {
					values = append(values, &SpecsContributor{UserId: contributor.UserID, Count: contributor.Count})
				}

				result.contributors[id] = values
			}

			return nil
		})
	}

	return lookups
}

func itemPlainFields(fields *ItemFields) *ItemFields {
	if !fields.GetAltNames() && !fields.GetOtherNames() && !fields.GetLocation() && !fields.GetSpecsContributors() {
		return fields
	}

	selected, _ := proto.Clone(fields).(*ItemFields)
	selected.AltNames = false
	selected.OtherNames = false
	selected.Location = false
	selected.SpecsContributors = false

	return selected
}

func (data *itemBatchFields) applyExtraFields(row *items.Item, resultRow *Item, fields *ItemFields, lang string) {
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
