package storage

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestPatternStrategy(t *testing.T) {
	t.Parallel()

	testCases := []struct {
		Pattern   string
		Result    string
		Extension string
	}{
		{"", "_.jpg", "jpg"}, // "0.jpg"
		{"just.test", "just.test.jpg", "jpg"},
		{"./test/./test/.", "test/test.jpg", "jpg"},
		{"../test/../test/..", "test/test.jpg", "jpg"},
		{"../test////test/..", "test/test.jpg", "jpg"},
	}

	for _, tc := range testCases {
		strategy := NamingStrategyPattern{}
		generated := strategy.Generate(GenerateOptions{
			Pattern:   tc.Pattern,
			Extension: tc.Extension,
		})
		require.Equal(t, tc.Result, generated)
	}
}

func TestSerialStrategy(t *testing.T) {
	t.Parallel()

	strategy := NamingStrategySerial{}

	generated := strategy.Generate(GenerateOptions{
		Index:     0,
		Count:     10,
		Extension: "png",
	})
	require.Equal(t, "11.png", generated)

	generated = strategy.Generate(GenerateOptions{
		Index:     2,
		Count:     10,
		Extension: "png",
	})
	require.Equal(t, "11_2.png", generated)
}
