package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

type PictureItemType int

const (
	PictureItemTypeContent    PictureItemType = 1
	PictureItemTypeAuthor     PictureItemType = 2
	PictureItemTypeCopyrights PictureItemType = 3

	PictureItemTableName                 = "picture_item"
	PictureItemTablePictureIDColName     = "picture_id"
	PictureItemTableItemIDColName        = "item_id"
	PictureItemTableTypeColName          = "type"
	PictureItemTableCropLeftColName      = "crop_left"
	PictureItemTableCropTopColName       = "crop_top"
	PictureItemTableCropWidthColName     = "crop_width"
	PictureItemTableCropHeightColName    = "crop_height"
	PictureItemTablePerspectiveIDColName = "perspective_id"
)

var (
	PictureItemTable                 = goqu.T(PictureItemTableName)
	PictureItemTablePictureIDCol     = PictureItemTable.Col(PictureItemTablePictureIDColName)
	PictureItemTableItemIDCol        = PictureItemTable.Col(PictureItemTableItemIDColName)
	PictureItemTableTypeCol          = PictureItemTable.Col(PictureItemTableTypeColName)
	PictureItemTableCropLeftCol      = PictureItemTable.Col(PictureItemTableCropLeftColName)
	PictureItemTableCropTopCol       = PictureItemTable.Col(PictureItemTableCropTopColName)
	PictureItemTableCropWidthCol     = PictureItemTable.Col(PictureItemTableCropWidthColName)
	PictureItemTableCropHeightCol    = PictureItemTable.Col(PictureItemTableCropHeightColName)
	PictureItemTablePerspectiveIDCol = PictureItemTable.Col(PictureItemTablePerspectiveIDColName)
)

type PictureItemRow struct {
	PictureID     int64           `db:"picture_id"`
	ItemID        int64           `db:"item_id"`
	Type          PictureItemType `db:"type"`
	CropLeft      sql.NullInt32   `db:"crop_left"`
	CropTop       sql.NullInt32   `db:"crop_top"`
	CropWidth     sql.NullInt32   `db:"crop_width"`
	CropHeight    sql.NullInt32   `db:"crop_height"`
	PerspectiveID sql.NullInt32   `db:"perspective_id"`
}
