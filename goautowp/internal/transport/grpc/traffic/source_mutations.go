package traffic

import (
	"context"
	"errors"
	"net/netip"
	"net/url"
	"regexp"
	"slices"
	"strings"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/traffic"
	"github.com/autowp/goautowp/internal/transport/grpc/grpcerrors"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

const (
	sourceRuleLimit = 1000
	sourceTextLimit = 200
	sourceURLLimit  = 2000
)

var (
	sourceIDPattern     = regexp.MustCompile(`^[a-z][a-z0-9_-]{0,63}$`)
	sourceDomainPattern = regexp.MustCompile(`^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$`)
)

func (s *Server) CreateTrafficSource(
	ctx context.Context,
	request *pb.CreateTrafficSourceRequest,
) (*emptypb.Empty, error) {
	if err := s.authorizeSources(ctx); err != nil {
		return nil, err
	}

	source, err := validateSource(request.GetSource(), []string{"name", "networks", "dns_suffixes", "references"})
	if err != nil {
		return nil, err
	}

	return sourceMutationResult(s.traffic.Monitoring.CreateSource(ctx, source))
}

func (s *Server) UpdateTrafficSource(
	ctx context.Context,
	request *pb.UpdateTrafficSourceRequest,
) (*emptypb.Empty, error) {
	if err := s.authorizeSources(ctx); err != nil {
		return nil, err
	}

	paths := request.GetUpdateMask().GetPaths()
	if len(paths) == 0 {
		return nil, status.Error(codes.InvalidArgument, "update_mask is required")
	}

	source, err := validateSource(request.GetSource(), paths)
	if err != nil {
		return nil, err
	}

	return sourceMutationResult(s.traffic.Monitoring.UpdateSource(ctx, source, paths))
}

func (s *Server) DeleteTrafficSource(
	ctx context.Context,
	request *pb.DeleteTrafficSourceRequest,
) (*emptypb.Empty, error) {
	if err := s.authorizeSources(ctx); err != nil {
		return nil, err
	}

	if !sourceIDPattern.MatchString(request.GetId()) {
		return nil, status.Error(codes.InvalidArgument, "invalid source id")
	}

	return sourceMutationResult(s.traffic.Monitoring.DeleteSource(ctx, request.GetId()))
}

func (s *Server) authorizeSources(ctx context.Context) error {
	user, err := s.auth.ValidateGRPC(ctx)
	if err != nil {
		return grpcerrors.Authentication(err)
	}

	if !util.Contains(user.Roles, users.RoleModer) {
		return status.Error(codes.PermissionDenied, "permission denied")
	}

	return nil
}

func validateSource(input *pb.TrafficSourceItem, paths []string) (traffic.Source, error) {
	if !sourceIDPattern.MatchString(input.GetId()) {
		return traffic.Source{}, status.Error(codes.InvalidArgument, "invalid source id")
	}

	source := traffic.Source{
		ID:   input.GetId(),
		Name: strings.TrimSpace(input.GetName()),
		Networks: slices.Clone(
			input.GetNetworks(),
		),
		DNSSuffixes: slices.Clone(input.GetDnsSuffixes()),
		References:  slices.Clone(input.GetReferences()),
	}

	for _, path := range paths {
		switch path {
		case "name":
			if source.Name == "" || len(source.Name) > sourceTextLimit {
				return traffic.Source{}, status.Error(codes.InvalidArgument, "invalid source name")
			}
		case "networks":
			if len(source.Networks) > sourceRuleLimit {
				return traffic.Source{}, status.Error(codes.InvalidArgument, "too many networks")
			}

			for i, network := range source.Networks {
				prefix, err := netip.ParsePrefix(strings.TrimSpace(network))
				if err != nil || prefix.Addr().Is4In6() {
					return traffic.Source{}, status.Errorf(codes.InvalidArgument, "invalid network: %s", network)
				}

				source.Networks[i] = prefix.Masked().String()
			}

			source.Networks = slices.Compact(source.Networks)
		case "dns_suffixes":
			if len(source.DNSSuffixes) > sourceRuleLimit {
				return traffic.Source{}, status.Error(codes.InvalidArgument, "too many DNS suffixes")
			}

			for i, domain := range source.DNSSuffixes {
				domain = strings.ToLower(strings.TrimSpace(domain))
				if len(domain) > sourceTextLimit || !sourceDomainPattern.MatchString(domain) {
					return traffic.Source{}, status.Errorf(codes.InvalidArgument, "invalid DNS suffix: %s", domain)
				}

				source.DNSSuffixes[i] = domain
			}
		case "references":
			if len(source.References) > sourceRuleLimit {
				return traffic.Source{}, status.Error(codes.InvalidArgument, "too many references")
			}

			for i, reference := range source.References {
				reference = strings.TrimSpace(reference)

				parsed, err := url.Parse(reference)
				if err != nil || parsed.Hostname() == "" || (parsed.Scheme != "https" && parsed.Scheme != "http") ||
					len(reference) > sourceURLLimit {
					return traffic.Source{}, status.Errorf(
						codes.InvalidArgument,
						"invalid reference URL: %s",
						reference,
					)
				}

				source.References[i] = reference
			}
		default:
			return traffic.Source{}, status.Errorf(codes.InvalidArgument, "unknown update path: %s", path)
		}
	}

	return source, nil
}

func sourceMutationResult(err error) (*emptypb.Empty, error) {
	switch {
	case errors.Is(err, traffic.ErrSourceNotFound):
		return nil, status.Error(codes.NotFound, err.Error())
	case errors.Is(err, traffic.ErrSourceExists):
		return nil, status.Error(codes.AlreadyExists, err.Error())
	case err != nil:
		return nil, status.Error(codes.Internal, err.Error())
	default:
		return &emptypb.Empty{}, nil
	}
}
