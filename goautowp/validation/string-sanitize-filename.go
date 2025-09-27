package validation

import (
	"github.com/autowp/goautowp/filter"
)

// StringSanitizeFilename filter.
type StringSanitizeFilename struct{}

// FilterString filter.
func (s *StringSanitizeFilename) FilterString(value string) string {
	return filter.SanitizeFilename(value)
}

// FilterInt32 filter.
func (s *StringSanitizeFilename) FilterInt32(value int32) int32 {
	return value
}
