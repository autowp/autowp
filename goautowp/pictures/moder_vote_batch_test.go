package pictures

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func createModerVoteBatchRepository(t *testing.T) *Repository {
	t.Helper()

	cfg := config.LoadConfig("..")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	db.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, db.Close()) })
	_, err = db.ExecContext(t.Context(), `
		CREATE TEMP TABLE picture_moder_vote (
			picture_id bigint, user_id bigint, vote integer, reason text,
			PRIMARY KEY (picture_id, user_id)
		);
		INSERT INTO picture_moder_vote VALUES
			(1, 10, 1, 'positive'), (1, 11, 0, 'negative'), (1, 12, 2, 'positive'),
			(2, 11, 0, 'negative'), (99, 10, 1, 'unrequested');
	`)
	require.NoError(t, err)

	return &Repository{db: goqu.New("postgres", db)}
}

func TestModerVoteSummariesBatch(t *testing.T) {
	t.Parallel()

	empty, err := (&Repository{}).ModerVoteSummaries(t.Context(), nil, 10)
	require.NoError(t, err)
	require.Empty(t, empty)
	repository := createModerVoteBatchRepository(t)
	summaries, err := repository.ModerVoteSummaries(t.Context(), []int64{1, 2, 3}, 10)
	require.NoError(t, err)
	require.Equal(t, map[int64]ModerVoteSummary{
		1: {Count: 3, Vote: 1, Voted: true},
		2: {Count: 1, Vote: -1, Voted: false},
	}, summaries)
	require.Equal(t, ModerVoteSummary{}, summaries[3])

	for _, id := range []int64{1, 2, 3} {
		count, vote, err := repository.ModerVoteCount(t.Context(), id)
		require.NoError(t, err)
		voted, err := repository.HasModerVote(t.Context(), id, 10)
		require.NoError(t, err)
		require.Equal(t, ModerVoteSummary{Count: count, Vote: vote, Voted: voted}, summaries[id])
	}

	other, err := repository.ModerVoteSummaries(t.Context(), []int64{1, 2}, 11)
	require.NoError(t, err)
	require.True(t, other[1].Voted)
	require.True(t, other[2].Voted)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.ModerVoteSummaries(ctx, []int64{1}, 10)
	require.ErrorIs(t, err, context.Canceled)
}

func TestPictureModerVotesBatch(t *testing.T) {
	t.Parallel()

	empty, err := (&Repository{}).PictureModerVotesBatch(t.Context(), nil, nil)
	require.NoError(t, err)
	require.Empty(t, empty)

	repository := createModerVoteBatchRepository(t)
	for _, tc := range []struct {
		name    string
		options *query.PictureModerVoteListOptions
	}{
		{name: "all", options: &query.PictureModerVoteListOptions{}},
		{name: "positive", options: &query.PictureModerVoteListOptions{VoteGtZero: true}},
		{name: "nonpositive", options: &query.PictureModerVoteListOptions{VoteLteZero: true}},
		{name: "conflicting", options: &query.PictureModerVoteListOptions{VoteGtZero: true, VoteLteZero: true}},
	} {
		t.Run(tc.name, func(t *testing.T) {
			t.Parallel()

			rows, err := repository.PictureModerVotesBatch(t.Context(), []int64{1, 2, 3}, tc.options)
			require.NoError(t, err)

			var expected []*schema.PictureModerVoteRow

			for _, id := range []int64{1, 2, 3} {
				options := tc.options.Clone()
				options.PictureID = id
				individual, err := repository.PictureModerVotes(t.Context(), options)
				require.NoError(t, err)

				expected = append(expected, individual...)
			}

			require.ElementsMatch(t, expected, rows)
		})
	}
}

func TestModerVotedPictureIDsBatch(t *testing.T) {
	t.Parallel()

	empty, err := (&Repository{}).ModerVotedPictureIDs(t.Context(), nil, 10)
	require.NoError(t, err)
	require.Empty(t, empty)
	anonymous, err := (&Repository{}).ModerVotedPictureIDs(t.Context(), []int64{1}, 0)
	require.NoError(t, err)
	require.Empty(t, anonymous)

	repository := createModerVoteBatchRepository(t)
	voted, err := repository.ModerVotedPictureIDs(t.Context(), []int64{1, 2, 3}, 10)
	require.NoError(t, err)
	require.Equal(t, []int64{1}, voted)
	voted, err = repository.ModerVotedPictureIDs(t.Context(), []int64{1, 2, 3}, 11)
	require.NoError(t, err)
	require.ElementsMatch(t, []int64{1, 2}, voted)
}
