package validation

import (
	"regexp"
	"strings"
)

var whitespaceRunRegexp = regexp.MustCompile("[[:space:]]+")

// StringSingleSpaces filter.
type StringSingleSpaces struct{}

// FilterString filter.
func (s *StringSingleSpaces) FilterString(value string) string {
	if len(value) == 0 {
		return ""
	}

	value = strings.ReplaceAll(value, "\r", "")
	lines := strings.Split(value, "\n")
	out := make([]string, len(lines))

	for idx, line := range lines {
		out[idx] = whitespaceRunRegexp.ReplaceAllString(line, " ")
	}

	return strings.Join(out, "\n")
}

// FilterInt32 filter.
func (s *StringSingleSpaces) FilterInt32(value int32) int32 {
	return value
}
