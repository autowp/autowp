package schema

import "github.com/doug-martin/goqu/v9"

const (
	ImageDirTableName         = "image_dir"
	ImageDirTableCountColName = "count"
	ImageDirTableDirColName   = "dir"
)

var (
	ImageDirTable         = goqu.T(ImageDirTableName)
	ImageDirTableCountCol = ImageDirTable.Col(ImageDirTableCountColName)
	ImageDirTableDirCol   = ImageDirTable.Col(ImageDirTableDirColName)
)
