package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestIdenticalStringsIsValidString(t *testing.T) {
	t.Parallel()

	validator := &IdenticalStrings{Pattern: "secret"}

	violations, err := validator.IsValidString("secret")
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidString("other")
	require.NoError(t, err)
	require.Equal(t, []string{IdenticalStringsNotSame}, violations)
}

func TestIdenticalStringsIsValidInt32(t *testing.T) {
	t.Parallel()

	validator := &IdenticalStrings{Pattern: "secret"}

	violations, err := validator.IsValidInt32(1)
	require.NoError(t, err)
	require.Equal(t, []string{IdenticalStringsNotSame}, violations)
}
