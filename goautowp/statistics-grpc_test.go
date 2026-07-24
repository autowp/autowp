package goautowp

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

var errGridIsEmpty = errors.New("grid is empty")

func assertGridNotEmpty(grid []*PulseGrid) error {
	for _, lines := range grid {
		for _, y := range lines.GetLine() {
			if y > 0 {
				return nil
			}
		}
	}

	return errGridIsEmpty
}

func TestStatisticsPulse(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	ctxTimeout, cancel := context.WithTimeout(ctx, 5000*time.Second)
	defer cancel()

	statisticsClient := NewStatisticsClient(conn)

	cfg := config.LoadConfig(".")

	db, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	kc := cnt.Keycloak()
	token, err := kc.Login(
		ctxTimeout,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, token)

	usersClient := NewUsersClient(conn)
	user, err := usersClient.Me(
		metadata.AppendToOutgoingContext(
			ctxTimeout,
			authorizationHeader,
			bearerPrefix+token.AccessToken,
		),
		&APIMeRequest{},
	)
	require.NoError(t, err)

	_, err = db.Insert(schema.LogEventTable).
		Cols(schema.LogEventTableDescriptionColName, schema.LogEventTableUserIDColName,
			schema.LogEventTableCreatedAtColName).
		Vals(
			goqu.Vals{"Description", user.GetId(), goqu.Func("NOW")},
		).Executor().ExecContext(ctx)
	require.NoError(t, err)

	r1, err := statisticsClient.GetPulse(ctxTimeout, &PulseRequest{})
	require.NoError(t, err)

	_, err = statisticsClient.GetPulse(ctxTimeout, &PulseRequest{
		Period: PulseRequest_DEFAULT,
	})
	require.NoError(t, err)

	require.NoError(t, assertGridNotEmpty(r1.GetGrid()))

	r1, err = statisticsClient.GetPulse(ctxTimeout, &PulseRequest{
		Period: PulseRequest_MONTH,
	})
	require.NoError(t, err)

	require.NoError(t, assertGridNotEmpty(r1.GetGrid()))

	r1, err = statisticsClient.GetPulse(ctxTimeout, &PulseRequest{
		Period: PulseRequest_YEAR,
	})
	require.NoError(t, err)

	require.NoError(t, assertGridNotEmpty(r1.GetGrid()))
}

func TestAboutData(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	statisticsClient := NewStatisticsClient(conn)

	_, err := statisticsClient.GetAboutData(ctx, &emptypb.Empty{})
	require.NoError(t, err)
}

func BenchmarkAboutData(b *testing.B) {
	ctx := b.Context()

	statisticsClient := NewStatisticsClient(conn)

	for range b.N {
		_, err := statisticsClient.GetAboutData(ctx, &emptypb.Empty{})
		require.NoError(b, err)
	}
}
