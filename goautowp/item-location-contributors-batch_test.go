package goautowp

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newLocationContributorExtractor(t *testing.T) (*ItemExtractor, *goqu.Database, *sql.DB) {
	t.Helper()
	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE item_point(item_id bigint,point geography(Point,4326));
 INSERT INTO item_point VALUES (1,ST_GeogFromText('POINT(30 50)')),(2,NULL),(0,ST_GeogFromText('POINT(0 0)'));
 CREATE TEMP TABLE attrs_user_values(item_id bigint,user_id bigint,attribute_id bigint);
 INSERT INTO attrs_user_values VALUES (1,7,1),(1,7,2),(1,7,3),(1,8,1),(2,9,1),(2,9,2),(2,10,1),
 (0,7,1),(-1,11,1);
 `)
	require.NoError(t, err)
	db.Logger(nil)

	return NewItemExtractor(&Container{config: config.LoadConfig("."), goquDB: db}), db, pool
}

func TestLocationContributorsBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newLocationContributorExtractor(t)

	rows := []*items.Item{{ID: 1}, {ID: 2}, {ID: 3}, {ID: 0}, {ID: -1}, {ID: 1}}
	for _, fields := range []*ItemFields{
		{},
		{Location: true},
		{SpecsContributors: true},
		{Location: true, SpecsContributors: true},
	} {
		expected := make([]*Item, len(rows))
		for index, row := range rows {
			expected[index] = &Item{}

			var err error

			expected[index].Location, err = extractor.extractLocation(t.Context(), fields, row)
			require.NoError(t, err)
			expected[index].SpecsContributors, err = extractor.extractSpecsContributors(t.Context(), fields, row)
			require.NoError(t, err)
		}

		logger := &itemBatchQueryLogger{}
		db.Logger(logger)

		result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", UserContext{})
		require.NoError(t, err)
		db.Logger(nil)

		want := 0
		if fields.GetLocation() {
			want++
		}

		if fields.GetSpecsContributors() {
			want++
		}

		require.EqualValues(t, want, logger.queries.Load())

		for index, row := range result {
			require.True(t, proto.Equal(expected[index].GetLocation(), row.GetLocation()))
			require.Equal(t, expected[index].GetSpecsContributors(), row.GetSpecsContributors())
		}
	}

	empty, err := extractor.ExtractRows(
		t.Context(),
		nil,
		&ItemFields{Location: true, SpecsContributors: true},
		"en",
		UserContext{},
	)
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
}

func TestLocationContributorsBatchFailure(t *testing.T) {
	t.Parallel()

	for _, statement := range []string{
		"ALTER TABLE pg_temp.item_point RENAME COLUMN point TO broken",
		"ALTER TABLE pg_temp.attrs_user_values RENAME COLUMN user_id TO broken",
	} {
		t.Run(statement, func(t *testing.T) {
			t.Parallel()
			extractor, _, pool := newLocationContributorExtractor(t)
			_, err := pool.ExecContext(t.Context(), statement)
			require.NoError(t, err)
			result, err := extractor.ExtractRows(
				t.Context(),
				[]*items.Item{{ID: 1}},
				&ItemFields{Location: true, SpecsContributors: true},
				"en",
				UserContext{},
			)
			require.Error(t, err)
			require.Nil(t, result)
		})
	}

	extractor, _, _ := newLocationContributorExtractor(t)
	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err := extractor.ExtractRows(
		ctx,
		[]*items.Item{{ID: 1}},
		&ItemFields{Location: true, SpecsContributors: true},
		"en",
		UserContext{},
	)
	require.ErrorIs(t, err, context.Canceled)
}
