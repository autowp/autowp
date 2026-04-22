package schema

import (
	"database/sql"

	"github.com/doug-martin/goqu/v9"
)

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
	ID    int32          `db:"id"    goqu:"pk,skipinsert"`
	Name  string         `db:"name"`
	Text  sql.NullString `db:"text"`
	Votes int32          `db:"votes"`
}
