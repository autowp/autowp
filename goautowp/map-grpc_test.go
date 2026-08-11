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

func TestNormalizedLongitudeRanges(t *testing.T) {
	t.Parallel()

	testCases := []struct {
		name       string
		lngLo      float64
		lngHi      float64
		wantRanges [][2]float64
	}{
		{
			name:       "already canonical, no split needed",
			lngLo:      10,
			lngHi:      20,
			wantRanges: [][2]float64{{10, 20}},
		},
		{
			name:       "wide low-zoom view, still within canonical bounds",
			lngLo:      -170,
			lngHi:      170,
			wantRanges: [][2]float64{{-170, 170}},
		},
		{
			name:       "straddles the antimeridian",
			lngLo:      170,
			lngHi:      190,
			wantRanges: [][2]float64{{170, 180}, {-180, -170}},
		},
		{
			name:       "panned one world copy to the right",
			lngLo:      300,
			lngHi:      340,
			wantRanges: [][2]float64{{-60, -20}},
		},
		{
			name:       "panned one world copy to the left",
			lngLo:      -340,
			lngHi:      -300,
			wantRanges: [][2]float64{{20, 60}},
		},
		{
			name:       "exactly a full world",
			lngLo:      -60,
			lngHi:      300,
			wantRanges: [][2]float64{{-180, 180}},
		},
		{
			name:       "much more than a full world",
			lngLo:      -600,
			lngHi:      600,
			wantRanges: [][2]float64{{-180, 180}},
		},
	}

	for _, testCase := range testCases {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			got := normalizedLongitudeRanges(testCase.lngLo, testCase.lngHi)

			require.Len(t, got, len(testCase.wantRanges))

			for i, wantRange := range testCase.wantRanges {
				require.InDelta(t, wantRange[0], got[i][0], 0.0001)
				require.InDelta(t, wantRange[1], got[i][1], 0.0001)
			}
		})
	}
}

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

// Regression test for a low-zoom map bug: at low zoom the visible world spans close to or more
// than 180deg of longitude, and Leaflet's getBounds().toBBoxString() reports that span literally
// (e.g. "-170,-80,170,80" for a near-world view centered on longitude 0). The backend used to
// build that bounding box as a *geography* polygon, whose edges are interpolated along the
// shortest great-circle arc - for an edge wider than 180deg, that silently wraps the short way
// around the globe, so the query matched only a narrow sliver near the antimeridian instead of the
// intended wide view, and points anywhere near the center of the map (e.g. longitude 0) were
// invisible.
func TestGetPointsWideLowZoomBounds(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	// Center of a wide, low-zoom view - the exact case that used to vanish.
	itemID := createItemWithPointAt(t, 10, 0)
	wantID := fmt.Sprintf("factory%d", itemID)

	client := NewMapClient(conn)

	res, err := client.GetPoints(
		ctx,
		&MapGetPointsRequest{
			Bounds:   "-170,-80,170,80",
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

	require.NotNil(t, found, "point at the center of a wide low-zoom view was not returned")
}

// Regression test for a world-wrap panning bug: Leaflet allows continuous horizontal panning past
// +-180deg into repeated "world copies", so a user panned one world-width east of a point sees
// bounds like (300, ..., 340, ...) instead of (-60, ..., -20, ...) for the same visible strip.
// Stored points always use canonical [-180, 180] longitude, so without normalizing the query
// bounds first, a point at longitude -40 would never match a query box of (300, 340).
func TestGetPointsWorldWrapPanning(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	itemID := createItemWithPointAt(t, 10, -40)
	wantID := fmt.Sprintf("factory%d", itemID)

	client := NewMapClient(conn)

	res, err := client.GetPoints(
		ctx,
		&MapGetPointsRequest{
			// One world-width (360) east of (-60, -20), the canonical range containing -40.
			Bounds:   "300,-80,340,80",
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

	require.NotNil(
		t, found, "point was not returned for a bounding box panned one world copy over from its real location",
	)
}
