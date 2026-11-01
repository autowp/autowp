package maps

import (
	"database/sql"

	"github.com/autowp/goautowp/internal/schema"
)

type ItemPoint struct {
	Point      schema.NullPoint
	ID         int64
	Name       string
	BeginYear  sql.NullInt32
	EndYear    sql.NullInt32
	ItemTypeID schema.ItemTableItemTypeID
	Today      sql.NullBool
}

type PictureCell struct {
	Count    int64
	AvgLng   float64
	AvgLat   float64
	SampleID int64
}

type Picture struct {
	Identity string
	Point    schema.NullPoint
	ImageID  sql.NullInt64
}
