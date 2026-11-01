package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	ImageFormattedTableName                    = "image_formatted"
	ImageFormattedTableStatusColName           = "status"
	ImageFormattedTableImageIDColName          = "image_id"
	ImageFormattedTableFormatColName           = "format"
	ImageFormattedTableImageFormattedIDColName = "image_formatted_id"
)

var (
	ImageFormattedTable          = goqu.T(ImageFormattedTableName)
	ImageFormattedTableStatusCol = ImageFormattedTable.Col(
		ImageFormattedTableStatusColName,
	)
	ImageFormattedTableImageIDCol = ImageFormattedTable.Col(
		ImageFormattedTableImageIDColName,
	)
	ImageFormattedTableFormatCol = ImageFormattedTable.Col(
		ImageFormattedTableFormatColName,
	)
	ImageFormattedTableImageFormattedIDCol = ImageFormattedTable.Col(
		ImageFormattedTableImageFormattedIDColName,
	)
)

type ImageFormattedRow struct {
	ImageID          int           `db:"image_id"`
	Format           string        `db:"format"`
	ImageFormattedID sql.NullInt32 `db:"image_formatted_id"`
	Status           int           `db:"status"`
}
