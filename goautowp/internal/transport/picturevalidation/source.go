package picturevalidation

import (
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/validation"
)

const pictureSourceURLMaxLength = 500

// LicenseRequiresSourceURL reports whether a licence is a public/open claim (CC family,
// public domain) rather than the default "unset" or "all rights reserved" - such a claim needs a
// source URL a moderator or reuser can check, unlike "all rights reserved" which grants no reuse
// rights to verify in the first place.
func LicenseRequiresSourceURL(license pb.PictureLicense) bool {
	return license != pb.PictureLicense_PICTURE_LICENSE_UNKNOWN &&
		license != pb.PictureLicense_PICTURE_LICENSE_ALL_RIGHTS_RESERVED
}

// ValidateSourceURL trims and validates an optional absolute http(s) source URL. An empty
// string is valid and clears the field. The returned problems (if any) are human-readable and
// caller-formatted (gRPC status vs. REST field-violation map).
func ValidateSourceURL(raw string) (string, []string, error) {
	urlInputFilter := validation.InputFilter{
		Filters: []validation.FilterInterface{&validation.StringTrimFilter{}},
		Validators: []validation.ValidatorInterface{
			&validation.StringLength{Max: pictureSourceURLMaxLength},
		},
	}

	trimmed, problems, err := urlInputFilter.IsValidString(raw)
	if err != nil {
		return "", nil, err
	}

	if trimmed != "" {
		urlProblems, err := (&validation.URL{}).IsValidString(trimmed)
		if err != nil {
			return "", nil, err
		}

		problems = append(problems, urlProblems...)
	}

	return trimmed, problems, nil
}
