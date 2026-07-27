package validation

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestStringSingleSpacesFilterString(t *testing.T) {
	t.Parallel()

	f := &StringSingleSpaces{}

	require.Empty(t, f.FilterString(""))
	require.Equal(t, "hello world", f.FilterString("hello   world"))
	require.Equal(t, "line1\nline2", f.FilterString("line1\r\nline2"))
	require.Equal(t, "a b\nc d", f.FilterString("a    b\nc\t\td"))
}

func TestStringSingleSpacesFilterInt32(t *testing.T) {
	t.Parallel()

	f := &StringSingleSpaces{}

	require.EqualValues(t, 42, f.FilterInt32(42))
}
