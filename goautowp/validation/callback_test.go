package validation

import (
	"errors"
	"testing"

	"github.com/stretchr/testify/require"
)

var errCallbackTest = errors.New("callback error")

func TestCallbackIsValidString(t *testing.T) {
	t.Parallel()

	callback := &Callback{
		CallbackString: func(value string) ([]string, error) {
			if value == "bad" {
				return []string{"bad value"}, nil
			}

			return []string{}, nil
		},
	}

	violations, err := callback.IsValidString("good")
	require.NoError(t, err)
	require.Empty(t, violations)

	violations, err = callback.IsValidString("bad")
	require.NoError(t, err)
	require.Equal(t, []string{"bad value"}, violations)
}

func TestCallbackIsValidInt32(t *testing.T) {
	t.Parallel()

	callback := &Callback{
		CallbackInt32: func(int32) ([]string, error) {
			return nil, errCallbackTest
		},
	}

	_, err := callback.IsValidInt32(1)
	require.ErrorIs(t, err, errCallbackTest)
}
