package validation

const (
	NotEmptyIsEmpty           = "Value is required and can't be empty"
	EmailAddressInvalidFormat = "The input is not a valid email address"
	URLInvalidFormat          = "The input is not a valid URL"
	StringLengthTooShort      = "The input is less than %d characters long"
	StringLengthTooLong       = "The input is more than %d characters long"
	EmailNotExistsExists      = "E-mail already registered"
	IdenticalStringsNotSame   = "The two given tokens do not match"
	NotInArray                = "The input was not found in the haystack"
	ValueTooSmall             = "The input is lower than %d"
	ValueTooBig               = "The input is greater than %d"
)

type FilterInterface interface {
	FilterString(value string) string
	FilterInt32(value int32) int32
}

type ValidatorInterface interface {
	IsValidString(value string) ([]string, error)
	IsValidInt32(value int32) ([]string, error)
}

type InputFilter struct {
	Filters    []FilterInterface
	Validators []ValidatorInterface
}

// IsValidString IsValidString.
func (s *InputFilter) IsValidString(value string) (string, []string, error) {
	value = filterString(value, s.Filters)

	violations, err := validateString(value, s.Validators)
	if err != nil {
		return "", nil, err
	}

	return value, violations, nil
}

func filterString(value string, filters []FilterInterface) string {
	for _, filter := range filters {
		value = filter.FilterString(value)
	}

	return value
}

func validateString(value string, validators []ValidatorInterface) ([]string, error) {
	result := make([]string, 0)

	for _, validator := range validators {
		violations, err := validator.IsValidString(value)
		if err != nil {
			return nil, err
		}

		if len(violations) > 0 {
			return violations, nil
		}
	}

	return result, nil
}

// IsValidInt32 IsValidInt32.
func (s *InputFilter) IsValidInt32(value int32) (int32, []string, error) {
	value = filterInt32(value, s.Filters)

	violations, err := validateInt32(value, s.Validators)
	if err != nil {
		return 0, nil, err
	}

	return value, violations, nil
}

func filterInt32(value int32, filters []FilterInterface) int32 {
	for _, filter := range filters {
		value = filter.FilterInt32(value)
	}

	return value
}

func validateInt32(value int32, validators []ValidatorInterface) ([]string, error) {
	result := make([]string, 0)

	for _, validator := range validators {
		violations, err := validator.IsValidInt32(value)
		if err != nil {
			return nil, err
		}

		if len(violations) > 0 {
			return violations, nil
		}
	}

	return result, nil
}
