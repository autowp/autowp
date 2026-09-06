package itemofday

import (
	"database/sql"
	"math/rand"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	_ "github.com/lib/pq"                               // enable postgres driver
	"github.com/stretchr/testify/require"
)

func TestCompletenessWeight(t *testing.T) {
	t.Parallel()

	require.InDelta(t, 1.0, completenessWeight(0), 0.0001)
	require.InDelta(t, 4.0, completenessWeight(5), 0.0001)
	require.InDelta(t, 7.0, completenessWeight(10), 0.0001)
	// Beyond the cap the weight stops growing - a section with 40 fully-documented pictures gets
	// no extra pull over one with exactly 10.
	require.InDelta(t, 7.0, completenessWeight(40), 0.0001)
}

func TestPickWeightedFavorsHigherWeight(t *testing.T) {
	t.Parallel()

	candidates := []weightedCandidate{
		{ItemID: 1, CompleteCount: 0},  // weight 1
		{ItemID: 2, CompleteCount: 5},  // weight 4
		{ItemID: 3, CompleteCount: 10}, // weight 7
	}

	rng := rand.New(rand.NewSource(1)) //nolint:gosec

	const trials = 20000

	counts := map[int64]int{}

	for range trials {
		chosen, found := pickWeighted(candidates, func(c weightedCandidate) float64 {
			return completenessWeight(c.CompleteCount)
		}, rng)
		require.True(t, found)

		counts[chosen.ItemID]++
	}

	// Weights 1:4:7 sum to 12 - expected shares are 1/12, 4/12, 7/12 of the trials. Generous
	// tolerance (the point is "clearly favors higher weight", not pinning the RNG's exact output).
	require.InDelta(t, float64(trials)*1/12, counts[1], float64(trials)*0.03)
	require.InDelta(t, float64(trials)*4/12, counts[2], float64(trials)*0.03)
	require.InDelta(t, float64(trials)*7/12, counts[3], float64(trials)*0.03)

	// The fully-documented item (weight 7) must come out ahead of the undocumented one (weight 1)
	// by roughly the ratio of their weights, not just "some" bias.
	require.Greater(t, counts[3], counts[1]*5)
}

func TestPickWeightedEmpty(t *testing.T) {
	t.Parallel()

	_, found := pickWeighted([]weightedCandidate{}, func(c weightedCandidate) float64 {
		return completenessWeight(c.CompleteCount)
	}, rand.New(rand.NewSource(1))) //nolint:gosec
	require.False(t, found)
}

func createRepository(t *testing.T) *Repository {
	t.Helper()

	cfg := config.LoadConfig("..")

	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquDB := goqu.New("postgres", db)

	s := NewRepository(goquDB)

	return s
}

func TestPickItemOfDay(t *testing.T) {
	t.Parallel()

	s := createRepository(t)
	_, err := s.Pick(t.Context())
	require.NoError(t, err)
}
