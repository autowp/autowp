package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

const (
	FormattedImageTableName                    = "formated_image"
	FormattedImageTableStatusColName           = "status"
	FormattedImageTableImageIDColName          = "image_id"
	FormattedImageTableFormatColName           = "format"
	FormattedImageTableFormattedImageIDColName = "formated_image_id"
)

var (
	FormattedImageTable          = goqu.T(FormattedImageTableName)
	FormattedImageTableStatusCol = FormattedImageTable.Col(
		FormattedImageTableStatusColName,
	)
	FormattedImageTableImageIDCol = FormattedImageTable.Col(
		FormattedImageTableImageIDColName,
	)
	FormattedImageTableFormatCol = FormattedImageTable.Col(
		FormattedImageTableFormatColName,
	)
	FormattedImageTableFormattedImageIDCol = FormattedImageTable.Col(
		FormattedImageTableFormattedImageIDColName,
	)
)

type FormattedImageRow struct {
	ImageID          int           `db:"image_id"`
	Format           string        `db:"format"`
	FormattedImageID sql.NullInt32 `db:"formated_image_id"`
	Status           int           `db:"status"`
}
