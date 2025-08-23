package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

const tokenWithInvalidSignature = "eyJhbGciOiJIUzUxMiIsInR5cCI6IkpXVCJ9." +
	"eyJhdWQiOiJkZWZhdWx0Iiwic3ViIjoiMSJ9." +
	"yuzUurjlDfEKchYseIrHQ1D5_RWnSuMxM-iK9FDNlQBBw8kCz3H-94xHvyd9pAA6Ry2-YkGi1v6Y3AHIpkDpcQ"

func TestGetVehicleTypesInaccessibleAnonymously(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	_, err = srv.GetVehicleTypes(t.Context(), &emptypb.Empty{})
	require.Error(t, err)
}

func TestGetVehicleTypesInaccessibleWithEmptyToken(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{authorizationHeader: bearerPrefix}),
	)

	_, err = srv.GetVehicleTypes(ctx, &emptypb.Empty{})
	require.Error(t, err)
}

func TestGetVehicleTypesInaccessibleWithInvalidToken(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{authorizationHeader: bearerPrefix + "abc"}),
	)

	_, err = srv.GetVehicleTypes(ctx, &emptypb.Empty{})
	require.Error(t, err)
}

func TestGetVehicleTypesInaccessibleWithWronglySignedToken(t *testing.T) {
	t.Parallel()

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	ctx := metadata.NewIncomingContext(
		t.Context(),
		metadata.New(map[string]string{
			authorizationHeader: bearerPrefix + tokenWithInvalidSignature,
		}),
	)

	_, err = srv.GetVehicleTypes(ctx, &emptypb.Empty{})
	require.Error(t, err)
}

func TestGetVehicleTypesInaccessibleWithoutModeratePrivilege(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	ctx := t.Context()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, testUsername, testPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	_, err = srv.GetVehicleTypes(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&emptypb.Empty{},
	)
	require.Error(t, err)
}

func TestGetVehicleTypes(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	ctx := t.Context()

	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, "frontend", "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	srv, err := cnt.GRPCServer(t.Context())
	require.NoError(t, err)

	result, err := srv.GetVehicleTypes(
		metadata.NewIncomingContext(
			ctx,
			metadata.New(map[string]string{authorizationHeader: bearerPrefix + token.AccessToken}),
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
	require.NotEmpty(t, result.GetItems())
}
