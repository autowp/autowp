package validation

// NotEmpty validator.
type NotEmpty struct{}

// IsValidString IsValidString.
func (s *NotEmpty) IsValidString(value string) ([]string, error) {
	if len(value) > 0 {
		return []string{}, nil
	}

	return []string{NotEmptyIsEmpty}, nil
}

// IsValidInt32 IsValidInt32.
func (s *NotEmpty) IsValidInt32(value int32) ([]string, error) {
	if value != 0 {
		return []string{}, nil
	}

	return []string{NotEmptyIsEmpty}, nil
}
