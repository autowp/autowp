package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestURLIsValidString(t *testing.T) {
	t.Parallel()

	validator := &URL{}

	violations, err := validator.IsValidString("https://example.com/path")
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidString("not a url")
	require.NoError(t, err)
	require.Equal(t, []string{URLInvalidFormat}, violations)
}

func TestURLIsValidInt32(t *testing.T) {
	t.Parallel()

	validator := &URL{}

	violations, err := validator.IsValidInt32(1)
	require.NoError(t, err)
	require.Empty(t, violations)
}
