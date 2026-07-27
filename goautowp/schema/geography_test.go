package schema_test

import (
	"encoding/binary"
	"testing"

	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	"github.com/twpayne/go-geom"
	"github.com/twpayne/go-geom/encoding/ewkbhex"
)

func TestNullPointScanNil(t *testing.T) {
	t.Parallel()

	var point schema.NullPoint

	err := point.Scan(nil)
	require.NoError(t, err)
	require.False(t, point.Valid)
}

func TestNullPointScanInvalidType(t *testing.T) {
	t.Parallel()

	var point schema.NullPoint

	err := point.Scan(42)
	require.Error(t, err)
	require.False(t, point.Valid)
}

func TestNullPointScanInvalidHex(t *testing.T) {
	t.Parallel()

	var point schema.NullPoint

	err := point.Scan([]byte("not-hex"))
	require.Error(t, err)
}

func TestNullPointScanWrongGeometryType(t *testing.T) {
	t.Parallel()

	src := geom.NewLineStringFlat(geom.XY, []float64{0, 0, 1, 1}).SetSRID(schema.SRID)

	encoded, err := ewkbhex.Encode(src, binary.LittleEndian)
	require.NoError(t, err)

	var point schema.NullPoint

	err = point.Scan([]byte(encoded))
	require.Error(t, err)
}

func TestNullPointScanValid(t *testing.T) {
	t.Parallel()

	src := geom.NewPointFlat(geom.XY, []float64{37.6173, 55.7558}).SetSRID(schema.SRID)

	encoded, err := ewkbhex.Encode(src, binary.LittleEndian)
	require.NoError(t, err)

	var point schema.NullPoint

	err = point.Scan([]byte(encoded))
	require.NoError(t, err)
	require.True(t, point.Valid)
	require.InDelta(t, 37.6173, point.Point.X(), 0.0001)
	require.InDelta(t, 55.7558, point.Point.Y(), 0.0001)
}

func TestNullPointValue(t *testing.T) {
	t.Parallel()

	invalid := schema.NullPoint{Valid: false}

	value, err := invalid.Value()
	require.NoError(t, err)
	require.Nil(t, value)

	src := *geom.NewPointFlat(geom.XY, []float64{37.6173, 55.7558}).SetSRID(schema.SRID)
	valid := schema.NullPoint{Point: src, Valid: true}

	value, err = valid.Value()
	require.NoError(t, err)
	require.NotEmpty(t, value)

	var roundTrip schema.NullPoint

	err = roundTrip.Scan([]byte(value.(string))) //nolint:forcetypeassert
	require.NoError(t, err)
	require.True(t, roundTrip.Valid)
	require.InDelta(t, 37.6173, roundTrip.Point.X(), 0.0001)
	require.InDelta(t, 55.7558, roundTrip.Point.Y(), 0.0001)
}
