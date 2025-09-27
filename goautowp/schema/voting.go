package schema

import (
	"time"

	"github.com/doug-martin/goqu/v9"
)

const (
	VotingTableName         = "voting"
	VotingTableVotesColName = "votes"
)

var (
	VotingTable                = goqu.T(VotingTableName)
	VotingTableIDCol           = VotingTable.Col("id")
	VotingTableNameCol         = VotingTable.Col("name")
	VotingTableTextCol         = VotingTable.Col("text")
	VotingTableMultivariantCol = VotingTable.Col("multivariant")
	VotingTableBeginDateCol    = VotingTable.Col("begin_date")
	VotingTableEndDateCol      = VotingTable.Col("end_date")
)

type VotingRow struct {
	ID           int32     `db:"id"`
	Name         string    `db:"name"`
	Text         string    `db:"text"`
	Multivariant bool      `db:"multivariant"`
	BeginDate    time.Time `db:"begin_date"`
	EndDate      time.Time `db:"end_date"`
}
