package goautowp

import (
	"fmt"
	"math/rand"
	"testing"
	"time"

	"github.com/stretchr/testify/require"
	"google.golang.org/genproto/googleapis/type/latlng"
)

func createItemWithPoint(t *testing.T) {
	t.Helper()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	createItem(t, conn, cnt, &APIItem{
		Name:       fmt.Sprintf("factory-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_FACTORY,
		Location:   &latlng.LatLng{Latitude: 30, Longitude: 30},
	})
}

func TestGetPoints(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	createItemWithPoint(t)

	client := NewMapClient(conn)

	_, err := client.GetPoints(
		ctx,
		&MapGetPointsRequest{
			Bounds:   "0,0,60,60",
			Language: "en",
		},
	)
	require.NoError(t, err)
}

func TestGetPointsOnly(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	createItemWithPoint(t)

	client := NewMapClient(conn)

	_, err := client.GetPoints(
		ctx,
		&MapGetPointsRequest{
			Bounds:     "0,0,60,60",
			Language:   "en",
			PointsOnly: true,
		},
	)
	require.NoError(t, err)
}
