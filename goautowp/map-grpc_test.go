package goautowp

import (
	"fmt"
	"math/rand"
	"testing"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/genproto/googleapis/type/latlng"
)

func createItemWithPoint(t *testing.T) {
	t.Helper()

	createItemWithPointAt(t, 30, 30)
}

func createItemWithPointAt(t *testing.T, latitude, longitude float64) int64 {
	t.Helper()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	return createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("factory-%d", random.Int()),
		IsGroup:    false,
		ItemTypeId: ItemType_ITEM_TYPE_FACTORY,
		Location:   &latlng.LatLng{Latitude: latitude, Longitude: longitude},
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
			Language: schema.EnglishLanguageCode,
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
			Language:   schema.EnglishLanguageCode,
			PointsOnly: true,
		},
	)
	require.NoError(t, err)
}

// Regression test for a latitude/longitude axis swap (see TestUpdateItemLocationAxisOrder for
// background). Moscow's latitude (~56) and longitude (~38) fall in very different ranges, so a
// bounding-box query built with the axes swapped - or a stored point with the axes swapped -
// would miss it entirely instead of just returning a slightly-off location.
func TestGetPointsAxisOrder(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	const (
		latitude  = 55.7520
		longitude = 37.6175
	)

	itemID := createItemWithPointAt(t, latitude, longitude)
	wantID := fmt.Sprintf("factory%d", itemID)

	client := NewMapClient(conn)

	// Bounds format is "lngLo,latLo,lngHi,latHi". A tight box straddling the point's real
	// location; if bounds parsing or the stored point had swapped axes, this box - built from
	// very different lat/lng ranges - would not intersect it.
	res, err := client.GetPoints(
		ctx,
		&MapGetPointsRequest{
			Bounds:   "37,55,38,56",
			Language: schema.EnglishLanguageCode,
		},
	)
	require.NoError(t, err)

	var found *MapPoint

	for _, point := range res.GetPoints() {
		if point.GetId() == wantID {
			found = point

			break
		}
	}

	require.NotNil(t, found, "point not found within a bounding box around its real location")
	require.InDelta(t, latitude, found.GetLocation().GetLatitude(), 0.0001)
	require.InDelta(t, longitude, found.GetLocation().GetLongitude(), 0.0001)

	// A box covering the swapped coordinates (lat/lng ranges exchanged) must NOT match - if it
	// did, the bounding-box polygon would itself have its axes swapped.
	res, err = client.GetPoints(
		ctx,
		&MapGetPointsRequest{
			Bounds:   "55,37,56,38",
			Language: schema.EnglishLanguageCode,
		},
	)
	require.NoError(t, err)

	for _, point := range res.GetPoints() {
		require.NotEqual(t, wantID, point.GetId(), "point matched a bounding box built from its swapped coordinates")
	}
}
