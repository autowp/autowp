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

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	trafficSrv, err := cnt.TrafficGRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
	)

	_, err = trafficSrv.DeleteFromBlacklist(
		ctx,
		&DeleteFromTrafficBlacklistRequest{Ip: "127.0.0.1"},
	)
	require.NoError(t, err)

	_, err = trafficSrv.AddToBlacklist(ctx, &AddToTrafficBlacklistRequest{
		Ip:     "127.0.0.1",
		Period: 3,
		Reason: "Test",
	})
	require.NoError(t, err)

	ip, err := srv.GetIP(ctx, &APIGetIPRequest{
		Ip:     "127.0.0.1",
		Fields: []string{"blacklist"},
	})
	require.NoError(t, err)
	require.NotNil(t, ip.GetBlacklist())

	_, err = trafficSrv.DeleteFromBlacklist(
		ctx,
		&DeleteFromTrafficBlacklistRequest{Ip: "127.0.0.1"},
	)
	require.NoError(t, err)

	ip, err = srv.GetIP(ctx, &APIGetIPRequest{Ip: "127.0.0.1"})
	require.NoError(t, err)
	require.Nil(t, ip.GetBlacklist())
}

func TestTop(t *testing.T) {
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

	srv, err := cnt.TrafficGRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
	)

	_, err = srv.GetTop(ctx, &emptypb.Empty{})
	require.NoError(t, err)
}

func TestWhitelist(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")
	ctx := t.Context()

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

	srv, err := cnt.TrafficGRPCServer(t.Context())
	require.NoError(t, err)

	_, err = srv.AddToWhitelist(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&AddToTrafficWhitelistRequest{Ip: "192.168.0.1"},
	)
	require.NoError(t, err)

	_, err = srv.GetTrafficWhitelist(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = srv.DeleteFromWhitelist(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&DeleteFromTrafficWhitelistRequest{Ip: "192.168.0.1"},
	)
	require.NoError(t, err)
}
