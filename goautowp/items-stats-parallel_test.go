package goautowp

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/types/known/emptypb"
)

func newItemsStatsTestServer(t *testing.T) (*ItemsGRPCServer, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	db, pool, logger := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
  CREATE TEMP TABLE item (id bigint, item_type_id integer, logo_id bigint,
   begin_year integer DEFAULT 0, end_year integer DEFAULT 0,
   begin_month integer DEFAULT 0, end_month integer DEFAULT 0);
  CREATE TEMP TABLE picture (id bigint, copyrights_text_id bigint);
  CREATE TEMP TABLE picture_item (item_id bigint, picture_id bigint);
  CREATE TEMP TABLE textstorage_text (id bigint, text text);
  CREATE TEMP TABLE attrs_attributes (id bigint);
  CREATE TEMP TABLE attrs_zone_attributes (attribute_id bigint, zone_id bigint);
  CREATE TEMP TABLE attrs_values (attribute_id bigint, item_id bigint);
  INSERT INTO picture VALUES (1, 1), (2, 2), (3, 3), (4, 99), (5, NULL), (6, 1);
  INSERT INTO textstorage_text VALUES (1, 'Copyright'), (2, ''), (3, NULL);
  INSERT INTO picture_item VALUES (10, 1), (10, 2), (10, 3), (10, 4), (1, 1), (1, 2), (1, 3), (1, 4), (11, 5);
  INSERT INTO attrs_attributes VALUES (31), (32), (33);
  INSERT INTO attrs_zone_attributes VALUES (31, 1), (32, 1), (33, 5);
  INSERT INTO attrs_values VALUES (31, 10), (32, 10), (31, 11);
 `)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO item
  (id, item_type_id, logo_id, begin_year, end_year, begin_month, end_month) VALUES
  (1, $1, 1, 2000, 0, 0, 0), (2, $1, NULL, 0, 0, 0, 0),
  (10, $2, NULL, 1990, 2000, 1, 12), (11, $2, NULL, 2001, 0, 0, 0),
  (12, $2, NULL, 2010, 2015, 1, 0), (20, $3, NULL, 0, 0, 0, 0)`,
		schema.ItemTableItemTypeIDBrand, schema.ItemTableItemTypeIDVehicle, schema.ItemTableItemTypeIDEngine)
	require.NoError(t, err)

	return &ItemsGRPCServer{
		auth:            &Auth{},
		repository:      items.NewRepository(db, 0, nil, nil, nil),
		attrsRepository: attrs.NewRepository(db, nil, nil, nil, nil, nil, nil),
		picturesRepository: pictures.NewRepository(db, nil, nil, nil, nil,
			config.DuplicateFinderConfig{}, nil, nil, nil, nil),
	}, pool, logger
}

func TestItemsStatsConcurrentReads(t *testing.T) {
	t.Parallel()

	server, pool, logger := newItemsStatsTestServer(t)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{Roles: []string{users.RoleModer}})

	ctx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()

	gate := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, gate, 4)

	var active, maximum atomic.Int64

	logger.beforeQuery = func() {
		current := active.Add(1)
		defer active.Add(-1)

		for old := maximum.Load(); current > old; old = maximum.Load() {
			if maximum.CompareAndSwap(old, current) {
				break
			}
		}

		gate.Printf("")
	}

	type outcome struct {
		response *StatsResponse
		err      error
	}

	done := make(chan outcome, 1)

	go func() {
		response, err := server.GetStats(ctx, &emptypb.Empty{})
		done <- outcome{response: response, err: err}
	}()

	unblock()

	result := <-done
	require.NoError(t, result.err)
	require.EqualValues(t, 4, maximum.Load())
	require.EqualValues(t, 11, logger.queries.Load(), "one query per counter")

	logger.beforeQuery = nil

	expected := &StatsResponse{Values: []*StatsValue{
		{Name: "moder/statistics/photos-with-copyrights", Total: 6, Value: 2},
		{Name: "moder/statistics/vehicles-with-4-or-more-photos", Total: 3, Value: 2},
		{Name: "moder/statistics/specifications-values", Total: 6, Value: 3},
		{Name: "moder/statistics/brand-logos", Total: 2, Value: 1},
		{Name: "moder/statistics/from-years", Total: 3, Value: 4},
		{Name: "moder/statistics/from-and-to-years", Total: 3, Value: 2},
		{Name: "moder/statistics/from-and-to-years-and-months", Total: 3, Value: 1},
	}}
	require.True(t, proto.Equal(expected, result.response), "unexpected stats: %s", result.response)

	serialCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
	serial, err := server.GetStats(serialCtx, &emptypb.Empty{})
	require.NoError(t, err)
	require.True(t, proto.Equal(serial, result.response))

	_, err = pool.ExecContext(ctx, `TRUNCATE item, picture, picture_item, textstorage_text,
 attrs_attributes, attrs_zone_attributes, attrs_values`)
	require.NoError(t, err)
	empty, err := server.GetStats(ctx, &emptypb.Empty{})
	require.NoError(t, err)
	require.Len(t, empty.GetValues(), len(expected.GetValues()))

	for index, row := range empty.GetValues() {
		require.Equal(t, expected.GetValues()[index].GetName(), row.GetName())
		require.Zero(t, row.GetValue())
		require.Zero(t, row.GetTotal())
	}
}

func TestItemsStatsRequiresModerator(t *testing.T) {
	t.Parallel()

	server := &ItemsGRPCServer{auth: &Auth{}}

	for _, user := range []UserContext{{}, {UserID: 7}, {UserID: 7, Roles: []string{users.RoleCommentsModer}}} {
		ctx := context.WithValue(t.Context(), authenticatedUserKey{}, user)
		response, err := server.GetStats(ctx, &emptypb.Empty{})
		require.Equal(t, codes.PermissionDenied, status.Code(err))
		require.Nil(t, response)
	}
}

func TestItemsStatsCancellation(t *testing.T) {
	t.Parallel()

	server, pool, logger := newItemsStatsTestServer(t)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{Roles: []string{users.RoleModer}})

	ctx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()

	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	defer connection.Close()

	started := make(chan struct{}, 4)

	logger.beforeQuery = func() { started <- struct{}{} }

	done := make(chan error, 1)

	go func() {
		_, extractErr := server.GetStats(ctx, &emptypb.Empty{})
		done <- extractErr
	}()

	for range 4 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("statistic reads did not start together")
		}
	}

	cancel()

	err = <-done
	require.Equal(t, codes.Internal, status.Code(err))
	require.Contains(t, status.Convert(err).Message(), "context canceled")
	require.EqualValues(t, 4, logger.queries.Load(), "queued reads must not start after cancellation")
}

func TestItemsStatsReadFailure(t *testing.T) {
	t.Parallel()

	server, pool, _ := newItemsStatsTestServer(t)
	_, err := pool.ExecContext(t.Context(), "ALTER TABLE attrs_attributes RENAME COLUMN id TO broken_id")
	require.NoError(t, err)

	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{Roles: []string{users.RoleModer}})

	ctx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()

	response, err := server.GetStats(ctx, &emptypb.Empty{})
	require.Equal(t, codes.Internal, status.Code(err))
	require.Contains(t, status.Convert(err).Message(), "id")
	require.Nil(t, response)
	require.NoError(t, ctx.Err())
	require.Zero(t, pool.Stats().InUse)
}
