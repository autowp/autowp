package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestBetweenIsValidString(t *testing.T) {
	t.Parallel()

	validator := &Between{Min: 1, Max: 10}

	_, err := validator.IsValidString("5")
	require.ErrorIs(t, err, ErrNotImplemented)
}

func TestBetweenIsValidInt32(t *testing.T) {
	t.Parallel()

	validator := &Between{Min: 1, Max: 10}

	violations, err := validator.IsValidInt32(5)
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidInt32(0)
	require.NoError(t, err)
	require.Equal(t, []string{"The input is lower than 1"}, violations)

	violations, err = validator.IsValidInt32(11)
	require.NoError(t, err)
	require.Equal(t, []string{"The input is greater than 10"}, violations)
}
