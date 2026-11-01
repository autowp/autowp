package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestStringToLowerFilterString(t *testing.T) {
	t.Parallel()

	f := &StringToLower{}

	require.Equal(t, "hello world", f.FilterString("Hello World"))
}

func TestStringToLowerFilterInt32(t *testing.T) {
	t.Parallel()

	f := &StringToLower{}

	require.EqualValues(t, 42, f.FilterInt32(42))
}
