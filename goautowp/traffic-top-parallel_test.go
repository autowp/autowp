package goautowp

import (
	"context"
	"database/sql"
	"sync/atomic"
	"testing"
	"time"

	"github.com/autowp/goautowp/ban"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/traffic"
	"github.com/autowp/goautowp/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/types/known/emptypb"
)

func newTrafficTopTestServer(t *testing.T) (*TrafficGRPCServer, *sql.DB, *itemBatchQueryLogger) {
	t.Helper()

	db, pool, logger := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
  CREATE TEMP TABLE ip_monitoring (ip inet, count integer, day_date date);
  CREATE TEMP TABLE ip_ban (ip inet, until timestamptz, reason text, by_user_id bigint);
  CREATE TEMP TABLE ip_whitelist (ip inet);
  CREATE TEMP TABLE users (
   id bigint, name text, deleted boolean DEFAULT false, identity text, last_online timestamptz,
   green boolean DEFAULT false, specs_weight double precision DEFAULT 1, img integer, e_mail text,
   pictures_total bigint DEFAULT 0, specs_volume bigint DEFAULT 0, language text DEFAULT 'en',
   uuid uuid, terms_version integer, contacts_public boolean DEFAULT false
  );
  INSERT INTO users (id, name, deleted, identity, last_online) VALUES
   (7, 'Ban author', false, 'ban-author', '2020-01-01'), (8, 'Hidden author', true, 'deleted-author', '2020-01-01');
  INSERT INTO ip_monitoring VALUES
   ('192.0.2.1', 100, CURRENT_DATE), ('192.0.2.1', 50, CURRENT_DATE),
   ('192.0.2.2', 90, CURRENT_DATE), ('192.0.2.3', 80, CURRENT_DATE),
   ('192.0.2.4', 70, CURRENT_DATE), ('2001:db8::1', 60, CURRENT_DATE),
   ('192.0.2.5', 50, CURRENT_DATE), ('192.0.2.99', 9999, CURRENT_DATE - 1);
  INSERT INTO ip_ban VALUES
   ('192.0.2.1', NOW() + interval '1 day', 'Active', 7),
   ('192.0.2.2', NOW() + interval '1 day', 'Missing author', 999),
   ('192.0.2.3', NOW() - interval '1 day', 'Expired', 7),
   ('192.0.2.4', NOW() + interval '1 day', 'Automatic', NULL),
   ('2001:db8::1', NOW() + interval '1 day', 'Deleted author', 8);
  INSERT INTO ip_whitelist VALUES ('192.0.2.2'), ('2001:db8::1');
 `)
	require.NoError(t, err)

	bans, err := ban.NewRepository(db)
	require.NoError(t, err)
	traf, err := traffic.NewRepository(db, bans)
	require.NoError(t, err)

	return NewTrafficGRPCServer(&Auth{}, users.NewRepository(db, "", nil, nil, config.KeycloakConfig{}, 0, nil),
		NewUserExtractor(nil, nil), traf), pool, logger
}

func TestTrafficTopConcurrentReads(t *testing.T) {
	t.Parallel()

	server, pool, logger := newTrafficTopTestServer(t)
	ctx := context.WithValue(
		t.Context(), authenticatedUserKey{}, UserContext{UserID: 9, Roles: []string{users.RoleModer}},
	)

	ctx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()

	gate := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, gate, 4)

	var seen, active, maximum atomic.Int64

	logger.beforeQuery = func() {
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
	}

	type outcome struct {
		response *TrafficTopResponse
		err      error
	}

	done := make(chan outcome, 1)

	go func() {
		response, err := server.GetTrafficTop(ctx, &emptypb.Empty{})
		done <- outcome{response: response, err: err}
	}()

	unblock()

	result := <-done
	require.NoError(t, result.err)
	require.EqualValues(t, 4, maximum.Load())

	logger.beforeQuery = nil

	serialCtx := context.WithValue(ctx, itemLookupContextKey{}, true)
	expected, err := server.GetTrafficTop(serialCtx, &emptypb.Empty{})
	require.NoError(t, err)
	require.True(t, proto.Equal(expected, result.response))

	rows := result.response.GetItems()
	require.Len(t, rows, 6)

	for index, ip := range []string{
		"192.0.2.1", "192.0.2.2", "192.0.2.3", "192.0.2.4", "2001:db8::1", "192.0.2.5",
	} {
		require.Equal(t, ip, rows[index].GetIpAddress())
	}

	require.EqualValues(t, 150, rows[0].GetCount())
	require.Equal(t, "Active", rows[0].GetBan().GetReason())
	require.Equal(t, "Ban author", rows[0].GetBan().GetByUser().GetName())
	require.False(t, rows[0].GetInWhitelist())
	require.True(t, rows[1].GetInWhitelist())
	require.EqualValues(t, 999, rows[1].GetBan().GetByUserId())
	require.Nil(t, rows[1].GetBan().GetByUser())
	require.Nil(t, rows[2].GetBan())
	require.EqualValues(t, 0, rows[3].GetBan().GetByUserId())
	require.Nil(t, rows[3].GetBan().GetByUser())
	require.True(t, rows[4].GetBan().GetByUser().GetDeleted())
	require.Empty(t, rows[4].GetBan().GetByUser().GetName())
	require.Equal(t, "https://nic.ru/whois/?query=2001%3Adb8%3A%3A1", rows[4].GetWhoisUrl())
	require.Nil(t, rows[5].GetBan())

	_, err = pool.ExecContext(ctx, `INSERT INTO ip_monitoring
  SELECT ('198.51.100.' || id)::inet, id, CURRENT_DATE FROM generate_series(1, 60) id`)
	require.NoError(t, err)
	limited, err := server.GetTrafficTop(ctx, &emptypb.Empty{})
	require.NoError(t, err)
	require.Len(t, limited.GetItems(), trafficTopLimit)

	for index := 1; index < len(limited.GetItems()); index++ {
		require.GreaterOrEqual(t, limited.GetItems()[index-1].GetCount(), limited.GetItems()[index].GetCount())
	}

	_, err = pool.ExecContext(ctx, "TRUNCATE ip_monitoring")
	require.NoError(t, err)
	empty, err := server.GetTrafficTop(ctx, &emptypb.Empty{})
	require.NoError(t, err)
	require.NotNil(t, empty.GetItems())
	require.Empty(t, empty.GetItems())
}

func TestTrafficTopRequiresModerator(t *testing.T) {
	t.Parallel()

	server := &TrafficGRPCServer{auth: &Auth{}}

	for _, user := range []UserContext{{}, {UserID: 7}, {Roles: []string{users.RoleCommentsModer}}} {
		ctx := context.WithValue(t.Context(), authenticatedUserKey{}, user)
		response, err := server.GetTrafficTop(ctx, &emptypb.Empty{})
		require.Equal(t, codes.PermissionDenied, status.Code(err))
		require.Nil(t, response)
	}
}

func TestTrafficTopReadFailures(t *testing.T) {
	t.Parallel()

	for _, mutation := range []string{
		"ALTER TABLE ip_ban RENAME COLUMN reason TO broken_reason",
		"ALTER TABLE ip_whitelist RENAME COLUMN ip TO broken_ip",
		"ALTER TABLE users RENAME COLUMN name TO broken_name",
	} {
		t.Run(mutation, func(t *testing.T) {
			t.Parallel()

			server, pool, _ := newTrafficTopTestServer(t)
			_, err := pool.ExecContext(t.Context(), mutation)
			require.NoError(t, err)

			ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{Roles: []string{users.RoleModer}})

			ctx, cancel := context.WithTimeout(ctx, 10*time.Second)
			defer cancel()

			response, err := server.GetTrafficTop(ctx, &emptypb.Empty{})
			require.Equal(t, codes.Internal, status.Code(err))
			require.Nil(t, response)
			require.NoError(t, ctx.Err())
			require.Zero(t, pool.Stats().InUse)
		})
	}
}

func TestTrafficTopCancellation(t *testing.T) {
	t.Parallel()

	server, pool, logger := newTrafficTopTestServer(t)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{Roles: []string{users.RoleModer}})

	ctx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()

	var queries atomic.Int64

	started := make(chan struct{}, 4)

	logger.beforeQuery = func() {
		if queries.Add(1) > 1 {
			started <- struct{}{}

			<-ctx.Done()
		}
	}

	done := make(chan error, 1)

	go func() {
		_, extractErr := server.GetTrafficTop(ctx, &emptypb.Empty{})
		done <- extractErr
	}()

	for range 4 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("traffic enrichment reads did not start together")
		}
	}

	cancel()

	err := <-done
	require.Equal(t, codes.Internal, status.Code(err))
	require.Contains(t, status.Convert(err).Message(), "context canceled")
	require.Zero(t, pool.Stats().InUse)
	require.EqualValues(t, 5, queries.Load(), "queued reads must not start after cancellation")
}
