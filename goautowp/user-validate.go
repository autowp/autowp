package goautowp

import (
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
)

func (s *User) Validate( //nolint: funlen
	languages map[string]config.LanguageConfig, maskPaths []string,
) ([]*errdetails.BadRequest_FieldViolation, error) {
	var (
		result   = make([]*errdetails.BadRequest_FieldViolation, 0)
		problems []string
		err      error
	)

	if util.Contains(maskPaths, "language") {
		langs := make([]string, 0, len(languages))
		for lang := range languages {
			langs = append(langs, lang)
		}

		languageInputFilter := validation.InputFilter{
			Filters: []validation.FilterInterface{
				&validation.StringTrimFilter{},
				&validation.StringSingleSpaces{},
			},
			Validators: []validation.ValidatorInterface{
				&validation.InArray{HaystackString: langs},
			},
		}

		s.Language, problems, err = languageInputFilter.IsValidString(s.GetLanguage())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "language",
				Description: fv,
			})
		}
	}

	if util.Contains(maskPaths, "timezone") {
		timezoneInputFilter := validation.InputFilter{
			Filters: []validation.FilterInterface{
				&validation.StringTrimFilter{},
				&validation.StringSingleSpaces{},
			},
			Validators: []validation.ValidatorInterface{
				&validation.InArray{HaystackString: TimeZones()},
			},
		}

		s.Timezone, problems, err = timezoneInputFilter.IsValidString(s.GetTimezone())
		if err != nil {
			return nil, err
		}

		for _, fv := range problems {
			result = append(result, &errdetails.BadRequest_FieldViolation{
				Field:       "timezone",
				Description: fv,
			})
		}
	}

	return result, nil
}
