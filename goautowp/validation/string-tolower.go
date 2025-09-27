package validation

import "strings"

// StringToLower filter.
type StringToLower struct{}

// FilterString filter.
func (s *StringToLower) FilterString(value string) string {
	return strings.ToLower(value)
}

// FilterInt32 filter.
func (s *StringToLower) FilterInt32(value int32) int32 {
	return value
}
