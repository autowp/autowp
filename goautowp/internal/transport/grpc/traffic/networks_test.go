package traffic

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestTrafficNetworks(t *testing.T) {
	t.Parallel()
	server, pool, _ := newTrafficTopTestServer(t)
	ctx := authn.WithUser(t.Context(), authn.User{UserID: 9, Roles: []string{users.RoleModer}})
	_, err := pool.ExecContext(ctx, `
 INSERT INTO ip_monitoring VALUES ('2001:db8::2', 40, CURRENT_DATE);
 INSERT INTO ip_ban VALUES ('192.0.2.0/24', NOW() + INTERVAL '1 minute', 'Network quota', NULL);`)
	require.NoError(t, err)
	response, err := server.GetTrafficNetworks(ctx, &emptypb.Empty{})
	require.NoError(t, err)
	require.Len(t, response.GetItems(), 2)
	require.Equal(t, "192.0.2.0/24", response.GetItems()[0].GetNetwork())
	require.EqualValues(t, 350, response.GetItems()[0].GetCount())
	require.EqualValues(t, 4, response.GetItems()[0].GetAddresses())
	require.Equal(t, "Network quota", response.GetItems()[0].GetBan().GetReason())
	require.Equal(t, "2001:db8::/64", response.GetItems()[1].GetNetwork())
	require.Nil(t, response.GetItems()[1].GetBan())

	ips, err := server.GetTrafficNetworkIPs(ctx, &pb.GetTrafficNetworkIPsRequest{Network: "192.0.2.0/24"})
	require.NoError(t, err)
	require.Len(t, ips.GetItems(), 4)
	require.Equal(t, "192.0.2.1", ips.GetItems()[0].GetIpAddress())
	require.False(t, ips.GetItems()[0].GetNetworkBan()) // The longer individual ban is reported.
	require.Equal(t, "192.0.2.3", ips.GetItems()[1].GetIpAddress())
	require.True(t, ips.GetItems()[1].GetNetworkBan())
	require.Equal(t, "Network quota", ips.GetItems()[1].GetBan().GetReason())

	for _, network := range []string{"invalid", "192.0.2.1", "192.0.2.0/25", "2001:db8::/48", "::ffff:192.0.2.0/64"} {
		_, err := server.GetTrafficNetworkIPs(ctx, &pb.GetTrafficNetworkIPsRequest{Network: network})
		require.Equal(t, codes.InvalidArgument, status.Code(err), network)
	}

	denied := authn.WithUser(t.Context(), authn.User{UserID: 9})
	_, err = server.GetTrafficNetworks(denied, &emptypb.Empty{})
	require.Equal(t, codes.PermissionDenied, status.Code(err))
	_, err = server.GetTrafficNetworkIPs(denied, &pb.GetTrafficNetworkIPsRequest{Network: "192.0.2.0/24"})
	require.Equal(t, codes.PermissionDenied, status.Code(err))
}
