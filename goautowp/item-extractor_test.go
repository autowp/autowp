package goautowp

import (
	"testing"

	"github.com/stretchr/testify/require"
)

// The caller may leave the fields out entirely, and proto.Clone of an absent message gives back a
// zero message that must not be written to - so the nil case is the one worth pinning down.
func TestPreviewPictureFieldsWithoutRequestedFields(t *testing.T) {
	t.Parallel()

	medium := previewPictureFields(nil, false)
	require.True(t, medium.GetNameText())
	require.True(t, medium.GetThumbMedium())
	require.False(t, medium.GetThumbLarge())

	large := previewPictureFields(nil, true)
	require.True(t, large.GetThumbLarge())
	require.False(t, large.GetThumbMedium())
}

// What the caller did ask for survives, and their message is not modified.
func TestPreviewPictureFieldsKeepsRequestedFields(t *testing.T) {
	t.Parallel()

	requested := &PictureFields{Image: true, ThumbLarge: true}

	fields := previewPictureFields(requested, false)

	require.True(t, fields.GetImage())
	require.True(t, fields.GetThumbMedium())
	require.False(t, fields.GetThumbLarge())

	require.True(t, requested.GetThumbLarge())
	require.False(t, requested.GetNameText())
}
