package traffic

import (
	"context"
	"net/netip"
	"time"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/ban"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const maxNetworkBanHours = 24 * 365

func (s *Server) CreateTrafficNetworkBan(
	ctx context.Context,
	request *pb.CreateTrafficNetworkBanRequest,
) (*emptypb.Empty, error) {
	userID, err := s.networkBanUser(ctx)
	if err != nil {
		return nil, err
	}

	prefix, err := parseBanNetwork(request.GetNetwork())
	if err != nil {
		return nil, err
	}

	if request.GetPeriodHours() < 1 || request.GetPeriodHours() > maxNetworkBanHours {
		return nil, status.Error(codes.InvalidArgument, "invalid ban duration")
	}

	err = s.traffic.Ban.AddManualNetwork(ctx, prefix, time.Duration(request.GetPeriodHours())*time.Hour, userID)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) DeleteTrafficNetworkBan(
	ctx context.Context,
	request *pb.DeleteTrafficNetworkBanRequest,
) (*emptypb.Empty, error) {
	if _, err := s.networkBanUser(ctx); err != nil {
		return nil, err
	}

	prefix, err := parseBanNetwork(request.GetNetwork())
	if err != nil {
		return nil, err
	}

	if err := s.traffic.Ban.RemoveNetwork(ctx, prefix); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) networkBanUser(ctx context.Context) (int64, error) {
	user, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return 0, grpcerrors.Authentication(err)
	}

	if !util.Contains(user.Roles, users.RoleUsersModer) {
		return 0, status.Error(codes.PermissionDenied, "permission denied")
	}

	return user.UserID, nil
}

func parseBanNetwork(network string) (netip.Prefix, error) {
	prefix, err := netip.ParsePrefix(network)
	if err != nil {
		return netip.Prefix{}, status.Error(codes.InvalidArgument, "invalid network")
	}

	isIPv4 := prefix.Addr().Is4() && prefix.Bits() == ban.IPv4NetworkBits

	isIPv6 := prefix.Addr().Is6() && !prefix.Addr().Is4In6() && prefix.Bits() == ban.IPv6NetworkBits
	if !isIPv4 && !isIPv6 {
		return netip.Prefix{}, status.Error(codes.InvalidArgument, "expected IPv4 /24 or IPv6 /64")
	}

	return prefix.Masked(), nil
}

func rangeBansToProto(rows []ban.RangeBan) []*pb.TrafficRangeBan {
	result := make([]*pb.TrafficRangeBan, 0, len(rows))
	for _, row := range rows {
		result = append(result, &pb.TrafficRangeBan{Network: row.Network, Ban: &pb.BanItem{
			Until: timestamppb.New(row.Until), ByUserId: row.ByUserID, Reason: row.Reason,
		}})
	}

	return result
}
