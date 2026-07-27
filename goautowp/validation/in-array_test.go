package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestInArrayIsValidString(t *testing.T) {
	t.Parallel()

	validator := &InArray{HaystackString: []string{"a", "b", "c"}}

	violations, err := validator.IsValidString("b")
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidString("z")
	require.NoError(t, err)
	require.Equal(t, []string{NotInArray}, violations)
}

func TestInArrayIsValidInt32(t *testing.T) {
	t.Parallel()

	validator := &InArray{HaystackInt32: []int32{1, 2, 3}}

	violations, err := validator.IsValidInt32(2)
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = validator.IsValidInt32(9)
	require.NoError(t, err)
	require.Equal(t, []string{NotInArray}, violations)
}
