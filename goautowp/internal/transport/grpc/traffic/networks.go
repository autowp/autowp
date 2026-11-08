package traffic

import (
	"context"
	"net/netip"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/ban"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

func (s *Server) GetTrafficNetworks(ctx context.Context, _ *emptypb.Empty) (*pb.TrafficNetworksResponse, error) {
	user, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(user.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.traffic.NetworksTraffic(ctx, trafficTopLimit)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := &pb.TrafficNetworksResponse{Items: make([]*pb.TrafficNetworkItem, 0, len(rows))}
	for _, row := range rows {
		item := &pb.TrafficNetworkItem{
			Network:   row.Network,
			Count:     row.Count,
			Addresses: row.Addresses,
			Bans:      rangeBansToProto(row.Bans),
		}
		for _, blocked := range item.GetBans() {
			if blocked.GetNetwork() == row.Network {
				item.Ban = blocked.GetBan()

				break
			}
		}

		result.Items = append(result.Items, item)
	}

	return result, nil
}

func (s *Server) GetTrafficNetworkIPs(
	ctx context.Context,
	request *pb.GetTrafficNetworkIPsRequest,
) (*pb.TrafficTopResponse, error) {
	user, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(user.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	prefix, err := netip.ParsePrefix(request.GetNetwork())
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, "invalid network")
	}

	isIPv4 := prefix.Addr().Is4() && prefix.Bits() == ban.IPv4NetworkBits

	isIPv6 := prefix.Addr().Is6() && !prefix.Addr().Is4In6() && prefix.Bits() == ban.IPv6NetworkBits
	if !isIPv4 && !isIPv6 {
		return nil, status.Error(codes.InvalidArgument, "expected IPv4 /24 or IPv6 /64")
	}

	items, err := s.traffic.Monitoring.ListNetworkIPs(ctx, prefix.Masked(), trafficTopLimit)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return s.extractTop(ctx, user, items)
}
