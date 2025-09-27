package schema

import "github.com/doug-martin/goqu/v9"

const (
	PictureVoteSummaryTableName             = "picture_vote_summary"
	PictureVoteSummaryTablePictureIDColName = "picture_id"
	PictureVoteSummaryTablePositiveColName  = "positive"
	PictureVoteSummaryTableNegativeColName  = "negative"
)

var (
	PictureVoteSummaryTable             = goqu.T(PictureVoteSummaryTableName)
	PictureVoteSummaryTablePictureIDCol = PictureVoteSummaryTable.Col(
		PictureVoteSummaryTablePictureIDColName,
	)
	PictureVoteSummaryTablePositiveCol = PictureVoteSummaryTable.Col(
		PictureVoteSummaryTablePositiveColName,
	)
	PictureVoteSummaryTableNegativeCol = PictureVoteSummaryTable.Col(
		PictureVoteSummaryTableNegativeColName,
	)
)
