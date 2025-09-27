package votings

import (
	"context"
	"database/sql"
	"math"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

type Repository struct {
	db *goqu.Database
}

// NewRepository constructor.
func NewRepository(db *goqu.Database) *Repository {
	return &Repository{
		db: db,
	}
}

type Voting struct {
	schema.VotingRow

	CanVote  bool
	MaxVotes int32
}

type VotingVariant struct {
	schema.VotingVariantRow

	Percent float32
	IsMax   bool
	IsMin   bool
}

func (s *Repository) Voting(
	ctx context.Context,
	id int32,
	userID int64,
) (*Voting, []*VotingVariant, error) {
	var st Voting

	success, err := s.db.Select(schema.VotingTableIDCol, schema.VotingTableNameCol, schema.VotingTableTextCol,
		schema.VotingTableMultivariantCol).
		From(schema.VotingTable).
		Where(schema.VotingTableIDCol.Eq(id)).
		ScanStructContext(ctx, &st)
	if err != nil {
		return nil, nil, err
	}

	if !success {
		return nil, nil, sql.ErrNoRows
	}

	var vvRows []*VotingVariant

	err = s.db.Select(schema.VotingVariantTableIDCol, schema.VotingVariantTableNameCol,
		schema.VotingVariantTableTextCol, schema.VotingVariantTableVotesCol).
		From(schema.VotingVariantTable).
		Where(schema.VotingVariantTableVotingIDCol.Eq(st.ID)).
		Order(schema.VotingVariantTablePositionCol.Asc()).
		ScanStructsContext(ctx, &vvRows)
	if err != nil {
		return nil, nil, err
	}

	var (
		maxVotes int32
		minVotes int32 = math.MaxInt32
	)

	for _, vvRow := range vvRows {
		if vvRow.Votes > maxVotes {
			maxVotes = vvRow.Votes
		}

		if vvRow.Votes < minVotes {
			minVotes = vvRow.Votes
		}
	}

	const maxPercents = 100

	var minVotesPercent float32
	if maxVotes > 0 {
		minVotesPercent = float32(math.Ceil(maxPercents * float64(minVotes) / float64(maxVotes)))
	}

	for _, variant := range vvRows {
		if maxVotes > 0 {
			variant.Percent = maxPercents * float32(variant.Votes) / float32(maxVotes)
			variant.IsMax = variant.Percent >= maxPercents-1
			variant.IsMin = variant.Percent <= minVotesPercent
		}
	}

	st.MaxVotes = maxVotes

	st.CanVote, err = s.canVote(ctx, &st.VotingRow, userID)
	if err != nil {
		return nil, nil, err
	}

	return &st, vvRows, nil
}

func (s *Repository) Votes(ctx context.Context, id int32) ([]int64, error) {
	var ids []int64

	err := s.db.Select(schema.VotingVariantVoteTableUserIDCol).
		From(schema.VotingVariantVoteTable).
		Where(schema.VotingVariantVoteTableVotingVariantIDCol.Eq(id)).
		ScanValsContext(ctx, ids)

	return ids, err
}

func (s *Repository) Vote(
	ctx context.Context,
	id int32,
	variantIDs []int32,
	userID int64,
) (bool, error) {
	if len(variantIDs) == 0 {
		return false, nil
	}

	var voting schema.VotingRow

	success, err := s.db.Select(schema.VotingTableIDCol, schema.VotingTableMultivariantCol).
		From(schema.VotingTable).
		Where(schema.VotingTableIDCol.Eq(id)).
		ScanStructContext(ctx, &voting)
	if err != nil {
		return false, err
	}

	if !success {
		return false, sql.ErrNoRows
	}

	canVote, err := s.canVote(ctx, &voting, userID)
	if err != nil {
		return false, err
	}

	if !canVote {
		return false, nil
	}

	err = s.db.Select(schema.VotingVariantTableIDCol).
		From(schema.VotingVariantTable).
		Where(
			schema.VotingVariantTableVotingIDCol.Eq(voting.ID),
			schema.VotingVariantTableIDCol.In(variantIDs),
		).ScanValsContext(ctx, &variantIDs)
	if err != nil {
		return false, err
	}

	if !voting.Multivariant && len(variantIDs) > 1 {
		return false, nil
	}

	ctx = context.WithoutCancel(ctx)

	for _, variantID := range variantIDs {
		_, err = s.db.Insert(schema.VotingVariantVoteTable).Rows(goqu.Record{
			schema.VotingVariantVoteTableVotingVariantIDColName: variantID,
			schema.VotingVariantVoteTableUserIDColName:          userID,
			schema.VotingVariantVoteTableTimestampColName:       goqu.Func("NOW"),
		}).OnConflict(goqu.DoNothing()).Executor().ExecContext(ctx)
		if err != nil {
			return false, err
		}

		err = s.updateVariantVotesCount(ctx, variantID)
		if err != nil {
			return false, err
		}
	}

	err = s.updateVotingVotesCount(ctx, voting.ID)
	if err != nil {
		return false, err
	}

	return true, nil
}

func (s *Repository) canVote(
	ctx context.Context,
	voting *schema.VotingRow,
	userID int64,
) (bool, error) {
	if userID == 0 || voting == nil {
		return false, nil
	}

	now := time.Now()

	if voting.BeginDate.After(now) {
		return false, nil
	}

	if voting.EndDate.Before(now) {
		return false, nil
	}

	var exists bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.VotingVariantVoteTable).
		Join(schema.VotingVariantTable, goqu.On(
			schema.VotingVariantVoteTableVotingVariantIDCol.Eq(schema.VotingVariantTableIDCol),
		)).
		Where(
			schema.VotingVariantTableVotingIDCol.Eq(voting.ID),
			schema.VotingVariantVoteTableUserIDCol.Eq(userID),
		).
		Limit(1).ScanValContext(ctx, &exists)

	return !success, err
}

func (s *Repository) updateVariantVotesCount(ctx context.Context, variantID int32) error {
	_, err := s.db.Update(schema.VotingVariantTable).
		Set(goqu.Record{
			schema.VotingVariantTableVotesColName: s.db.Select(goqu.COUNT(goqu.Star())).
				From(schema.VotingVariantVoteTable).
				Where(schema.VotingVariantVoteTableVotingVariantIDCol.Eq(schema.VotingVariantTableIDCol)),
		}).
		Where(schema.VotingVariantTableIDCol.Eq(variantID)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) updateVotingVotesCount(ctx context.Context, votingID int32) error {
	_, err := s.db.Update(schema.VotingTable).Set(goqu.Record{
		schema.VotingTableVotesColName: s.db.Select(goqu.COUNT(goqu.DISTINCT(schema.VotingVariantVoteTableUserIDCol))).
			From(schema.VotingVariantVoteTable).
			Join(schema.VotingVariantTable, goqu.On(
				schema.VotingVariantVoteTableVotingVariantIDCol.Eq(schema.VotingVariantTableIDCol),
			)).
			Where(schema.VotingVariantTableVotingIDCol.Eq(schema.VotingTableIDCol)),
	}).Where(schema.VotingTableIDCol.Eq(votingID)).
		Executor().ExecContext(ctx)

	return err
}
