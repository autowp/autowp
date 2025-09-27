package schema

import "github.com/doug-martin/goqu/v9"

const (
	VotingVariantTableName         = "voting_variant"
	VotingVariantTableVotesColName = "votes"
)

var (
	VotingVariantTable            = goqu.T(VotingVariantTableName)
	VotingVariantTableIDCol       = VotingTable.Col("id")
	VotingVariantTableVotingIDCol = VotingTable.Col("voting_id")
	VotingVariantTablePositionCol = VotingTable.Col("position")
	VotingVariantTableNameCol     = VotingTable.Col("name")
	VotingVariantTableTextCol     = VotingTable.Col("text")
	VotingVariantTableVotesCol    = VotingTable.Col(VotingVariantTableVotesColName)
)

type VotingVariantRow struct {
	ID    int32  `db:"id"`
	Name  string `db:"name"`
	Text  string `db:"text"`
	Votes int32  `db:"votes"`
}
