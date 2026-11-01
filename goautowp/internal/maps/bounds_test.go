package maps

import (
	"testing"

	"github.com/stretchr/testify/require"
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
