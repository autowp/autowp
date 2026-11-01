package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestNotEmptyIsValidString(t *testing.T) {
	t.Parallel()

	validator := &NotEmpty{}

	violations, err := validator.IsValidString("value")
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidString("")
	require.NoError(t, err)
	require.Equal(t, []string{NotEmptyIsEmpty}, violations)
}

func TestNotEmptyIsValidInt32(t *testing.T) {
	t.Parallel()

	validator := &NotEmpty{}

	violations, err := validator.IsValidInt32(1)
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidInt32(0)
	require.NoError(t, err)
	require.Equal(t, []string{NotEmptyIsEmpty}, violations)
}
