package validation

import (
	"fmt"
	"strconv"
)

// StringLength validator.
type StringLength struct {
	Min int
	Max int
}

// IsValidString IsValidString.
func (s *StringLength) IsValidString(value string) ([]string, error) {
	l := len(value)
	if l < s.Min {
		return []string{fmt.Sprintf(StringLengthTooShort, s.Min)}, nil
	}

	if l > s.Max {
		return []string{fmt.Sprintf(StringLengthTooLong, s.Max)}, nil
	}

	return []string{}, nil
}

// IsValidInt32 IsValidInt32.
func (s *StringLength) IsValidInt32(value int32) ([]string, error) {
	return s.IsValidString(strconv.FormatInt(int64(value), 10))
}
