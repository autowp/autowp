package schema

import "github.com/doug-martin/goqu/v9"

const (
	DfDistanceTableName                = "df_distance"
	DfDistanceTableDistanceColName     = "distance"
	DfDistanceTableSrcPictureIDColName = "src_picture_id"
	DfDistanceTableDstPictureIDColName = "dst_picture_id"
	DfDistanceTableHideColName         = "hide"
)

var (
	DfDistanceTable                = goqu.T(DfDistanceTableName)
	DfDistanceTableDistanceCol     = DfDistanceTable.Col(DfDistanceTableDistanceColName)
	DfDistanceTableSrcPictureIDCol = DfDistanceTable.Col(DfDistanceTableSrcPictureIDColName)
	DfDistanceTableDstPictureIDCol = DfDistanceTable.Col(DfDistanceTableDstPictureIDColName)
)

type DfDistanceRow struct {
	SrcPictureID int64 `db:"src_picture_id"`
	DstPictureID int64 `db:"dst_picture_id"`
	Distance     int64 `db:"distance"`
}
