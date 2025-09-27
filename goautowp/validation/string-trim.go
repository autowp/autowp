package validation

import "strings"

// StringTrimFilter filter.
type StringTrimFilter struct{}

// FilterString filter.
func (s *StringTrimFilter) FilterString(value string) string {
	return strings.TrimSpace(value)
}

// FilterInt32 filter.
func (s *StringTrimFilter) FilterInt32(value int32) int32 {
	return value
}
