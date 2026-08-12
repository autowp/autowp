package goautowp

import (
	"context"
	"fmt"
	"math"
	"math/rand"
	"net"
	"strconv"
	"testing"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/jackc/pgtype"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
	"github.com/twpayne/go-geom"
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

// createPictureWithPointAt inserts an accepted picture directly at the given standard-convention
// (latitude, longitude), bypassing the upload pipeline - mirrors the direct schema.PictureRow
// insertion pattern in pictures/repository_test.go's createTestPicture, since GetPicturePoints
// only cares about status/point/identity, not the rest of the upload flow.
func createPictureWithPointAt(t *testing.T, latitude, longitude float64) string {
	t.Helper()

	ctx := t.Context()

	goquDB, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	identity := "m" + strconv.Itoa(int(random.Uint32()%100000))

	point, err := geom.NewPoint(geom.XY).SetCoords(geom.Coord{longitude, latitude})
	require.NoError(t, err)

	point.SetSRID(schema.SRID)

	var pgIP pgtype.Inet

	err = pgIP.Set(net.IPv4(127, 0, 0, 1))
	require.NoError(t, err)

	var id int64

	success, err := goquDB.Insert(schema.PictureTable).Rows(schema.PictureRow{
		Identity:  identity,
		Status:    schema.PictureStatusAccepted,
		IP:        pgIP,
		CreatedAt: time.Now(),
		Point:     schema.NullPoint{Point: *point, Valid: true},
	}).Returning(schema.PictureTableIDCol).Executor().ScanValContext(ctx, &id)
	require.NoError(t, err)
	require.True(t, success)

	// Without this, repeated local runs against the same persistent Postgres instance accumulate
	// picture rows across invocations (this test package's DB isn't reset between `go test`
	// runs), which can eventually cause two unrelated runs' points to land in the same grid cell
	// by chance and make TestGetPicturePointsSingle/Cluster flaky.
	t.Cleanup(func() {
		_, err := goquDB.Delete(schema.PictureTable).
			Where(schema.PictureTableIDCol.Eq(id)).
			Executor().ExecContext(context.Background())
		assert.NoError(t, err)
	})

	return identity
}

// Regression-style coverage for GetPicturePoints: a picture alone in its grid cell must come back
// as an individual, clickable MapSinglePicture (not folded into a cluster).
//
// The picture's position is jittered a little within a fixed viewport (rather than using fixed
// coordinates) so repeated local test runs against the same persistent Postgres instance don't
// pile up pictures at the exact same spot and turn "alone in its cell" into "clustered with a
// leftover picture from a previous run" - a real, if self-inflicted, failure mode observed while
// developing this test.
func TestGetPicturePointsSingle(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	// Viewport around London; the picture is placed within its central quarter so it's never near
	// a grid-cell edge regardless of the jitter.
	const bounds = "-1,50.5,0.9,52.5"

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	latitude := 51.0 + random.Float64()*0.5
	longitude := -0.5 + random.Float64()*1.0

	identity := createPictureWithPointAt(t, latitude, longitude)

	client := NewMapClient(conn)

	res, err := client.GetPicturePoints(ctx, &MapGetPicturePointsRequest{Bounds: bounds})
	require.NoError(t, err)

	var found *MapSinglePicture

	for _, point := range res.GetPoints() {
		if picture := point.GetPicture(); picture != nil && picture.GetIdentity() == identity {
			found = picture

			break
		}
	}

	require.NotNil(t, found, "lone picture in the viewport was not returned as an individual point")
	require.InDelta(t, latitude, found.GetLocation().GetLatitude(), 0.01)
	require.InDelta(t, longitude, found.GetLocation().GetLongitude(), 0.01)
}

// Several pictures placed close enough together to share a grid cell must come back as one
// MapPictureCluster with the correct count, not as separate individual points. The cluster's base
// position is jittered per run for the same reason as TestGetPicturePointsSingle above.
func TestGetPicturePointsCluster(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	// Viewport around Tokyo - a different area from TestGetPicturePointsSingle (London), to avoid
	// any chance of overlap between the two tests' jittered positions.
	const (
		boundsLngLo  = 139.0
		boundsLatLo  = 35.0
		boundsLngHi  = 140.0
		boundsLatHi  = 36.0
		bounds       = "139,35,140,36"
		pictureCount = 5
	)

	// Placed dead-center of a random interior grid cell (matching GetPicturePoints's own
	// pictureGridSize) rather than anywhere in the viewport: near a cell edge, the small spread
	// between the pictureCount pictures below could straddle two cells and split the cluster,
	// which happened intermittently before this fix.
	cellLng := (boundsLngHi - boundsLngLo) / pictureGridSize
	cellLat := (boundsLatHi - boundsLatLo) / pictureGridSize

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	cellLngIndex := 2 + random.Intn(pictureGridSize-4)
	cellLatIndex := 2 + random.Intn(pictureGridSize-4)
	baseLongitude := boundsLngLo + (float64(cellLngIndex)+0.5)*cellLng
	baseLatitude := boundsLatLo + (float64(cellLatIndex)+0.5)*cellLat

	for i := range pictureCount {
		createPictureWithPointAt(t, baseLatitude+float64(i)*0.001, baseLongitude+float64(i)*0.001)
	}

	client := NewMapClient(conn)

	res, err := client.GetPicturePoints(ctx, &MapGetPicturePointsRequest{Bounds: bounds})
	require.NoError(t, err)

	// The cluster's reported location is the average of its pictures' coordinates, so the expected
	// center is offset from the base by the mean of the per-picture spread (i*0.001 for
	// i in [0, pictureCount)), not the base itself.
	wantLatitude := baseLatitude + float64(pictureCount-1)/2*0.001
	wantLongitude := baseLongitude + float64(pictureCount-1)/2*0.001

	var found *MapPictureCluster

	for _, point := range res.GetPoints() {
		// Matches on both a tight location window and the exact expected count: on a shared,
		// non-reset test database, count alone or location alone could coincidentally match an
		// unrelated cluster left over from another test run.
		if cluster := point.GetCluster(); cluster != nil &&
			cluster.GetCount() == pictureCount &&
			math.Abs(cluster.GetLocation().GetLatitude()-wantLatitude) < 0.001 &&
			math.Abs(cluster.GetLocation().GetLongitude()-wantLongitude) < 0.001 {
			found = cluster

			break
		}
	}

	require.NotNil(t, found, "colocated pictures were not returned as a cluster")
	require.EqualValues(t, pictureCount, found.GetCount())
	require.InDelta(t, wantLatitude, found.GetLocation().GetLatitude(), 0.0001)
	require.InDelta(t, wantLongitude, found.GetLocation().GetLongitude(), 0.0001)
}

// GetPicturePoints with individual: true must skip grid clustering entirely and return every
// matching picture as a flat MapSinglePicture, even when they'd otherwise fragment into several
// small sub-clusters within a tight bbox. This is what resolveStuckCluster in map.component.ts
// relies on: without it, re-querying a small bbox around a max-zoom cluster could still come back
// with several tiny sub-clusters, rendered as a popup full of unusable "+N" placeholders instead
// of a clickable list of pictures.
func TestGetPicturePointsIndividual(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	// Tight enough (comparable to CLUSTER_RESOLVE_DELTA in map.component.ts) that a plain
	// grid-clustered request over it would fragment even a handful of pictures into multiple small
	// sub-clusters instead of individual points.
	const (
		centerLat    = -33.85
		centerLng    = 151.2
		delta        = 0.0005
		pictureCount = 6
	)

	bounds := fmt.Sprintf("%f,%f,%f,%f", centerLng-delta, centerLat-delta, centerLng+delta, centerLat+delta)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	identities := make(map[string]bool, pictureCount)

	for range pictureCount {
		latitude := centerLat + (random.Float64()*2-1)*delta*0.8
		longitude := centerLng + (random.Float64()*2-1)*delta*0.8
		identities[createPictureWithPointAt(t, latitude, longitude)] = true
	}

	client := NewMapClient(conn)

	res, err := client.GetPicturePoints(ctx, &MapGetPicturePointsRequest{Bounds: bounds, Individual: true})
	require.NoError(t, err)

	found := make(map[string]bool, pictureCount)

	for _, point := range res.GetPoints() {
		require.Nil(t, point.GetCluster(), "individual mode must never return a cluster point")

		if picture := point.GetPicture(); picture != nil && identities[picture.GetIdentity()] {
			found[picture.GetIdentity()] = true
		}
	}

	require.Len(t, found, pictureCount, "all pictures in the tight bbox must come back as individual points")
}
