package schema

import (
	"database/sql/driver"

	geo "github.com/paulmach/go.geo"
)

// NullPoint represents a [geo.Point] that may be null.
// NullPoint implements the [Scanner] interface so
// it can be used as a scan destination, similar to [NullString].
type NullPoint struct {
	Point geo.Point
	Valid bool // Valid is true if Point is not NULL
}

// Scan implements the [Scanner] interface.
func (n *NullPoint) Scan(value interface{}) error {
	if value == nil {
		n.Point, n.Valid = geo.Point{}, false

		return nil
	}

	err := n.Point.Scan(value)
	if err != nil {
		return err
	}

	n.Valid = true

	return nil
}

// Value implements the [driver.Valuer] interface.
func (n NullPoint) Value() (driver.Value, error) {
	if !n.Valid {
		return nil, nil //nolint: nilnil
	}

	return n.Point, nil
}
