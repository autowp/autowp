package itemextractor

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newNamesBatchExtractor(t *testing.T) (*testExtractor, *goqu.Database, *sql.DB) {
	t.Helper()
	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE item_language (item_id bigint,language text,name text);
 INSERT INTO item_language VALUES (1,'xx','First'),(1,'en','Common'),(1,'fr','Common'),(1,'ru','Первое'),
 (1,'de',''),(1,'es',NULL),(1,'it',' '),(2,'xx','Second'),(2,'ru','Второе'),(4,'en',''),(4,'ru',NULL);
 `)
	require.NoError(t, err)
	db.Logger(nil)

	return newTestExtractor(t, db), db, pool
}

func TestItemNamesBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newNamesBatchExtractor(t)
	rows := []*items.Item{
		{ID: 1, NameOnly: "First"},
		{ID: 2, NameOnly: "Second"},
		{ID: 3},
		{ID: 4},
		{ID: 1, NameOnly: "Common"},
	}

	for _, lang := range []string{"en", "ru", "xx", "unknown"} {
		for _, fields := range []*pb.ItemFields{
			{}, {AltNames: true}, {OtherNames: true}, {AltNames: true, OtherNames: true},
		} {
			expectedAlt := make([][]*pb.AltName, len(rows))

			expectedOther := make([][]string, len(rows))
			for index, row := range rows {
				var err error

				expectedAlt[index], err = extractor.extractAltNames(t.Context(), fields, row, lang)
				require.NoError(t, err)
				expectedOther[index], err = extractor.extractOtherNames(t.Context(), fields, row)
				require.NoError(t, err)
			}

			original := proto.Clone(fields)
			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			result, err := extractor.ExtractRows(t.Context(), rows, fields, lang, authn.User{})
			require.NoError(t, err)
			db.Logger(nil)

			want := 0
			if fields.GetAltNames() || fields.GetOtherNames() {
				want = 1
			}

			require.EqualValues(t, want, logger.queries.Load())

			for index, row := range result {
				require.Equal(t, rows[index].ID, row.GetId())
				require.ElementsMatch(t, expectedOther[index], row.GetOtherNames())
				require.Len(t, row.GetAltNames(), len(expectedAlt[index]))

				byName := make(map[string][]string)
				for _, name := range row.GetAltNames() {
					byName[name.GetName()] = name.GetLanguages()
				}

				for _, name := range expectedAlt[index] {
					require.ElementsMatch(t, name.GetLanguages(), byName[name.GetName()])
				}

				if fields.GetAltNames() {
					require.NotNil(t, row.GetAltNames())
				}

				if fields.GetOtherNames() {
					require.NotNil(t, row.GetOtherNames())
				}
			}

			require.True(t, proto.Equal(original, fields))
		}
	}
}

func TestItemNamesBatchErrorsAndCancellation(t *testing.T) {
	t.Parallel()
	extractor, db, pool := newNamesBatchExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	started := make(chan struct{}, 1)

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

	done := make(chan error, 1)

	go func() {
		_, err := extractor.ExtractRows(
			ctx,
			[]*items.Item{{ID: 1}},
			&pb.ItemFields{AltNames: true, OtherNames: true},
			"en",
			authn.User{},
		)
		done <- err
	}()

	select {
	case <-started:
	case <-ctx.Done():
		t.Fatal("names batch did not start")
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
	require.NoError(t, connection.Close())
	db.Logger(nil)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.item_language RENAME COLUMN name TO broken")
	require.NoError(t, err)
	result, err := extractor.ExtractRows(
		t.Context(),
		[]*items.Item{{ID: 1}},
		&pb.ItemFields{AltNames: true, OtherNames: true},
		"en",
		authn.User{},
	)
	require.Error(t, err)
	require.Nil(t, result)
	_, err = extractor.ExtractRows(t.Context(), []*items.Item{{ID: 1}}, nil, "en", authn.User{})
	require.NoError(t, err)
	empty, err := extractor.ExtractRows(
		t.Context(),
		nil,
		&pb.ItemFields{AltNames: true, OtherNames: true},
		"en",
		authn.User{},
	)
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
}
