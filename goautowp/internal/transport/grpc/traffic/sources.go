package traffic

import (
	"context"
	"errors"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/traffic"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

func (s *Server) GetTrafficSources(ctx context.Context, _ *emptypb.Empty) (*pb.TrafficSourcesResponse, error) {
	user, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(user.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.traffic.SourcesTraffic(ctx)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := &pb.TrafficSourcesResponse{Items: make([]*pb.TrafficSourceItem, 0, len(rows))}
	for _, row := range rows {
		result.Items = append(result.Items, &pb.TrafficSourceItem{
			Id:                   row.ID,
			Name:                 row.Name,
			Networks:             row.Networks,
			DnsSuffixes:          row.DNSSuffixes,
			References:           row.References,
			Count:                row.Count,
			Addresses:            row.Addresses,
			WhitelistedAddresses: row.WhitelistedAddresses, Bans: rangeBansToProto(row.Bans),
		})
	}

	return result, nil
}

func (s *Server) GetTrafficSourceIPs(
	ctx context.Context,
	request *pb.GetTrafficSourceIPsRequest,
) (*pb.TrafficTopResponse, error) {
	user, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(user.Roles, users.RoleModer) {
		return nil, status.Error(codes.PermissionDenied, "permission denied")
	}

	rows, err := s.traffic.Monitoring.ListSourceIPs(ctx, request.GetSourceId(), trafficTopLimit)
	if errors.Is(err, traffic.ErrSourceNotFound) {
		return nil, status.Error(codes.NotFound, err.Error())
	}

	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return s.extractTop(ctx, user, rows)
}
