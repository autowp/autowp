package validation

// Callback validator.
type Callback struct {
	CallbackString func(value string) ([]string, error)
	CallbackInt32  func(value int32) ([]string, error)
}

// IsValidString IsValidString.
func (s *Callback) IsValidString(value string) ([]string, error) {
	return s.CallbackString(value)
}

// IsValidInt32 IsValidInt32.
func (s *Callback) IsValidInt32(value int32) ([]string, error) {
	return s.CallbackInt32(value)
}
