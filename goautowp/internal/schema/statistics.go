package schema

import "database/sql"

type StatisticsPulseRow struct {
	UserID int64   `db:"user_id"`
	Date   string  `db:"date"`
	Value  float32 `db:"value"`
}

type PictureStatisticsRow struct {
	Count int32           `db:"count"`
	Size  sql.NullFloat64 `db:"size"`
}
