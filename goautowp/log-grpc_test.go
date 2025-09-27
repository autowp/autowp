package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func TestGetEvents(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(
		t.Context(),
		"frontend",
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewLogClient(conn)

	ctx := metadata.AppendToOutgoingContext(
		t.Context(),
		authorizationHeader,
		bearerPrefix+token.AccessToken,
	)

	_, err = client.GetEvents(ctx, &LogEventsRequest{})
	require.NoError(t, err)
}

func TestGetEventsWithFilters(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(
		t.Context(),
		"frontend",
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, token)

	client := NewLogClient(conn)

	ctx := metadata.AppendToOutgoingContext(
		t.Context(),
		authorizationHeader,
		bearerPrefix+token.AccessToken,
	)

	_, err = client.GetEvents(ctx, &LogEventsRequest{
		UserId:    1,
		PictureId: 1,
		ItemId:    1,
		ArticleId: 1,
		Page:      2,
	})
	require.NoError(t, err)
}
