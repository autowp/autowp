package goautowp

import (
	"errors"
	"fmt"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/usercontacts"
	"github.com/autowp/goautowp/util"
	"github.com/autowp/goautowp/validation"
	"google.golang.org/genproto/googleapis/rpc/errdetails"
)

// contactErrorText maps a usercontacts.Parse error to a short user-facing description. The
// Angular form gives localised instant feedback from its own mirror of the registry; this text
// is the rare server-side fallback and follows the existing convention of English descriptions.
func contactErrorText(err error) string {
	switch {
	case errors.Is(err, usercontacts.ErrWrongPlatform):
		return "This link belongs to a different platform"
	case errors.Is(err, usercontacts.ErrNotAProfile):
		return "This link is not a profile page"
	case errors.Is(err, usercontacts.ErrTooLong):
		return "This is too long"
	case errors.Is(err, usercontacts.ErrUnknownPlatform):
		return "Unknown platform"
	default:
		return "This does not look like a valid username or profile link"
	}
}

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

	if util.Contains(maskPaths, "contacts") {
		seen := make(map[UserContactPlatform]bool)

		for i, contact := range s.GetContacts() {
			field := fmt.Sprintf("contacts[%d]", i)
			platform := contact.GetPlatform()

			if platform == UserContactPlatform_USER_CONTACT_PLATFORM_UNSPECIFIED {
				if contact.GetUsername() != "" {
					result = append(result, &errdetails.BadRequest_FieldViolation{
						Field:       field,
						Description: "Select a platform",
					})
				}

				continue
			}

			platformID := schema.UserContactPlatform(platform) //nolint:gosec // proto enum, small range

			username, err := usercontacts.Parse(platformID, contact.GetUsername())
			if err != nil {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       field,
					Description: contactErrorText(err),
				})

				continue
			}

			if username == "" {
				continue // blank row — dropped on save
			}

			if seen[platform] {
				result = append(result, &errdetails.BadRequest_FieldViolation{
					Field:       field,
					Description: "This platform is already listed",
				})

				continue
			}

			seen[platform] = true
			contact.Username = username // normalised in place for the handler to persist
		}
	}

	return result, nil
}
