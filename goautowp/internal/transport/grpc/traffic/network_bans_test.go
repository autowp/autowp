package traffic

import (
	"net"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestTrafficNetworkBanManagement(t *testing.T) {
	t.Parallel()
	server, _, _ := newTrafficTopTestServer(t)
	ctx := authn.WithUser(t.Context(), authn.User{UserID: 9, Roles: []string{users.RoleModer, users.RoleUsersModer}})
	_, err := server.CreateTrafficNetworkBan(
		ctx,
		&pb.CreateTrafficNetworkBanRequest{Network: "192.0.2.12/24", PeriodHours: 24},
	)
	require.NoError(t, err)
	active, err := server.traffic.Ban.ActiveRanges(ctx)
	require.NoError(t, err)

	var found bool

	for _, blocked := range active {
		if blocked.Network == "192.0.2.0/24" {
			found = true

			require.EqualValues(t, 9, blocked.ByUserID)
		}
	}

	require.True(t, found)

	response, err := server.GetTrafficNetworks(ctx, &emptypb.Empty{})
	require.NoError(t, err)
	require.NotEmpty(t, response.GetItems())
	item := response.GetItems()[0]
	require.Equal(t, "192.0.2.0/24", item.GetNetwork())
	require.NotNil(t, item.GetBan())
	require.Len(t, item.GetBans(), 4) // Network and three active individual bans; expired host is omitted.

	exists, err := server.traffic.Ban.Exists(ctx, net.ParseIP("192.0.2.240"))
	require.NoError(t, err)
	require.True(t, exists)
	// A whitelisted neighbor keeps its exemption from a network ban.
	require.NoError(t, server.traffic.Whitelist.Add(ctx, net.ParseIP("192.0.2.241"), "exempt"))
	exists, err = server.traffic.Ban.Exists(ctx, net.ParseIP("192.0.2.241"))
	require.NoError(t, err)
	require.False(t, exists)

	_, err = server.DeleteTrafficNetworkBan(ctx, &pb.DeleteTrafficNetworkBanRequest{Network: "192.0.2.0/24"})
	require.NoError(t, err)
	exists, err = server.traffic.Ban.Exists(ctx, net.ParseIP("192.0.2.240"))
	require.NoError(t, err)
	require.False(t, exists)
	exists, err = server.traffic.Ban.Exists(ctx, net.ParseIP("192.0.2.1"))
	require.NoError(t, err)
	require.True(t, exists, "removing the network ban must preserve individual bans")
}

func TestTrafficNetworkBanAuthorizationAndValidation(t *testing.T) {
	t.Parallel()
	server, _, _ := newTrafficTopTestServer(t)
	viewer := authn.WithUser(t.Context(), authn.User{UserID: 9, Roles: []string{users.RoleModer}})
	_, err := server.CreateTrafficNetworkBan(
		viewer,
		&pb.CreateTrafficNetworkBanRequest{Network: "192.0.2.0/24", PeriodHours: 24},
	)
	require.Equal(t, codes.PermissionDenied, status.Code(err))
	_, err = server.DeleteTrafficNetworkBan(viewer, &pb.DeleteTrafficNetworkBanRequest{Network: "192.0.2.0/24"})
	require.Equal(t, codes.PermissionDenied, status.Code(err))

	ctx := authn.WithUser(t.Context(), authn.User{UserID: 9, Roles: []string{users.RoleUsersModer}})
	for _, request := range []*pb.CreateTrafficNetworkBanRequest{
		{Network: "192.0.2.0/23", PeriodHours: 24},
		{Network: "192.0.2.0/24", PeriodHours: 0},
		{Network: "2001:db8::/63", PeriodHours: 24},
		{Network: "::ffff:192.0.2.0/120", PeriodHours: 24},
	} {
		_, err := server.CreateTrafficNetworkBan(ctx, request)
		require.Equal(t, codes.InvalidArgument, status.Code(err))
	}
}
