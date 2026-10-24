package goautowp

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestItemParentExtractorBatchesRelatedItems(t *testing.T) {
	t.Parallel()
	fixture, ids, pool, logger := newParentCacheBatchTestExtractor(t)
	extractor := NewItemParentExtractor(fixture.container)
	user := UserContext{UserID: 7, Roles: []string{users.RoleModer}}
	fields := &ItemParentFields{
		Item:   &ItemFields{NameHtml: true, LinksCount: true},
		Parent: &ItemFields{NameText: true, CommentsCount: true},
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
		expected := make([]*ItemParent, 0, len(rows))

		logger.queries.Store(0)

		for _, row := range rows {
			result, err := extractor.ExtractRows(t.Context(), []*items.ItemParent{row}, fields, lang, user)
			require.NoError(t, err)

			expected = append(expected, result...)
		}

		individual := logger.queries.Load()
		logger.queries.Store(0)

		result, err := extractor.ExtractRows(t.Context(), rows, fields, lang, user)
		require.NoError(t, err)
		require.EqualValues(t, 4, logger.queries.Load(), "two selections and two field batches")
		require.Less(t, logger.queries.Load(), individual)

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

func TestDfDistanceBatchPreservesFilters(t *testing.T) {
	t.Parallel()
	fixture, db, pool, _ := newRemainingPictureExtractor(t)
	extractor := NewDfDistanceExtractor(fixture.container)
	rows := []*schema.DfDistanceRow{
		{SrcPictureID: 7, DstPictureID: 3, Distance: 12},
		{SrcPictureID: 5, DstPictureID: 1, Distance: 20},
		{SrcPictureID: 9, DstPictureID: 3, Distance: 30},
		{SrcPictureID: 11, DstPictureID: 0},
	}

	for _, options := range []*PictureListOptions{
		nil, {Id: 999, Ids: []int64{1, 3, 5}, OwnerId: 7},
	} {
		fields := &DfDistanceFields{
			DstPicture: &PicturesRequest{Options: options, Fields: &PictureFields{Rights: true, Subscribed: true}},
		}
		original := proto.Clone(fields)
		logger := &itemBatchQueryLogger{}
		db.Logger(logger)

		result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", UserContext{UserID: 7})
		require.NoError(t, err)
		db.Logger(nil)
		require.EqualValues(t, 3, logger.queries.Load(), "one picture selection and two nested field batches")
		require.Len(t, result, len(rows))

		for index, row := range rows {
			require.Equal(t, row.SrcPictureID, result[index].GetSrcPictureId())
			require.Equal(t, row.DstPictureID, result[index].GetDstPictureId())

			if row.DstPictureID == 0 {
				require.Nil(t, result[index].GetDstPicture())

				continue
			}

			require.Equal(t, row.DstPictureID, result[index].GetDstPicture().GetId())
		}

		require.True(t, proto.Equal(original, fields))
	}

	for _, options := range []*PictureListOptions{
		{Ids: []int64{3}},
		{OwnerId: 8},
		{Status: PictureStatus_PICTURE_STATUS_ACCEPTED},
		{Identity: "one"},
	} {
		result, err := extractor.ExtractRows(
			t.Context(),
			rows,
			&DfDistanceFields{DstPicture: &PicturesRequest{Options: options}},
			"en",
			UserContext{},
		)
		require.ErrorIs(t, err, sql.ErrNoRows)
		require.Nil(t, result)
	}

	result, err := extractor.ExtractRows(t.Context(), []*schema.DfDistanceRow{{DstPictureID: 999}},
		&DfDistanceFields{DstPicture: &PicturesRequest{}}, "en", UserContext{})
	require.ErrorIs(t, err, sql.ErrNoRows)
	require.Nil(t, result)
	repository, err := fixture.container.PicturesRepository(t.Context())
	require.NoError(t, err)

	options := &query.PictureListOptions{ID: 999, IDs: []int64{1, 3, 5}, Limit: 1}
	selected, err := repository.PicturesByIDs(
		t.Context(),
		[]int64{1, 3},
		options,
		nil,
		convertPicturesOrder(PicturesRequest_ORDER_NONE),
	)
	require.NoError(t, err)
	require.Len(t, selected, 2, "the per-picture limit must not truncate the shared batch")
	require.Equal(t, &query.PictureListOptions{ID: 999, IDs: []int64{1, 3, 5}, Limit: 1}, options)

	for _, filter := range []*query.PictureListOptions{{ExcludeID: 1}, {IDGt: 1}, {IDLt: 3}, {IDs: []int64{3}}} {
		selected, err := repository.PicturesByIDs(
			t.Context(),
			[]int64{1, 3},
			filter,
			nil,
			convertPicturesOrder(PicturesRequest_ORDER_NONE),
		)
		require.NoError(t, err)
		require.Len(t, selected, 1)
	}
	// SQL errors from the shared picture selection still fail the whole request.
	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.picture RENAME COLUMN identity TO broken")
	require.NoError(t, err)
	result, err = extractor.ExtractRows(
		t.Context(),
		rows,
		&DfDistanceFields{DstPicture: &PicturesRequest{}},
		"en",
		UserContext{},
	)
	require.Error(t, err)
	require.Nil(t, result)
	_, singleErr := repository.Picture(
		t.Context(),
		&query.PictureListOptions{ID: 1},
		nil,
		convertPicturesOrder(PicturesRequest_ORDER_NONE),
	)
	require.EqualError(t, err, singleErr.Error())
}
