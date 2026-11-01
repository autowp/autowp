package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestEmailAddressIsValidString(t *testing.T) {
	t.Parallel()

	validator := &EmailAddress{}

	violations, err := validator.IsValidString("user@example.com")
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidString("not-an-email")
	require.NoError(t, err)
	require.Equal(t, []string{EmailAddressInvalidFormat}, violations)
}

func TestEmailAddressIsValidInt32(t *testing.T) {
	t.Parallel()

	validator := &EmailAddress{}

	violations, err := validator.IsValidInt32(1)
	require.NoError(t, err)
	require.Equal(t, []string{EmailAddressInvalidFormat}, violations)
}
