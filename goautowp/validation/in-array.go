package validation

import "github.com/autowp/goautowp/util"

// InArray validator.
type InArray struct {
	HaystackString []string
	HaystackInt32  []int32
}

// IsValidString IsValidString.
func (s *InArray) IsValidString(value string) ([]string, error) {
	if !util.Contains(s.HaystackString, value) {
		return []string{NotInArray}, nil
	}

	return []string{}, nil
}

// IsValidInt32 IsValidInt32.
func (s *InArray) IsValidInt32(value int32) ([]string, error) {
	if !util.Contains(s.HaystackInt32, value) {
		return []string{NotInArray}, nil
	}

	return []string{}, nil
}
