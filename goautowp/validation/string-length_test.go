package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestStringLengthIsValidString(t *testing.T) {
	t.Parallel()

	validator := &StringLength{Min: 2, Max: 5}

	violations, err := validator.IsValidString("abc")
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidString("a")
	require.NoError(t, err)
	require.Equal(t, []string{"The input is less than 2 characters long"}, violations)

	violations, err = validator.IsValidString("abcdef")
	require.NoError(t, err)
	require.Equal(t, []string{"The input is more than 5 characters long"}, violations)
}

func TestStringLengthIsValidInt32(t *testing.T) {
	t.Parallel()

	validator := &StringLength{Min: 2, Max: 5}

	violations, err := validator.IsValidInt32(123)
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidInt32(1)
	require.NoError(t, err)
	require.Equal(t, []string{"The input is less than 2 characters long"}, violations)
}
