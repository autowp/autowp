package schema

import (
	"database/sql/driver"
	"encoding/binary"
	"errors"

	"github.com/twpayne/go-geom"
	"github.com/twpayne/go-geom/encoding/ewkbhex"
)

const SRID = 4326

var (
	errGeometryPointExpected = errors.New("geometry point expected")
	errBytesExpected         = errors.New("bytes expected")
	errNonNilValueExpected   = errors.New("non-nil value expected")
)

// NullPoint represents a [geo.Point] that may be null.
// NullPoint implements the [Scanner] interface so
// it can be used as a scan destination, similar to [NullString].
type NullPoint struct {
	Point geom.Point
	Valid bool // Valid is true if Point is not NULL
}

// Scan implements the [Scanner] interface.
func (n *NullPoint) Scan(value interface{}) error {
	n.Point, n.Valid = geom.Point{}, false

	if value == nil {
		return nil
	}

	switch bytes := value.(type) {
	case []byte:
		decoded, err := ewkbhex.Decode(string(bytes))
		if err != nil {
			return err
		}

		point, ok := decoded.(*geom.Point)
		if !ok {
			return errGeometryPointExpected
		}

		if point == nil {
			return errNonNilValueExpected
		}

		n.Point = *point
		n.Valid = true
	default:
		return errBytesExpected
	}

	return nil
}

// Value implements the [driver.Valuer] interface.
func (n NullPoint) Value() (driver.Value, error) {
	if !n.Valid {
		return nil, nil //nolint: nilnil
	}

	return ewkbhex.Encode(&n.Point, binary.LittleEndian)
}
