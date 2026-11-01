package testutil

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	"github.com/stretchr/testify/require"
)

// CreateItem creates a catalogue fixture using the caller's authenticated context.
func CreateItem(ctx context.Context, t *testing.T, client pb.ItemsClient, item *pb.Item) int64 {
	t.Helper()

	response, err := client.CreateItem(ctx, item)
	require.NoError(t, err)
	require.NotZero(t, response.GetId())

	return response.GetId()
}
