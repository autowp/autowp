package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestStringTrimFilterFilterString(t *testing.T) {
	t.Parallel()

	f := &StringTrimFilter{}

	require.Equal(t, "hello", f.FilterString("  hello  "))
}

func TestStringTrimFilterFilterInt32(t *testing.T) {
	t.Parallel()

	f := &StringTrimFilter{}

	require.EqualValues(t, 42, f.FilterInt32(42))
}
