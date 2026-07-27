package goautowp

import (
	"os"
	"testing"

	"github.com/stretchr/testify/require"
)

func TestHammingDistance(t *testing.T) {
	t.Parallel()

	zero := make([]byte, 32)
	flipped := make([]byte, 32)
	require.Equal(t, 0, hammingDistance(zero, flipped))

	flipped[0] = 0b0000_0001
	flipped[10] = 0b1000_0000
	require.Equal(t, 2, hammingDistance(zero, flipped))
}

func hashFile(t *testing.T, path string) []byte {
	t.Helper()

	file, err := os.Open(path)
	require.NoError(t, err)

	defer func() {
		require.NoError(t, file.Close())
	}()

	hash, err := getFileHash(file)
	require.NoError(t, err)
	require.Len(t, hash, 32)

	return hash
}

// TestGetFileHashSimilarity guards PDQ hash quality: the same photo at a
// different resolution must stay well under the duplicate threshold, while
// an unrelated photo must stay well above it.
func TestGetFileHashSimilarity(t *testing.T) {
	t.Parallel()

	large := hashFile(t, "./test/large.jpg")
	small := hashFile(t, "./test/small.jpg")
	unrelated := hashFile(t, "./test/test.jpg")

	require.LessOrEqual(t, hammingDistance(large, small), 10)
	require.Greater(t, hammingDistance(large, unrelated), threshold)
	require.Greater(t, hammingDistance(small, unrelated), threshold)
}
