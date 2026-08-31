package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestHttpBanPost(t *testing.T) {
	t.Parallel()

	// A dedicated RFC 5737 TEST-NET-3 address, used by no other test, so that `go test ./...`
	// running this package concurrently with the `traffic` and `ban` packages against the shared
	// database can't add, remove, or auto-whitelist this ban out from under us mid-test. It is
	// deliberately not a loopback/private address, which the ban interceptor and AutoWhitelist
	// treat specially.
	const bannedIP = "203.0.113.9"

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(
		t.Context(),
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, token)

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	trafficSrv, err := cnt.TrafficGRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
	)

	_, err = trafficSrv.DeleteTrafficBlacklistItem(
		ctx,
		&DeleteTrafficBlacklistItemRequest{IpAddress: bannedIP},
	)
	require.NoError(t, err)

	_, err = trafficSrv.CreateTrafficBlacklistItem(ctx, &CreateTrafficBlacklistItemRequest{
		Item: &TrafficBlacklistItem{
			IpAddress: bannedIP,
			Period:    3,
			Reason:    "Test",
		},
	})
	require.NoError(t, err)

	ip, err := srv.GetIP(ctx, &GetIPRequest{
		IpAddress: bannedIP,
		Fields:    []string{"blacklist"},
	})
	require.NoError(t, err)
	require.NotNil(t, ip.GetBlacklist())

	_, err = trafficSrv.DeleteTrafficBlacklistItem(
		ctx,
		&DeleteTrafficBlacklistItemRequest{IpAddress: bannedIP},
	)
	require.NoError(t, err)

	ip, err = srv.GetIP(ctx, &GetIPRequest{IpAddress: bannedIP})
	require.NoError(t, err)
	require.Nil(t, ip.GetBlacklist())
}

func TestTop(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	token, err := kc.Login(
		t.Context(),
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, token)

	srv, err := cnt.TrafficGRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
	)

	_, err = srv.GetTrafficTop(ctx, &emptypb.Empty{})
	require.NoError(t, err)
}

func TestWhitelist(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	ctx := t.Context()

	kc := cnt.Keycloak()
	token, err := kc.Login(
		t.Context(),
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, token)

	srv, err := cnt.TrafficGRPCServer(t.Context())
	require.NoError(t, err)

	_, err = srv.CreateTrafficWhitelistItem(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&CreateTrafficWhitelistItemRequest{Item: &TrafficWhitelistItem{
			IpAddress: "192.168.0.1",
		}},
	)
	require.NoError(t, err)

	_, err = srv.GetTrafficWhitelistItems(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = srv.DeleteTrafficWhitelistItem(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&DeleteTrafficWhitelistItemRequest{IpAddress: "192.168.0.1"},
	)
	require.NoError(t, err)
}
