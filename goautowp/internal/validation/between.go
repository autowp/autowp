package validation

import (
	"errors"
	"fmt"
)

var ErrNotImplemented = errors.New("not implemented")

// Between validator.
type Between struct {
	Min int32
	Max int32
}

// IsValidString IsValidString.
func (s *Between) IsValidString(_ string) ([]string, error) {
	return nil, ErrNotImplemented
}

// IsValidInt32 IsValidInt32.
func (s *Between) IsValidInt32(value int32) ([]string, error) {
	if value < s.Min {
		return []string{fmt.Sprintf(ValueTooSmall, s.Min)}, nil
	}

	if value > s.Max {
		return []string{fmt.Sprintf(ValueTooBig, s.Max)}, nil
	}

	return []string{}, nil
}
