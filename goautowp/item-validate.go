package goautowp

import (
	"context"
	"errors"
	"fmt"
	"math"
	"time"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
	"google.golang.org/protobuf/types/known/wrapperspb"
)

func (s *APIItem) Validate( //nolint: maintidx
	ctx context.Context, repository *items.Repository, maskPaths []string, roles []string,
) ([]*errdetails.BadRequest_FieldViolation, error) {
	if maskPaths == nil || util.Contains(maskPaths, "is_group") {
		switch s.GetItemTypeId() {
		case ItemType_ITEM_TYPE_VEHICLE, ItemType_ITEM_TYPE_ENGINE, ItemType_ITEM_TYPE_UNKNOWN:
			if s.GetId() > 0 {
				_, err := repository.ItemParent(ctx, &query.ItemParentListOptions{
					ParentID: s.GetId(),
				}, items.ItemParentFields{})
				if err != nil && !errors.Is(err, items.ErrItemNotFound) {
					return nil, err
				}

				if err == nil {
					s.IsGroup = true
				}
			}

		case ItemType_ITEM_TYPE_CATEGORY,
			ItemType_ITEM_TYPE_TWINS,
			ItemType_ITEM_TYPE_BRAND,
			ItemType_ITEM_TYPE_FACTORY,
			ItemType_ITEM_TYPE_MUSEUM,
			ItemType_ITEM_TYPE_PERSON,
			ItemType_ITEM_TYPE_COPYRIGHT:
			s.IsGroup = true
		}
	}

	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	switch s.GetItemTypeId() {
	case ItemType_ITEM_TYPE_VEHICLE,
		ItemType_ITEM_TYPE_ENGINE,
		ItemType_ITEM_TYPE_CATEGORY,
		ItemType_ITEM_TYPE_TWINS,
		ItemType_ITEM_TYPE_BRAND,
		ItemType_ITEM_TYPE_FACTORY,
		ItemType_ITEM_TYPE_MUSEUM,
		ItemType_ITEM_TYPE_PERSON,
		ItemType_ITEM_TYPE_COPYRIGHT:
	case ItemType_ITEM_TYPE_UNKNOWN:
		fallthrough
	default:
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "item_type_id",
			Description: "unexpected `item_type_id`",
		})
	}

	canEditEngine := util.Contains(roles, users.RoleCarsModer)

	if maskPaths == nil || util.Contains(maskPaths, "engine_inherit") {
		if s.GetItemTypeId() != ItemType_ITEM_TYPE_VEHICLE {
			if s.GetId() > 0 || s.GetEngineInherit() {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "engine_inherit",
					Description: "engine_inherit can be used only for vehicle",
				})
			}
		} else if (s.GetId() > 0 || s.GetId() == 0 && s.GetEngineInherit()) && !canEditEngine {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "engine_inherit",
				Description: "permission denied",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "engine_item_id") {
		switch {
		case s.GetItemTypeId() != ItemType_ITEM_TYPE_VEHICLE:
			if s.GetId() > 0 || s.GetEngineItemId() > 0 {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "engine_item_id",
					Description: "engine_item_id can be used only for vehicle",
				})
			}
		case (s.GetId() > 0 || s.GetId() == 0 && s.GetEngineItemId() > 0) && !canEditEngine:
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "engine_item_id",
				Description: "permission denied",
			})
		default:
			if s.GetEngineItemId() > 0 {
				_, err = repository.Item(ctx, &query.ItemListOptions{
					ItemID: s.GetEngineItemId(),
					TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDEngine},
				}, nil)
				if err != nil && !errors.Is(err, items.ErrItemNotFound) {
					return nil, err
				}

				if err != nil {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       "engine_item_id",
						Description: err.Error(),
					})
				}
			}
		}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "today")) &&
		s.GetEndYear() > 0 && s.GetEndYear() < int32(time.Now().Year()) { //nolint: gosec
		s.Today = &wrapperspb.BoolValue{Value: false}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "spec_id")) && s.GetSpecId() > 0 &&
		s.GetSpecInherit() {
		s.SpecId = 0
	}

	if (maskPaths == nil || util.Contains(maskPaths, "location")) &&
		!util.Contains(
			[]ItemType{ItemType_ITEM_TYPE_FACTORY, ItemType_ITEM_TYPE_MUSEUM},
			s.GetItemTypeId(),
		) {
		if s.GetLocation() != nil {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "location",
				Description: "location can be used only for factory or museum",
			})
		}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "full_name")) &&
		s.GetItemTypeId() != ItemType_ITEM_TYPE_BRAND && len(s.GetFullName()) > 0 {
		result = append(result, &errdetails.BadRequest_FieldViolation{
			Field:       "full_name",
			Description: "full_name can be used only for brand",
		})
	}

	if !util.Contains(
		[]ItemType{ItemType_ITEM_TYPE_VEHICLE, ItemType_ITEM_TYPE_ENGINE},
		s.GetItemTypeId(),
	) {
		if (maskPaths == nil || util.Contains(maskPaths, "is_concept")) && s.GetIsConcept() {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "is_concept",
				Description: "is_concept can be used only for vehicle or engine",
			})
		}

		if (maskPaths == nil || util.Contains(maskPaths, "is_concept_inherit")) &&
			s.GetIsConceptInherit() {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "is_concept_inherit",
				Description: "is_concept_inherit can be used only for vehicle or engine",
			})
		}

		if (maskPaths == nil || util.Contains(maskPaths, "produced")) && s.GetProduced() != nil {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "produced",
				Description: "produced can be used only for vehicle or engine",
			})
		}

		if (maskPaths == nil || util.Contains(maskPaths, "produced_exactly")) &&
			s.GetProducedExactly() {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "produced_exactly",
				Description: "produced_exactly can be used only for vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "name") {
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

		s.Name, problems, err = nameInputFilter.IsValidString(s.GetName())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "name",
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

		s.FullName, problems, err = fullNameInputFilter.IsValidString(s.GetFullName())
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

	if s.GetItemTypeId() == ItemType_ITEM_TYPE_VEHICLE {
		if s.GetEngineItemId() > 0 &&
			(maskPaths == nil || util.Contains(maskPaths, "engine_item_id")) {
			exists, err := repository.Exists(ctx, query.ItemListOptions{
				ItemID: s.GetEngineItemId(),
				TypeID: []schema.ItemTableItemTypeID{schema.ItemTableItemTypeIDEngine},
			})
			if err != nil {
				return nil, err
			}

			if !exists {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "engine_item_id",
					Description: fmt.Sprintf("engine `%d` not found", s.GetEngineItemId()),
				})
			}
		}
	} else {
		if s.GetEngineItemId() > 0 && (maskPaths == nil || util.Contains(maskPaths, "engine_item_id")) {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "engine_item_id",
				Description: "engine_item_id can be used only for vehicle",
			})
		}

		if s.GetEngineInherit() && (maskPaths == nil || util.Contains(maskPaths, "engine_inherit")) {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "engine_inherit",
				Description: "engine_inherit can be used only for vehicle",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "catname") {
		if util.Contains(
			[]ItemType{ItemType_ITEM_TYPE_CATEGORY, ItemType_ITEM_TYPE_BRAND},
			s.GetItemTypeId(),
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

			s.Catname, problems, err = catnameInputFilter.IsValidString(s.GetCatname())
			if err != nil {
				return nil, err
			}

			for _, fv := range problems {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "catname",
					Description: fv,
				})
			}

			if len(problems) == 0 {
				exists, err := repository.Exists(
					ctx,
					query.ItemListOptions{Catname: s.GetCatname(), ExcludeID: s.GetId()},
				)
				if err != nil {
					return nil, err
				}

				if exists {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       "catname",
						Description: fmt.Sprintf("`%s` already exists", s.GetCatname()),
					})
				}
			}
		} else if len(s.GetCatname()) > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "catname",
				Description: "catname can be used only for brand or category",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "body") {
		if util.Contains(
			[]ItemType{ItemType_ITEM_TYPE_VEHICLE, ItemType_ITEM_TYPE_ENGINE},
			s.GetItemTypeId(),
		) {
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

			s.Body, problems, err = bodyInputFilter.IsValidString(s.GetBody())
			if err != nil {
				return nil, err
			}

			for _, fv := range problems {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "body",
					Description: fv,
				})
			}
		} else if len(s.GetBody()) > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "body",
				Description: "body can be used only with vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "spec_id") {
		if util.Contains(
			[]ItemType{ItemType_ITEM_TYPE_VEHICLE, ItemType_ITEM_TYPE_ENGINE},
			s.GetItemTypeId(),
		) {
			if s.GetSpecId() != 0 {
				exists, err := repository.SpecExists(ctx, s.GetSpecId())
				if err != nil {
					return nil, err
				}

				if !exists {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       "spec_id",
						Description: fmt.Sprintf("spec `%d` not found", s.GetSpecId()),
					})
				}
			}
		} else {
			if s.GetSpecId() > 0 {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "spec_id",
					Description: "spec_id can be used only with vehicle or engine",
				})
			}

			if s.GetSpecInherit() {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       "spec_inherit",
					Description: "spec_inherit can be used only with vehicle or engine",
				})
			}
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "begin_model_year") {
		if util.Contains(
			[]ItemType{ItemType_ITEM_TYPE_VEHICLE, ItemType_ITEM_TYPE_ENGINE},
			s.GetItemTypeId(),
		) {
			if s.GetBeginModelYear() > 0 {
				var maxYear int32 = schema.ItemYearMax

				if s.GetEndModelYear() > 0 {
					maxYear = s.GetEndModelYear()
				}

				beginModelYearInputFilter := validation.InputFilter{
					Validators: []validation.ValidatorInterface{
						&validation.Between{Min: schema.ItemYearMin, Max: maxYear},
					},
				}

				s.BeginModelYear, problems, err = beginModelYearInputFilter.IsValidInt32(
					s.GetBeginModelYear(),
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
		} else if s.GetBeginModelYear() > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "begin_model_year",
				Description: "begin_model_year can be used only with vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "begin_model_year_fraction") {
		if util.Contains(
			[]ItemType{ItemType_ITEM_TYPE_VEHICLE, ItemType_ITEM_TYPE_ENGINE},
			s.GetItemTypeId(),
		) {
			if len(s.GetBeginModelYearFraction()) > 0 {
				beginModelYearFractionInputFilter := validation.InputFilter{
					Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
					Validators: []validation.ValidatorInterface{
						&validation.InArray{HaystackString: []string{"¼", "½", "¾"}},
					},
				}

				s.BeginModelYearFraction, problems, err = beginModelYearFractionInputFilter.IsValidString(
					s.GetBeginModelYearFraction(),
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
		} else if len(s.GetBeginModelYearFraction()) > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "begin_model_year_fraction",
				Description: "begin_model_year_fraction can be used only with vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "end_model_year") {
		if util.Contains(
			[]ItemType{ItemType_ITEM_TYPE_VEHICLE, ItemType_ITEM_TYPE_ENGINE},
			s.GetItemTypeId(),
		) {
			if s.GetEndModelYear() > 0 {
				var minYear int32 = schema.ItemYearMin

				if s.GetBeginModelYear() > 0 {
					minYear = s.GetBeginModelYear()
				}

				endModelYearInputFilter := validation.InputFilter{
					Validators: []validation.ValidatorInterface{
						&validation.Between{Min: minYear, Max: schema.ItemYearMax},
					},
				}

				s.EndModelYear, problems, err = endModelYearInputFilter.IsValidInt32(
					s.GetEndModelYear(),
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
		} else if s.GetEndModelYear() > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "end_model_year",
				Description: "end_model_year can be used only with vehicle or engine",
			})
		}
	}

	if maskPaths == nil || util.Contains(maskPaths, "end_model_year_fraction") {
		if util.Contains(
			[]ItemType{ItemType_ITEM_TYPE_VEHICLE, ItemType_ITEM_TYPE_ENGINE},
			s.GetItemTypeId(),
		) {
			if len(s.GetEndModelYearFraction()) > 0 {
				endModelYearFractionInputFilter := validation.InputFilter{
					Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
					Validators: []validation.ValidatorInterface{
						&validation.InArray{HaystackString: []string{"¼", "½", "¾"}},
					},
				}

				s.EndModelYearFraction, problems, err = endModelYearFractionInputFilter.IsValidString(
					s.GetEndModelYearFraction(),
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
		} else if len(s.GetEndModelYearFraction()) > 0 {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "end_model_year_fraction",
				Description: "end_model_year_fraction can be used only with vehicle or engine",
			})
		}
	}

	if (maskPaths == nil || util.Contains(maskPaths, "begin_year")) && s.GetBeginYear() > 0 {
		var maxYear int32 = schema.ItemYearMax

		if s.GetEndYear() > 0 {
			maxYear = s.GetEndYear()
		}

		beginYearInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: schema.ItemYearMin, Max: maxYear},
			},
		}

		s.BeginYear, problems, err = beginYearInputFilter.IsValidInt32(s.GetBeginYear())
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

	if (maskPaths == nil || util.Contains(maskPaths, "begin_month")) && s.GetBeginMonth() > 0 {
		beginMonthInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: int32(time.January), Max: int32(time.December)},
			},
		}

		s.BeginMonth, problems, err = beginMonthInputFilter.IsValidInt32(s.GetBeginMonth())
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

	if (maskPaths == nil || util.Contains(maskPaths, "end_year")) && s.GetEndYear() > 0 {
		var minYear int32 = schema.ItemYearMin

		if s.GetBeginYear() > 0 {
			minYear = s.GetBeginYear()
		}

		endYearInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: minYear, Max: schema.ItemYearMax},
			},
		}

		s.EndYear, problems, err = endYearInputFilter.IsValidInt32(s.GetEndYear())
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

	if (maskPaths == nil || util.Contains(maskPaths, "end_month")) && s.GetEndMonth() > 0 {
		endMonthInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: int32(time.January), Max: int32(time.December)},
			},
		}

		s.EndMonth, problems, err = endMonthInputFilter.IsValidInt32(s.GetEndMonth())
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

	if (maskPaths == nil || util.Contains(maskPaths, "produced")) && s.GetProduced() != nil {
		producedInputFilter := validation.InputFilter{
			Validators: []validation.ValidatorInterface{
				&validation.Between{Min: 0, Max: math.MaxInt32},
			},
		}

		var produced int32

		produced, problems, err = producedInputFilter.IsValidInt32(s.GetProduced().GetValue())
		if err != nil {
			return nil, err
		}

		s.GetProduced().Value = produced

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "produced",
				Description: fv,
			})
		}
	}

	return result, nil
}
