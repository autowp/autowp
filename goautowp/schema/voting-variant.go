package schema

import "github.com/doug-martin/goqu/v9"

const (
	VotingVariantTableName         = "voting_variant"
	VotingVariantTableVotesColName = "votes"
)

var (
	VotingVariantTable            = goqu.T(VotingVariantTableName)
	VotingVariantTableIDCol       = VotingVariantTable.Col("id")
	VotingVariantTableVotingIDCol = VotingVariantTable.Col("voting_id")
	VotingVariantTablePositionCol = VotingVariantTable.Col("position")
	VotingVariantTableNameCol     = VotingVariantTable.Col("name")
	VotingVariantTableTextCol     = VotingVariantTable.Col("text")
	VotingVariantTableVotesCol    = VotingVariantTable.Col(VotingVariantTableVotesColName)
)

type VotingVariantRow struct {
	ID    int32  `db:"id"`
	Name  string `db:"name"`
	Text  string `db:"text"`
	Votes int32  `db:"votes"`
}
