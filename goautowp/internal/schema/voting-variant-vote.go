package schema

import "github.com/doug-martin/goqu/v9"

const (
	VotingVariantVoteTableName                   = "voting_variant_vote"
	VotingVariantVoteTableVotingVariantIDColName = "voting_variant_id"
	VotingVariantVoteTableUserIDColName          = "user_id"
	VotingVariantVoteTableTimestampColName       = "timestamp"
)

var (
	VotingVariantVoteTable                   = goqu.T(VotingVariantVoteTableName)
	VotingVariantVoteTableVotingVariantIDCol = VotingVariantVoteTable.Col(
		VotingVariantVoteTableVotingVariantIDColName,
	)
	VotingVariantVoteTableUserIDCol = VotingVariantVoteTable.Col("user_id")
)
