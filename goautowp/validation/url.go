package validation

import "net/url"

// URL validator.
type URL struct{}

// IsValidString IsValidString.
func (s *URL) IsValidString(value string) ([]string, error) {
	_, err := url.ParseRequestURI(value)
	if err != nil {
		return []string{URLInvalidFormat}, nil //nolint:nilerr
	}

	return []string{}, nil
}

// IsValidInt32 IsValidInt32.
func (s *URL) IsValidInt32(int32) ([]string, error) {
	return []string{}, nil
}
