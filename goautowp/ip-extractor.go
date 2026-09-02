package goautowp

import (
	"context"
	"errors"
	"net"

	"github.com/autowp/goautowp/ban"
	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"google.golang.org/protobuf/types/known/timestamppb"
)

type IPExtractor struct {
	banRepository  *ban.Repository
	userRepository *users.Repository
	userExtractor  *UserExtractor
}

func NewIPExtractor(
	banRepository *ban.Repository,
	userRepository *users.Repository,
	userExtractor *UserExtractor,
) *IPExtractor {
	return &IPExtractor{
		banRepository:  banRepository,
		userRepository: userRepository,
		userExtractor:  userExtractor,
	}
}

func (s *IPExtractor) Extract(
	ctx context.Context, ip net.IP, fields map[string]bool, userID int64, roles []string,
) (*IP, error) {
	result := IP{
		Address: ip.String(),
	}

	_, ok := fields["hostname"]
	if ok {
		host, err := net.DefaultResolver.LookupAddr(ctx, ip.String())
		if err != nil {
			logging.Errorf("LookupAddr error: %v", err.Error())
		}

		if len(host) > 0 {
			result.Hostname = host[0]
		}
	}

	_, ok = fields["blacklist"]

	if ok {
		canView := len(roles) > 0 && util.Contains(roles, users.RoleModer)

		if canView {
			result.Blacklist = nil

			banItem, err := s.banRepository.Get(ctx, ip)
			if err != nil && !errors.Is(err, ban.ErrBanItemNotFound) {
				return nil, err
			}

			if banItem != nil {
				result.Blacklist = &BanItem{
					Until:    timestamppb.New(banItem.Until),
					ByUserId: banItem.ByUserID,
					ByUser:   nil,
					Reason:   banItem.Reason,
				}

				user, err := s.userRepository.User(
					ctx,
					&query.UserListOptions{ID: banItem.ByUserID},
					users.UserFields{},
					users.OrderByNone,
				)
				if err != nil && !errors.Is(err, users.ErrUserNotFound) {
					return nil, err
				}

				if user != nil {
					apiUser, err := s.userExtractor.Extract(ctx, user, nil, userID, roles)
					if err != nil {
						return nil, err
					}

					result.Blacklist.ByUser = apiUser
				}
			}
		}
	}

	_, ok = fields["rights"]
	if ok {
		canBan := len(roles) > 0 && util.Contains(roles, users.RoleUsersModer)

		result.Rights = &IPRights{
			AddToBlacklist:      canBan,
			RemoveFromBlacklist: canBan,
		}
	}

	return &result, nil
}
