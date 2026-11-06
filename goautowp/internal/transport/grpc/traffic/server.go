package traffic

import (
	"context"
	"errors"
	"net"
	"net/url"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/ban"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/traffic"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/transport/grpc/userextractor"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/timestamppb"
)

const trafficTopLimit = 50

type Server struct {
	pb.UnimplementedTrafficServer

	auth            *authn.Service
	usersRepository *users.Repository
	userExtractor   *userextractor.Extractor
	traffic         *traffic.Repository
}

func NewServer(
	auth *authn.Service,
	usersRepository *users.Repository,
	userExtractor *userextractor.Extractor,
	traffic *traffic.Repository,
) *Server {
	return &Server{
		auth:            auth,
		usersRepository: usersRepository,
		userExtractor:   userExtractor,
		traffic:         traffic,
	}
}

func (s *Server) GetTrafficTop(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.TrafficTopResponse, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	items, err := s.traffic.Monitoring.ListOfTop(ctx, trafficTopLimit)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return s.extractTop(ctx, userCtx, items)
}

func (s *Server) DeleteTrafficBlacklistItem(
	ctx context.Context,
	in *pb.DeleteTrafficBlacklistItemRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleUsersModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	ip := net.ParseIP(in.GetIpAddress())
	if ip == nil {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	err = s.traffic.Ban.Remove(ctx, ip)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) DeleteTrafficWhitelistItem(
	ctx context.Context,
	in *pb.DeleteTrafficWhitelistItemRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	ip := net.ParseIP(in.GetIpAddress())
	if ip == nil {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	err = s.traffic.Whitelist.Remove(ctx, ip)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) CreateTrafficBlacklistItem(
	ctx context.Context,
	in *pb.CreateTrafficBlacklistItemRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleUsersModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	item := in.GetItem()

	ip := net.ParseIP(item.GetIpAddress())
	if ip == nil {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	duration := time.Hour * time.Duration(item.GetPeriod())

	err = s.traffic.Ban.Add(ctx, ip, duration, userCtx.UserID, item.GetReason())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) CreateTrafficWhitelistItem(
	ctx context.Context,
	in *pb.CreateTrafficWhitelistItemRequest,
) (*emptypb.Empty, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	item := in.GetItem()

	ip := net.ParseIP(item.GetIpAddress())
	if ip == nil {
		return nil, status.Errorf(codes.InvalidArgument, "invalid argument")
	}

	ctx = context.WithoutCancel(ctx)

	err = s.traffic.Whitelist.Add(ctx, ip, "manual click")
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	err = s.traffic.Ban.Remove(ctx, ip)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	if err = s.traffic.Monitoring.ClearIP(ctx, ip); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &emptypb.Empty{}, nil
}

func (s *Server) GetTrafficWhitelistItems(
	ctx context.Context,
	in *pb.GetTrafficWhitelistItemsRequest,
) (*pb.TrafficWhitelistItems, error) {
	userCtx, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return nil, grpcerrors.Authentication(err)
	}

	if !util.Contains(userCtx.Roles, users.RoleModer) {
		return nil, status.Errorf(codes.PermissionDenied, "permission denied")
	}

	list, pages, err := s.traffic.Whitelist.List(ctx, in.GetPage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	result := make([]*pb.TrafficWhitelistItem, len(list))
	for idx, i := range list {
		result[idx] = &pb.TrafficWhitelistItem{
			IpAddress:   i.IP.String(),
			Description: i.Description,
		}
	}

	return &pb.TrafficWhitelistItems{
		Items: result,
		Paginator: &pb.Pages{
			PageCount:      pages.PageCount,
			Current:        pages.Current,
			TotalItemCount: pages.TotalItemCount,
		},
	}, nil
}

func (s *Server) extractTop(
	ctx context.Context,
	userCtx authn.User,
	items []traffic.ListOfTopItem,
) (*pb.TrafficTopResponse, error) {
	result := make([]*pb.TrafficTopItem, len(items))
	lookups := make([]func(context.Context) error, 0, 2*len(items))

	for idx, item := range items {
		resultRow := &pb.TrafficTopItem{
			IpAddress: item.IP.String(),
			Count:     int32(item.Count), //nolint: gosec
			WhoisUrl:  "https://nic.ru/whois/?query=" + url.QueryEscape(item.IP.String()),
		}
		result[idx] = resultRow

		// Flatten both independent reads for every IP into the same bounded queue.
		// The user lookup/extraction stays in the ban branch and inherits its slot.
		lookups = append(lookups, func(ctx context.Context) error {
			banItem, banErr := s.traffic.Ban.Get(ctx, item.IP)
			if banErr != nil && !errors.Is(banErr, ban.ErrBanItemNotFound) {
				return banErr
			}

			var (
				user *schema.UsersRow
				err  error
			)

			if banItem != nil {
				resultRow.NetworkBan = banItem.Network

				var extractedUser *pb.User

				if banItem.ByUserID > 0 {
					user, err = s.usersRepository.User(ctx, &query.UserListOptions{
						ID: banItem.ByUserID,
					}, users.UserFields{}, users.OrderByNone)
					if err != nil && !errors.Is(err, users.ErrUserNotFound) {
						return err
					}

					if user != nil {
						extractedUser, err = s.userExtractor.Extract(
							ctx,
							user,
							nil,
							userCtx.UserID,
							userCtx.Roles,
						)
						if err != nil {
							return err
						}
					}
				}

				resultRow.Ban = &pb.BanItem{
					Until:    timestamppb.New(banItem.Until),
					ByUserId: banItem.ByUserID,
					ByUser:   extractedUser,
					Reason:   banItem.Reason,
				}
			}

			return nil
		}, func(ctx context.Context) error {
			inWhitelist, err := s.traffic.Whitelist.Exists(ctx, item.IP)
			if err != nil {
				return err
			}

			resultRow.InWhitelist = inWhitelist

			return nil
		})
	}

	if err := extraction.Run(ctx, lookups); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.TrafficTopResponse{
		Items: result,
	}, nil
}
