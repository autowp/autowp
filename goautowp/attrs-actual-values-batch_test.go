package goautowp

import (
	"context"
	"testing"
	"time"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
)

func TestAttrsActualValueGroupsParallel(t *testing.T) {
	t.Parallel()

	server, db, _ := newAttrsValuesTestServer(t)
	// Warm only the attribute metadata; typed reads remain independently gated.
	_, err := server.repository.Attribute(t.Context(), 1)
	require.NoError(t, err)

	expected := map[int64]map[int64]attrs.Value{10: {
		1: {Valid: true, Type: schema.AttrsAttributeTypeIDString, StringValue: "text"},
		2: {Valid: true, Type: schema.AttrsAttributeTypeIDInteger, IntValue: 1234},
		3: {Valid: true, Type: schema.AttrsAttributeTypeIDFloat, FloatValue: 12.345},
		4: {Valid: true, Type: schema.AttrsAttributeTypeIDString},
		5: {Valid: true, Type: schema.AttrsAttributeTypeIDBoolean, BoolValue: true},
		6: {Valid: true, Type: schema.AttrsAttributeTypeIDList, ListValue: []int64{11, 12}},
		7: {Valid: true, Type: schema.AttrsAttributeTypeIDTree, ListValue: []int64{21}},
	}}

	for _, zoneID := range []int64{0, 1} {
		ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
		logger := &itemBatchQueryLogger{}
		unblock := blockItemExtractionQueries(t, logger, 4)
		db.Logger(logger)

		done := make(chan error, 1)

		var actual map[int64]map[int64]attrs.Value

		go func() {
			var readErr error
			if zoneID == 0 {
				actual, readErr = server.repository.ItemsActualValues(ctx, []int64{10, 10, 999})
			} else {
				actual, readErr = server.repository.ZoneItemsActualValues(ctx, zoneID, []int64{10, 10, 999})
			}

			done <- readErr
		}()

		unblock()
		require.NoError(t, <-done)
		db.Logger(nil)
		cancel()
		require.EqualValues(t, 4, logger.queries.Load())

		// Specification loaders have no list ORDER BY; verify membership separately.
		require.ElementsMatch(t, expected[10][6].ListValue, actual[10][6].ListValue)
		list := actual[10][6]
		list.ListValue = expected[10][6].ListValue
		actual[10][6] = list

		if zoneID == 0 {
			require.Equal(t, expected, actual)
		} else {
			subset := make(map[int64]attrs.Value)
			for _, id := range []int64{1, 2, 4, 5, 6} {
				subset[id] = expected[10][id]
			}

			require.Equal(t, map[int64]map[int64]attrs.Value{10: subset}, actual)
		}
	}

	empty, err := server.repository.ItemsActualValues(t.Context(), nil)
	// The existing specification loaders reject an empty IN list in SQL.
	require.ErrorContains(t, err, "syntax error")
	require.Nil(t, empty)
}

func TestAttrsActualValueGroupsCancellationAndFailure(t *testing.T) {
	t.Parallel()

	server, db, pool := newAttrsValuesTestServer(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	defer connection.Close()

	started := make(chan struct{}, 4)

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{} }})

	done := make(chan error, 1)

	go func() {
		_, readErr := server.repository.ItemsActualValues(ctx, []int64{10})
		done <- readErr
	}()

	for range 4 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("typed reads did not start together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
	require.NoError(t, connection.Close())

	db.Logger(nil)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.attrs_values_float RENAME COLUMN value TO broken_value")
	require.NoError(t, err)

	failureCtx, stop := context.WithTimeout(t.Context(), 10*time.Second)
	defer stop()

	result, err := server.repository.ZoneItemsActualValues(failureCtx, 1, []int64{10})
	require.ErrorContains(t, err, "value")
	require.Nil(t, result)
	require.NoError(t, failureCtx.Err())
	require.Zero(t, pool.Stats().InUse)
}

func TestAttrsGetValuesBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()

	server, db, pool := newAttrsValuesTestServer(t)
	_, err := pool.ExecContext(t.Context(), `
  INSERT INTO attrs_attributes (id, name, type_id, multiple, position) VALUES
   (9, 'Missing scalar', 1, false, 9), (10, 'Missing list', 6, true, 10),
   (11, 'Empty list', 6, true, 11), (12, 'Partial empty list', 6, true, 12),
   (13, 'Empty boolean', 5, false, 13), (14, 'Empty float', 3, false, 14),
   (15, 'No type', NULL, false, 15), (16, 'Unknown type', 99, false, 16);
  INSERT INTO attrs_values SELECT id, 10, '2020-01-01'::timestamptz + id * interval '1 day'
   FROM generate_series(9, 16) id;
  INSERT INTO attrs_values VALUES (1, 20, '2021-01-01');
  INSERT INTO attrs_values_string VALUES (1, 20, 'Other item');
  INSERT INTO attrs_values_list VALUES
   (11, 10, NULL, 1), (12, 10, 11, 3), (12, 10, NULL, 2), (12, 10, 12, 1);
  INSERT INTO attrs_values_int VALUES (13, 10, NULL);
  INSERT INTO attrs_values_float VALUES (14, 10, NULL);
 `)
	require.NoError(t, err)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 7})

	for _, lang := range []string{"en", "ru"} {
		for _, zoneID := range []int64{0, 1} {
			rows, err := server.repository.Values(
				ctx, query.AttrsValueListOptions{ZoneID: zoneID}, attrs.ValuesOrderByNone,
			)
			require.NoError(t, err)

			expected := make([]*AttrValue, 0, len(rows))
			for _, row := range rows {
				value, text, err := server.repository.ActualValueText(ctx, row.AttributeID, row.ItemID, lang)
				require.NoError(t, err)

				extracted := extractAttrValue(value)
				expected = append(expected, &AttrValue{
					ItemId: row.ItemID, AttributeId: row.AttributeID, Value: &extracted, ValueText: text,
				})
			}

			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			actual, err := server.GetValues(ctx, &AttrValuesRequest{Language: lang, ZoneId: zoneID})
			require.NoError(t, err)
			db.Logger(nil)

			wantQueries := 5
			if zoneID == 1 {
				wantQueries = 4
			}

			require.EqualValues(
				t, wantQueries, logger.queries.Load(), "typed query count must not grow with the number of values",
			)
			require.True(t, proto.Equal(&AttrValuesResponse{Items: expected}, actual))
		}
	}

	// Invalid stored boolean values must still fail the whole request.
	_, err = pool.ExecContext(ctx, "UPDATE pg_temp.attrs_values_int SET value = 2 WHERE attribute_id = 5")
	require.NoError(t, err)
	_, _, err = server.repository.ActualValueText(ctx, 5, 10, "en")
	require.Error(t, err)
	actual, err := server.GetValues(ctx, &AttrValuesRequest{ItemId: 10, Language: "en"})
	require.Equal(t, codes.Internal, status.Code(err))
	require.Nil(t, actual)
}
