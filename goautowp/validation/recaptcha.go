package validation

import "github.com/dpapathanasiou/go-recaptcha"

// Recaptcha validator.
type Recaptcha struct {
	ClientIP string
}

// IsValidString IsValidString.
func (s *Recaptcha) IsValidString(value string) ([]string, error) {
	_, err := recaptcha.Confirm(s.ClientIP, value)
	if err != nil {
		return []string{err.Error()}, nil //nolint:nilerr
	}

	return []string{}, nil
}

// IsValidInt32 IsValidInt32.
func (s *Recaptcha) IsValidInt32(int32) ([]string, error) {
	return nil, nil
}
