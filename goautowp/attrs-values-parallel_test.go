package goautowp

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/attrs"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
)

func newAttrsValuesTestServer(t *testing.T) (*AttrsGRPCServer, *goqu.Database, *sql.DB) {
	t.Helper()

	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
  CREATE TEMP TABLE attrs_attributes (id bigint, name text, description text, type_id integer,
   unit_id bigint, multiple boolean, precision integer, parent_id bigint, position integer);
  INSERT INTO attrs_attributes SELECT id, 'Attribute ' || id, NULL, id, NULL, true, 2, NULL, id
   FROM generate_series(1, 7) id;
  INSERT INTO attrs_attributes VALUES (8, 'Empty', NULL, 1, NULL, false, NULL::integer, NULL::bigint, 8);
  CREATE TEMP TABLE attrs_zone_attributes (attribute_id bigint, zone_id bigint);
  INSERT INTO attrs_zone_attributes VALUES (1, 1), (2, 1), (3, 2), (4, 1), (5, 1), (6, 1), (7, 2), (8, 1);
  CREATE TEMP TABLE attrs_values (attribute_id bigint, item_id bigint, update_date timestamptz);
  INSERT INTO attrs_values SELECT id, 10, '2020-01-01'::timestamptz + id * interval '1 day'
   FROM generate_series(1, 8) id;
  CREATE TEMP TABLE attrs_user_values (attribute_id bigint, item_id bigint, user_id bigint, update_date timestamptz);
  INSERT INTO attrs_user_values SELECT id, 10, 7, CASE WHEN id = 8 THEN NULL
   ELSE '2020-01-01'::timestamptz + id * interval '1 day' END FROM generate_series(1, 8) id;
  INSERT INTO attrs_user_values VALUES (2, 10, 8, '2020-01-01');
  CREATE TEMP TABLE attrs_values_string (attribute_id bigint, item_id bigint, value text);
  INSERT INTO attrs_values_string VALUES (1, 10, 'text'), (4, 10, ''), (8, 10, NULL);
  CREATE TEMP TABLE attrs_values_int (attribute_id bigint, item_id bigint, value integer);
  INSERT INTO attrs_values_int VALUES (2, 10, 1234), (5, 10, 1);
  CREATE TEMP TABLE attrs_values_float (attribute_id bigint, item_id bigint, value double precision);
  INSERT INTO attrs_values_float VALUES (3, 10, 12.345);
  CREATE TEMP TABLE attrs_values_list (attribute_id bigint, item_id bigint, value bigint, ordering integer);
  INSERT INTO attrs_values_list VALUES (6, 10, 11, 2), (6, 10, 12, 1), (7, 10, 21, 1);
  CREATE TEMP TABLE attrs_user_values_string AS SELECT *, 7::bigint AS user_id FROM attrs_values_string;
  CREATE TEMP TABLE attrs_user_values_int AS SELECT *, 7::bigint AS user_id FROM attrs_values_int;
  INSERT INTO attrs_user_values_int VALUES (2, 10, 456, 8);
  CREATE TEMP TABLE attrs_user_values_float AS SELECT *, 7::bigint AS user_id FROM attrs_values_float;
  CREATE TEMP TABLE attrs_user_values_list AS SELECT *, 7::bigint AS user_id FROM attrs_values_list;
  CREATE TEMP TABLE attrs_list_options (attribute_id bigint, id bigint, parent_id bigint, name text, position integer);
  INSERT INTO attrs_list_options VALUES
   (6, 11, NULL, 'specifications/boolean/true', 1), (6, 12, NULL, 'specifications/boolean/false', 2),
   (7, 21, NULL, 'specifications/boolean/true', 3);
 `)
	require.NoError(t, err)

	bundle, err := cnt.I18n()
	require.NoError(t, err)
	db.Logger(nil)

	return NewAttrsGRPCServer(attrs.NewRepository(db, bundle, nil, nil, nil, nil, nil), &Auth{}), db, pool
}

type attrsValuesTestCall struct {
	name    string
	extract func(context.Context, string) (proto.Message, error)
}

func attrsValuesTestCalls(server *AttrsGRPCServer) []attrsValuesTestCall {
	return []attrsValuesTestCall{
		{name: "actual", extract: func(ctx context.Context, lang string) (proto.Message, error) {
			return server.GetValues(ctx, &AttrValuesRequest{ItemId: 10, Language: lang})
		}},
		{name: "user text", extract: func(ctx context.Context, lang string) (proto.Message, error) {
			return server.GetUserValues(ctx, &AttrUserValuesRequest{
				ItemId: 10, UserId: 7, Language: lang,
				Fields: &AttrUserValuesFields{ValueText: true},
			})
		}},
		{name: "user raw", extract: func(ctx context.Context, lang string) (proto.Message, error) {
			return server.GetUserValues(ctx, &AttrUserValuesRequest{ItemId: 10, UserId: 7, Language: lang})
		}},
	}
}

func TestAttrsValuesConcurrentReads(t *testing.T) {
	t.Parallel()

	for index := range 3 {
		t.Run([]string{"actual", "user text", "user raw"}[index], func(t *testing.T) {
			t.Parallel()

			server, db, _ := newAttrsValuesTestServer(t)
			call := attrsValuesTestCalls(server)[index]
			ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 7})

			ctx, cancel := context.WithTimeout(ctx, 10*time.Second)
			defer cancel()

			// First extraction races initialization of attribute and list-option caches.
			cold, err := call.extract(ctx, "en")
			require.NoError(t, err)

			gate := &itemBatchQueryLogger{}
			unblock := blockItemExtractionQueries(t, gate, 4)

			var seen, active, maximum atomic.Int64

			db.Logger(&itemBatchQueryLogger{beforeQuery: func() {
				if seen.Add(1) == 1 {
					return
				}

				current := active.Add(1)
				defer active.Add(-1)

				for old := maximum.Load(); current > old; old = maximum.Load() {
					if maximum.CompareAndSwap(old, current) {
						break
					}
				}

				gate.Printf("")
			}})

			done := make(chan error, 1)

			var actual proto.Message

			go func() {
				var extractErr error

				actual, extractErr = call.extract(ctx, "en")
				done <- extractErr
			}()

			unblock()
			require.NoError(t, <-done)

			db.Logger(nil)
			require.EqualValues(t, 4, maximum.Load())

			require.EqualValues(t, 5, seen.Load(), "one list query and four typed batches")

			require.True(t, proto.Equal(cold, actual))

			for _, lang := range []string{"en", "ru"} {
				serialCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
				expected, err := call.extract(serialCtx, lang)
				require.NoError(t, err)
				actual, err := call.extract(ctx, lang)
				require.NoError(t, err)
				require.True(t, proto.Equal(expected, actual))
			}
		})
	}
}

func TestAttrsValuesFieldsAndFilters(t *testing.T) {
	t.Parallel()

	server, _, _ := newAttrsValuesTestServer(t)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 7})
	actual, err := server.GetValues(ctx, &AttrValuesRequest{ItemId: 10, Language: "en"})
	require.NoError(t, err)
	require.Len(t, actual.GetItems(), 8)

	for index, row := range actual.GetItems() {
		require.EqualValues(t, 8-index, row.GetAttributeId())
	}

	require.True(t, actual.GetItems()[0].GetValue().GetIsEmpty())
	require.Equal(t, "—", actual.GetItems()[0].GetValueText())
	require.Equal(t, []int64{12, 11}, actual.GetItems()[2].GetValue().GetListValue())

	user, err := server.GetUserValues(ctx, &AttrUserValuesRequest{ItemId: 10, UserId: 7})
	require.NoError(t, err)
	require.Len(t, user.GetItems(), 8)

	for index, row := range user.GetItems() {
		require.EqualValues(t, 7, row.GetUserId())
		require.Empty(t, row.GetValueText())

		if index < 7 {
			require.EqualValues(t, 7-index, row.GetAttributeId())
			require.NotNil(t, row.GetUpdateTime())
		} else {
			require.EqualValues(t, 8, row.GetAttributeId())
			require.Nil(t, row.GetUpdateTime())
		}
	}

	excluded, err := server.GetUserValues(ctx, &AttrUserValuesRequest{ItemId: 10, ExcludeUserId: 7})
	require.NoError(t, err)
	require.Len(t, excluded.GetItems(), 1)
	require.EqualValues(t, 456, excluded.GetItems()[0].GetValue().GetIntValue())

	empty, err := server.GetValues(ctx, &AttrValuesRequest{ItemId: 999})
	require.NoError(t, err)
	require.NotNil(t, empty.GetItems())
	require.Empty(t, empty.GetItems())

	unauthenticated := context.WithValue(ctx, authenticatedUserKey{}, UserContext{})
	_, err = server.GetValues(unauthenticated, &AttrValuesRequest{})
	require.Equal(t, codes.PermissionDenied, status.Code(err))
	_, err = server.GetUserValues(unauthenticated, &AttrUserValuesRequest{ItemId: 10})
	require.Equal(t, codes.PermissionDenied, status.Code(err))
	_, err = server.GetUserValues(ctx, &AttrUserValuesRequest{})
	require.Equal(t, codes.PermissionDenied, status.Code(err))
}

func TestAttrsValuesCancellationAndErrors(t *testing.T) {
	t.Parallel()

	for index := range 3 {
		t.Run([]string{"actual", "user text", "user raw"}[index], func(t *testing.T) {
			t.Parallel()

			server, db, pool := newAttrsValuesTestServer(t)
			call := attrsValuesTestCalls(server)[index]
			ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 7})
			_, err := call.extract(ctx, "en")
			require.NoError(t, err)

			childCtx, cancel := context.WithTimeout(ctx, 10*time.Second)
			defer cancel()

			var queries atomic.Int64

			started := make(chan struct{}, 4)

			db.Logger(&itemBatchQueryLogger{beforeQuery: func() {
				if queries.Add(1) > 1 {
					started <- struct{}{}

					<-childCtx.Done()
				}
			}})

			done := make(chan error, 1)

			go func() {
				_, extractErr := call.extract(childCtx, "en")
				done <- extractErr
			}()

			for range 4 {
				select {
				case <-started:
				case <-childCtx.Done():
					t.Fatal("value reads did not start together")
				}
			}

			cancel()
			require.Equal(t, codes.Internal, status.Code(<-done))

			db.Logger(nil)
			require.Zero(t, pool.Stats().InUse)

			table := "attrs_values_float"
			if index > 0 {
				table = "attrs_user_values_float"
			}

			_, err = pool.ExecContext(ctx, "ALTER TABLE "+table+" RENAME COLUMN value TO broken_value")
			require.NoError(t, err)
			result, err := call.extract(ctx, "en")
			require.Equal(t, codes.Internal, status.Code(err))
			require.Contains(t, status.Convert(err).Message(), "value")
			require.False(t, result.ProtoReflect().IsValid())
			require.NoError(t, ctx.Err())
			// The driver releases a connection asynchronously after canceling SQL.
			require.Eventually(t, func() bool { return pool.Stats().InUse == 0 }, time.Second, time.Millisecond)
		})
	}
}
