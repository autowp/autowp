package validation

import (
	"errors"
	"testing"

	"github.com/stretchr/testify/require"
)

var errValidatorTest = errors.New("validator error")

func TestInputFilterIsValidString(t *testing.T) {
	t.Parallel()

	inputFilter := &InputFilter{
		Filters:    []FilterInterface{&StringTrimFilter{}, &StringToLower{}},
		Validators: []ValidatorInterface{&NotEmpty{}, &StringLength{Min: 2, Max: 10}},
	}

	value, violations, err := inputFilter.IsValidString("  Hello  ")
	require.NoError(t, err)
	require.Empty(t, violations)
	require.Equal(t, "hello", value)

	value, violations, err = inputFilter.IsValidString("   ")
	require.NoError(t, err)
	require.Equal(t, []string{NotEmptyIsEmpty}, violations)
	require.Empty(t, value)
}

func TestInputFilterIsValidStringError(t *testing.T) {
	t.Parallel()

	inputFilter := &InputFilter{
		Validators: []ValidatorInterface{&Callback{
			CallbackString: func(string) ([]string, error) {
				return nil, errValidatorTest
			},
		}},
	}

	_, _, err := inputFilter.IsValidString("value")
	require.ErrorIs(t, err, errValidatorTest)
}

func TestInputFilterIsValidInt32(t *testing.T) {
	t.Parallel()

	inputFilter := &InputFilter{
		Filters:    []FilterInterface{&StringTrimFilter{}},
		Validators: []ValidatorInterface{&Between{Min: 1, Max: 10}},
	}

	value, violations, err := inputFilter.IsValidInt32(5)
	require.NoError(t, err)
	require.Empty(t, violations)
	require.EqualValues(t, 5, value)

	value, violations, err = inputFilter.IsValidInt32(20)
	require.NoError(t, err)
	require.Equal(t, []string{"The input is greater than 10"}, violations)
	require.EqualValues(t, 20, value)
}

func TestInputFilterIsValidInt32Error(t *testing.T) {
	t.Parallel()

	inputFilter := &InputFilter{
		Validators: []ValidatorInterface{&Callback{
			CallbackInt32: func(int32) ([]string, error) {
				return nil, errValidatorTest
			},
		}},
	}

	_, _, err := inputFilter.IsValidInt32(1)
	require.ErrorIs(t, err, errValidatorTest)
}
