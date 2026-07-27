package util

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestMinMax(t *testing.T) {
	t.Parallel()

	minValue, maxValue := MinMax([]int32{3, 1, 4, 1, 5, 9, 2, 6})
	require.EqualValues(t, 1, minValue)
	require.EqualValues(t, 9, maxValue)

	minValue, maxValue = MinMax([]int32{7})
	require.EqualValues(t, 7, minValue)
	require.EqualValues(t, 7, maxValue)
}
