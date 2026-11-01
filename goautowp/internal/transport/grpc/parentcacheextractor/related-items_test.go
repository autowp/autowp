package parentcacheextractor_test

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestItemParentExtractorBatchesRelatedItems(t *testing.T) {
	t.Parallel()
	fixture, ids, pool, logger := newParentCacheBatchTestExtractor(t)
	extractor := fixture.parentExtractor
	user := authn.User{UserID: 7, Roles: []string{users.RoleModer}}
	fields := &pb.ItemParentFields{
		Item:   &pb.ItemFields{NameHtml: true, LinksCount: true},
		Parent: &pb.ItemFields{NameText: true, CommentsCount: true},
	}
	original := proto.Clone(fields)
	rows := make([]*items.ItemParent, 0, 5)
	rows = append(rows, []*items.ItemParent{
		{
			ItemParentRow: schema.ItemParentRow{
				ItemID:   ids[1],
				ParentID: ids[0],
				Catname:  "first",
				Type:     schema.ItemParentTypeTuning,
			},
		},
		{ItemParentRow: schema.ItemParentRow{ItemID: ids[0], ParentID: ids[0], Catname: "second"}},
		{ItemParentRow: schema.ItemParentRow{ItemID: -999, ParentID: ids[0]}},
		{ItemParentRow: schema.ItemParentRow{ItemID: ids[1], ParentID: -999}},
	}...)

	rows = append(rows, rows[0])
	for _, lang := range []string{"en", "ru"} {
		expected := make([]*pb.ItemParent, 0, len(rows))

		logger.Queries.Store(0)

		for _, row := range rows {
			result, err := extractor.ExtractRows(t.Context(), []*items.ItemParent{row}, fields, lang, user)
			require.NoError(t, err)

			expected = append(expected, result...)
		}

		individual := logger.Queries.Load()
		logger.Queries.Store(0)

		result, err := extractor.ExtractRows(t.Context(), rows, fields, lang, user)
		require.NoError(t, err)
		require.EqualValues(t, 4, logger.Queries.Load(), "two selections and two field batches")
		require.Less(t, logger.Queries.Load(), individual)

		for index := range result {
			require.True(t, proto.Equal(expected[index], result[index]))
		}

		require.Nil(t, result[2].GetItem())
		require.Nil(t, result[3].GetParent())
		require.Empty(t, result[0].GetItem().GetNameText())
		require.Empty(t, result[0].GetParent().GetNameHtml())
	}

	require.True(t, proto.Equal(original, fields))
	_, err := pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.item_link RENAME COLUMN item_id TO broken")
	require.NoError(t, err)
	result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", user)
	require.Error(t, err)
	require.Nil(t, result)
}
