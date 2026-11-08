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
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

func TestTrafficSourceManagement(t *testing.T) {
	t.Parallel()
	server, _, _ := newTrafficTopTestServer(t)
	ctx := authn.WithUser(t.Context(), authn.User{UserID: 9, Roles: []string{users.RoleModer}})
	source := &pb.TrafficSourceItem{
		Id:       "fixture",
		Name:     "Fixture",
		Networks: []string{"192.0.2.12/24", "2001:db8::/64"},
	}
	_, err := server.CreateTrafficSource(ctx, &pb.CreateTrafficSourceRequest{Source: source})
	require.NoError(t, err)
	response, err := server.GetTrafficSources(ctx, &emptypb.Empty{})
	require.NoError(t, err)

	var found *pb.TrafficSourceItem

	for _, item := range response.GetItems() {
		if item.GetId() == source.GetId() {
			found = item
		}
	}

	require.NotNil(t, found)
	require.Equal(t, []string{"192.0.2.0/24", "2001:db8::/64"}, found.GetNetworks())
	require.EqualValues(t, 500, found.GetCount())
	require.EqualValues(t, 6, found.GetAddresses())
	require.EqualValues(t, 2, found.GetWhitelistedAddresses())

	_, err = server.CreateTrafficSource(ctx, &pb.CreateTrafficSourceRequest{Source: source})
	require.Equal(t, codes.AlreadyExists, status.Code(err))
	_, err = server.UpdateTrafficSource(ctx, &pb.UpdateTrafficSourceRequest{
		Source: &pb.TrafficSourceItem{
			Id:   "fixture",
			Name: "Renamed",
		},
		UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"name"}},
	})
	require.NoError(t, err)
	ips, err := server.GetTrafficSourceIPs(ctx, &pb.GetTrafficSourceIPsRequest{SourceId: "fixture"})
	require.NoError(t, err)
	require.Len(t, ips.GetItems(), 6)
	require.Equal(t, "192.0.2.1", ips.GetItems()[0].GetIpAddress())

	_, err = server.UpdateTrafficSource(ctx, &pb.UpdateTrafficSourceRequest{
		Source: source, UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"count"}},
	})
	require.Equal(t, codes.InvalidArgument, status.Code(err))
	_, err = server.DeleteTrafficSource(ctx, &pb.DeleteTrafficSourceRequest{Id: "fixture"})
	require.NoError(t, err)
	_, err = server.GetTrafficSourceIPs(ctx, &pb.GetTrafficSourceIPsRequest{SourceId: "fixture"})
	require.Equal(t, codes.NotFound, status.Code(err))
}

func TestTrafficSourceAuthorizationAndValidation(t *testing.T) {
	t.Parallel()
	server, _, _ := newTrafficTopTestServer(t)
	visitor := authn.WithUser(t.Context(), authn.User{UserID: 9})
	_, err := server.CreateTrafficSource(visitor, &pb.CreateTrafficSourceRequest{})
	require.Equal(t, codes.PermissionDenied, status.Code(err))
	_, err = server.GetTrafficSources(visitor, &emptypb.Empty{})
	require.Equal(t, codes.PermissionDenied, status.Code(err))

	ctx := authn.WithUser(t.Context(), authn.User{UserID: 9, Roles: []string{users.RoleModer}})
	for _, source := range []*pb.TrafficSourceItem{
		{Id: "bad id", Name: "Name"},
		{Id: "empty", Name: " "},
		{Id: "bad-prefix", Name: "Name", Networks: []string{"192.0.2.1/99"}},
		{Id: "bad-domain", Name: "Name", DnsSuffixes: []string{"*.example.com"}},
		{Id: "bad-url", Name: "Name", References: []string{"javascript:alert(1)"}},
	} {
		_, err := server.CreateTrafficSource(ctx, &pb.CreateTrafficSourceRequest{Source: source})
		require.Equal(t, codes.InvalidArgument, status.Code(err))
	}
}
