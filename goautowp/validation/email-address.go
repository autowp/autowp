package validation

import "net/mail"

// EmailAddress validator.
type EmailAddress struct{}

// IsValidString IsValidString.
func (s *EmailAddress) IsValidString(value string) ([]string, error) {
	_, err := mail.ParseAddress(value)
	if err != nil {
		return []string{EmailAddressInvalidFormat}, nil //nolint:nilerr
	}

	return []string{}, nil
}

// IsValidInt32 IsValidInt32.
func (s *EmailAddress) IsValidInt32(int32) ([]string, error) {
	return []string{EmailAddressInvalidFormat}, nil
}
