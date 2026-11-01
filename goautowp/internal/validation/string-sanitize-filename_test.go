package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestStringSanitizeFilenameFilterString(t *testing.T) {
	t.Parallel()

	f := &StringSanitizeFilename{}

	require.Equal(t, "just_test", f.FilterString("just test"))
}

func TestStringSanitizeFilenameFilterInt32(t *testing.T) {
	t.Parallel()

	f := &StringSanitizeFilename{}

	require.EqualValues(t, 42, f.FilterInt32(42))
}
