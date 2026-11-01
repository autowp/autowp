package schema

import (
	"github.com/doug-martin/goqu/v9"
)

const (
	PictureAuthorSuggestionSourceEXIFArtist    = "exif-artist"
	PictureAuthorSuggestionSourceEXIFCopyright = "exif-copyright"

	PictureAuthorSuggestionTableName             = "picture_author_suggestion"
	PictureAuthorSuggestionTablePictureIDColName = "picture_id"
	PictureAuthorSuggestionTableItemIDColName    = "item_id"
	PictureAuthorSuggestionTableSourceColName    = "source"
	PictureAuthorSuggestionTableRawValueColName  = "raw_value"
	PictureAuthorSuggestionTableCreatedAtColName = "created_at"
)

var (
	PictureAuthorSuggestionTable             = goqu.T(PictureAuthorSuggestionTableName)
	PictureAuthorSuggestionTablePictureIDCol = PictureAuthorSuggestionTable.Col(
		PictureAuthorSuggestionTablePictureIDColName,
	)
	PictureAuthorSuggestionTableItemIDCol = PictureAuthorSuggestionTable.Col(
		PictureAuthorSuggestionTableItemIDColName,
	)
	PictureAuthorSuggestionTableSourceCol = PictureAuthorSuggestionTable.Col(
		PictureAuthorSuggestionTableSourceColName,
	)
	PictureAuthorSuggestionTableRawValueCol = PictureAuthorSuggestionTable.Col(
		PictureAuthorSuggestionTableRawValueColName,
	)
)

type PictureAuthorSuggestionRow struct {
	PictureID int64  `db:"picture_id"`
	ItemID    int64  `db:"item_id"`
	Source    string `db:"source"`
	RawValue  string `db:"raw_value"`
}
