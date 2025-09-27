package schema

import "github.com/doug-martin/goqu/v9"

const (
	PictureViewTableName             = "picture_view"
	PictureViewTablePictureIDColName = "picture_id"
	PictureViewTableViewsColName     = "views"
)

var (
	PictureViewTable             = goqu.T(PictureViewTableName)
	PictureViewTablePictureIDCol = PictureViewTable.Col(PictureViewTablePictureIDColName)
	PictureViewTableViewsCol     = PictureViewTable.Col(PictureViewTableViewsColName)
)
