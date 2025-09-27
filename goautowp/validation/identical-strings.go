package validation

// IdenticalStrings validator.
type IdenticalStrings struct {
	Pattern string
}

// IsValidString IsValidString.
func (s *IdenticalStrings) IsValidString(value string) ([]string, error) {
	if value != s.Pattern {
		return []string{IdenticalStringsNotSame}, nil
	}

	return []string{}, nil
}

// IsValidInt32 IsValidInt32.
func (s *IdenticalStrings) IsValidInt32(int32) ([]string, error) {
	return []string{IdenticalStringsNotSame}, nil
}
