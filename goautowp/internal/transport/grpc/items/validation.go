package items

import (
	"context"
	"errors"
	"fmt"
	"math"
	"time"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/autowp/goautowp/internal/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/protobuf/types/known/wrapperspb"
)

const (
	nameField          = "name"
	catnameField       = "catname"
	engineItemIDField  = "engine_item_id"
	engineInheritField = "engine_inherit"
)

func validateItem( //nolint: maintidx
	ctx context.Context,
	item *pb.Item,
	repository *items.Repository,
	maskPaths []string,
	roles []string,
) ([]*errdetails.BadRequest_FieldViolation, error) {
	if maskPaths == nil || util.Contains(maskPaths, "is_group") {
		switch item.GetItemTypeId() {
		case pb.ItemType_ITEM_TYPE_VEHICLE, pb.ItemType_ITEM_TYPE_ENGINE, pb.ItemType_ITEM_TYPE_UNKNOWN:
			if item.GetId() > 0 {
				_, err := repository.ItemParent(ctx, &query.ItemParentListOptions{
					ParentID: item.GetId(),
				}, items.ItemParentFields{})
				if err != nil && !errors.Is(err, items.ErrItemNotFound) {
					return nil, err
				}

				if err == nil {
					item.IsGroup = true
				}
			}

		case pb.ItemType_ITEM_TYPE_CATEGORY,
			pb.ItemType_ITEM_TYPE_TWINS,
			pb.ItemType_ITEM_TYPE_BRAND,
			pb.ItemType_ITEM_TYPE_FACTORY,
			pb.ItemType_ITEM_TYPE_MUSEUM,
			pb.ItemType_ITEM_TYPE_PERSON,
			pb.ItemType_ITEM_TYPE_COPYRIGHT:
			item.IsGroup = true
		}
	}

	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	isVehicleOrEngine := item.GetItemTypeId() == pb.ItemType_ITEM_TYPE_VEHICLE ||
		item.GetItemTypeId() == pb.ItemType_ITEM_TYPE_ENGINE

	switch item.GetItemTypeId() {
	case pb.ItemType_ITEM_TYPE_VEHICLE,
		pb.ItemType_ITEM_TYPE_ENGINE,
		pb.ItemType_ITEM_TYPE_CATEGORY,
		pb.ItemType_ITEM_TYPE_TWINS,
		pb.ItemType_ITEM_TYPE_BRAND,
		pb.ItemType_ITEM_TYPE_FACTORY,
		pb.ItemType_ITEM_TYPE_MUSEUM,
		pb.ItemType_ITEM_TYPE_PERSON,
		pb.ItemType_ITEM_TYPE_COPYRIGHT:
	case pb.ItemType_ITEM_TYPE_UNKNOWN:
		fallthrough
	default:
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "item_type_id",
			Description: "unexpected `item_type_id`",
		})
	}

	canEditEngine := util.Contains(roles, users.RoleCarsModer)

	if maskPaths == nil || util.Contains(maskPaths, engineInheritField) {
		if item.GetItemTypeId() != pb.ItemType_ITEM_TYPE_VEHICLE {
			if item.GetId() > 0 || item.GetEngineInherit() {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       engineInheritField,
					Description: "engine_inherit can be used only for vehicle",
				})
			}
		} else {
			changesRestrictedEngineInherit := item.GetId() > 0 || (item.GetId() == 0 && item.GetEngineInherit())
			if changesRestrictedEngineInherit && !canEditEngine {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       engineInheritField,
					Description: "permission denied",
				})
			}
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, engineItemIDField) {
		changesRestrictedEngineItemID := item.GetId() > 0 || (item.GetId() == 0 && item.GetEngineItemId() > 0)

		switch {
		case item.GetItemTypeId() != pb.ItemType_ITEM_TYPE_VEHICLE:
			if item.GetId() > 0 || item.GetEngineItemId() > 0 {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       engineItemIDField,
					Description: "engine_item_id can be used only for vehicle",
				})
			}
		case changesRestrictedEngineItemID && !canEditEngine:
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       engineItemIDField,
				Description: "permission denied",
			})
		default:
			if item.GetEngineItemId() > 0 {
				_, err = repository.Item(ctx, &query.ItemListOptions{
					ItemID: item.GetEngineItemId(),
					TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDEngine},
				}, nil)
				if err != nil && !errors.Is(err, items.ErrItemNotFound) {
					return nil, err
				}

				if err != nil {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       engineItemIDField,
						Description: err.Error(),
					})
				}
			}
		}
	}

	touchesToday := maskPaths == nil || util.Contains(maskPaths, "today")
	endYearInPast := item.GetEndYear() > 0 && item.GetEndYear() < int32(time.Now().Year()) //nolint: gosec

	if touchesToday && endYearInPast {
		item.Today = &wrapperspb.BoolValue{Value: false}
	}

	touchesSpecID := maskPaths == nil || util.Contains(maskPaths, "spec_id")
	if touchesSpecID && item.GetSpecId() > 0 && item.GetSpecInherit() {
		item.SpecId = 0
	}

	touchesLocation := maskPaths == nil || util.Contains(maskPaths, "location")
	isLocationlessType := !util.Contains(
		[]pb.ItemType{pb.ItemType_ITEM_TYPE_FACTORY, pb.ItemType_ITEM_TYPE_MUSEUM},
		item.GetItemTypeId(),
	)

	if touchesLocation && isLocationlessType {
		if item.GetLocation() != nil {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "location",
				Description: "location can be used only for factory or museum",
			})
		}
	}

	touchesFullName := maskPaths == nil || util.Contains(maskPaths, "full_name")
	if touchesFullName &&
		item.GetItemTypeId() != pb.ItemType_ITEM_TYPE_BRAND && len(item.GetFullName()) > 0 {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "full_name",
			Description: "full_name can be used only for brand",
		})
	}

	if !isVehicleOrEngine {
		if (maskPaths == nil || util.Contains(maskPaths, "is_concept")) && item.GetIsConcept() {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "is_concept",
				Description: "is_concept can be used only for vehicle or engine",
			})
		}

		if (maskPaths == nil || util.Contains(maskPaths, "is_concept_inherit")) &&
			item.GetIsConceptInherit() {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "is_concept_inherit",
				Description: "is_concept_inherit can be used only for vehicle or engine",
			})
		}

		if (maskPaths == nil || util.Contains(maskPaths, "produced")) && item.GetProduced() != nil {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "produced",
				Description: "produced can be used only for vehicle or engine",
			})
		}

		if (maskPaths == nil || util.Contains(maskPaths, "produced_exactly")) &&
			item.GetProducedExactly() {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "produced_exactly",
				Description: "produced_exactly can be used only for vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, nameField) {
		nameInputFilter := validation.InputFilter{
			Filters: []validation.FilterInterface{
				&validation.StringTrimFilter{},
				&validation.StringSingleSpaces{},
			},
			Validators: []validation.ValidatorInterface{
				&validation.StringLength{
					Min: schema.ItemNameMinLength,
					Max: schema.ItemNameMaxLength,
				},
			},
		}

		item.Name, problems, err = nameInputFilter.IsValidString(item.GetName())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       nameField,
				Description: fv,
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "full_name") {
		fullNameInputFilter := validation.InputFilter{
			Filters: []validation.FilterInterface{
				&validation.StringTrimFilter{},
				&validation.StringSingleSpaces{},
			},
			Validators: []validation.ValidatorInterface{
				&validation.StringLength{Max: schema.ItemFullNameMaxLength},
			},
		}

		item.FullName, problems, err = fullNameInputFilter.IsValidString(item.GetFullName())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "full_name",
				Description: fv,
			})
		}
	}

	if item.GetItemTypeId() == pb.ItemType_ITEM_TYPE_VEHICLE {
		if item.GetEngineItemId() > 0 &&
			(maskPaths == nil || util.Contains(maskPaths, engineItemIDField)) {
			exists, err := repository.Exists(ctx, query.ItemListOptions{
				ItemID: item.GetEngineItemId(),
				TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDEngine},
			})
			if err != nil {
				return nil, err
			}

			if !exists {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       engineItemIDField,
					Description: fmt.Sprintf("engine `%d` not found", item.GetEngineItemId()),
				})
			}
		}
	} else {
		if item.GetEngineItemId() > 0 && (maskPaths == nil || util.Contains(maskPaths, engineItemIDField)) {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       engineItemIDField,
				Description: "engine_item_id can be used only for vehicle",
			})
		}

		if item.GetEngineInherit() && (maskPaths == nil || util.Contains(maskPaths, engineInheritField)) {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       engineInheritField,
				Description: "engine_inherit can be used only for vehicle",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, catnameField) {
		if util.Contains(
			[]pb.ItemType{pb.ItemType_ITEM_TYPE_CATEGORY, pb.ItemType_ITEM_TYPE_BRAND},
			item.GetItemTypeId(),
		) {
			catnameInputFilter := validation.InputFilter{
				Filters: []validation.FilterInterface{
					&validation.StringTrimFilter{},
					&validation.StringSingleSpaces{},
					&validation.StringSanitizeFilename{},
				},
				Validators: []validation.ValidatorInterface{
					&validation.StringLength{
						Min: schema.ItemCatnameMinLength,
						Max: schema.ItemCatnameMaxLength,
					},
				},
			}

			item.Catname, problems, err = catnameInputFilter.IsValidString(item.GetCatname())
			if err != nil {
				return nil, err
			}

			for _, fv := range problems {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       catnameField,
					Description: fv,
				})
			}

			if len(problems) == 0 {
				exists, err := repository.Exists(
					ctx,
					query.ItemListOptions{Catname: item.GetCatname(), ExcludeID: item.GetId()},
				)
				if err != nil {
					return nil, err
				}

				if exists {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       catnameField,
						Description: fmt.Sprintf("`%s` already exists", item.GetCatname()),
					})
				}
			}
		} else if len(item.GetCatname()) > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       catnameField,
				Description: "catname can be used only for brand or category",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "body") {
		if isVehicleOrEngine {
			bodyInputFilter := validation.InputFilter{
				Filters: []validation.FilterInterface{
					&validation.StringTrimFilter{},
					&validation.StringSingleSpaces{},
				},
				Validators: []validation.ValidatorInterface{
					&validation.StringLength{
						Min: schema.ItemBodyMinLength,
						Max: schema.ItemBodyMaxLength,
					},
				},
			}

			item.Body, problems, err = bodyInputFilter.IsValidString(item.GetBody())
			if err != nil {
				return nil, err
			}

			for _, fv := range problems {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "body",
					Description: fv,
				})
			}
		} else if len(item.GetBody()) > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "body",
				Description: "body can be used only with vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "spec_id") {
		if isVehicleOrEngine {
			if item.GetSpecId() != 0 {
				exists, err := repository.SpecExists(ctx, item.GetSpecId())
				if err != nil {
					return nil, err
				}

				if !exists {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       "spec_id",
						Description: fmt.Sprintf("spec `%d` not found", item.GetSpecId()),
					})
				}
			}
		} else {
			if item.GetSpecId() > 0 {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "spec_id",
					Description: "spec_id can be used only with vehicle or engine",
				})
			}

			if item.GetSpecInherit() {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "spec_inherit",
					Description: "spec_inherit can be used only with vehicle or engine",
				})
			}
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "begin_model_year") {
		if isVehicleOrEngine {
			if item.GetBeginModelYear() > 0 {
				var maxYear int32 = schema.ItemYearMax

				if item.GetEndModelYear() > 0 {
					maxYear = item.GetEndModelYear()
				}

				beginModelYearInputFilter := validation.InputFilter{
					Validators: []validation.ValidatorInterface{
						&validation.Between{Min: schema.ItemYearMin, Max: maxYear},
					},
				}

				item.BeginModelYear, problems, err = beginModelYearInputFilter.IsValidInt32(
					item.GetBeginModelYear(),
				)
				if err != nil {
					return nil, err
				}

				for _, fv := range problems {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       "begin_model_year",
						Description: fv,
					})
				}
			}
		} else if item.GetBeginModelYear() > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "begin_model_year",
				Description: "begin_model_year can be used only with vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "begin_model_year_fraction") {
		if isVehicleOrEngine {
			if len(item.GetBeginModelYearFraction()) > 0 {
				beginModelYearFractionInputFilter := validation.InputFilter{
					Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
					Validators: []validation.ValidatorInterface{
						&validation.InArray{HaystackString: []string{"¼", "½", "¾"}},
					},
				}

				item.BeginModelYearFraction, problems, err = beginModelYearFractionInputFilter.IsValidString(
					item.GetBeginModelYearFraction(),
				)
				if err != nil {
					return nil, err
				}

				for _, fv := range problems {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       "begin_model_year_fraction",
						Description: fv,
					})
				}
			}
		} else if len(item.GetBeginModelYearFraction()) > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "begin_model_year_fraction",
				Description: "begin_model_year_fraction can be used only with vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "end_model_year") {
		if isVehicleOrEngine {
			if item.GetEndModelYear() > 0 {
				var minYear int32 = schema.ItemYearMin

				if item.GetBeginModelYear() > 0 {
					minYear = item.GetBeginModelYear()
				}

				endModelYearInputFilter := validation.InputFilter{
					Validators: []validation.ValidatorInterface{
						&validation.Between{Min: minYear, Max: schema.ItemYearMax},
					},
				}

				item.EndModelYear, problems, err = endModelYearInputFilter.IsValidInt32(
					item.GetEndModelYear(),
				)
				if err != nil {
					return nil, err
				}

				for _, fv := range problems {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       "end_model_year",
						Description: fv,
					})
				}
			}
		} else if item.GetEndModelYear() > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "end_model_year",
				Description: "end_model_year can be used only with vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "end_model_year_fraction") {
		if isVehicleOrEngine {
			if len(item.GetEndModelYearFraction()) > 0 {
				endModelYearFractionInputFilter := validation.InputFilter{
					Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
					Validators: []validation.ValidatorInterface{
						&validation.InArray{HaystackString: []string{"¼", "½", "¾"}},
					},
				}

				item.EndModelYearFraction, problems, err = endModelYearFractionInputFilter.IsValidString(
					item.GetEndModelYearFraction(),
				)
				if err != nil {
					return nil, err
				}

				for _, fv := range problems {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       "end_model_year_fraction",
						Description: fv,
					})
				}
			}
		} else if len(item.GetEndModelYearFraction()) > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "end_model_year_fraction",
				Description: "end_model_year_fraction can be used only with vehicle or engine",
			})
		}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "begin_year")) && item.GetBeginYear() > 0 {
		var maxYear int32 = schema.ItemYearMax

		if item.GetEndYear() > 0 {
			maxYear = item.GetEndYear()
		}

		beginYearInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: schema.ItemYearMin, Max: maxYear},
			},
		}

		item.BeginYear, problems, err = beginYearInputFilter.IsValidInt32(item.GetBeginYear())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "begin_year",
				Description: fv,
			})
		}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "begin_month")) && item.GetBeginMonth() > 0 {
		beginMonthInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: int32(time.January), Max: int32(time.December)},
			},
		}

		item.BeginMonth, problems, err = beginMonthInputFilter.IsValidInt32(item.GetBeginMonth())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "begin_month",
				Description: fv,
			})
		}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "end_year")) && item.GetEndYear() > 0 {
		var minYear int32 = schema.ItemYearMin

		if item.GetBeginYear() > 0 {
			minYear = item.GetBeginYear()
		}

		endYearInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: minYear, Max: schema.ItemYearMax},
			},
		}

		item.EndYear, problems, err = endYearInputFilter.IsValidInt32(item.GetEndYear())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "end_year",
				Description: fv,
			})
		}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "end_month")) && item.GetEndMonth() > 0 {
		endMonthInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: int32(time.January), Max: int32(time.December)},
			},
		}

		item.EndMonth, problems, err = endMonthInputFilter.IsValidInt32(item.GetEndMonth())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "end_month",
				Description: fv,
			})
		}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "produced")) && item.GetProduced() != nil {
		producedInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: 0, Max: math.MaxInt32},
			},
		}

		var produced int32

		produced, problems, err = producedInputFilter.IsValidInt32(item.GetProduced().GetValue())
		if err != nil {
			return nil, err
		}

		item.GetProduced().Value = produced

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "produced",
				Description: fv,
			})
		}
	}

	return result, nil
}
