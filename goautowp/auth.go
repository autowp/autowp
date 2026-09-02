package goautowp

import (
	"context"
	"errors"
	"fmt"
	"net"
	"strings"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	"github.com/gin-gonic/gin"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/status"
)

var (
	errAuthTokenIsInvalid = errors.New("authorization token is invalid")
	errMissingMetadata    = errors.New("missing metadata")
)

const (
	authorizationHeader = "authorization"
	bearerSchema        = "Bearer"
	defaultRemoteAddr   = "127.0.0.1"
)

type Auth struct {
	db          *goqu.Database
	keycloak    *gocloak.GoCloak
	keycloakCfg config.KeycloakConfig
	repository  *users.Repository
}

type UserContext struct {
	UserID int64
	Roles  []string
	IP     net.IP
}

func NewAuth(
	db *goqu.Database,
	keycloak *gocloak.GoCloak,
	keycloakCfg config.KeycloakConfig,
	repository *users.Repository,
) *Auth {
	return &Auth{
		db:          db,
		keycloak:    keycloak,
		keycloakCfg: keycloakCfg,
		repository:  repository,
	}
}

func (s *Auth) ValidateREST(ctx *gin.Context) (UserContext, error) {
	header := ctx.GetHeader(authorizationHeader)

	if len(header) == 0 {
		return UserContext{}, nil
	}

	tokenString := strings.TrimPrefix(header, bearerSchema+" ")

	remoteAddr := ctx.ClientIP()
	if remoteAddr == "" {
		remoteAddr = defaultRemoteAddr
	}

	ip := net.ParseIP(remoteAddr)

	return s.ValidateToken(ctx, tokenString, ip)
}

func (s *Auth) ValidateGRPC(ctx context.Context) (UserContext, error) {
	md, ok := metadata.FromIncomingContext(ctx)
	if !ok {
		return UserContext{}, errMissingMetadata
	}

	lines := md[authorizationHeader]

	if len(lines) < 1 {
		return UserContext{}, nil
	}

	tokenString := strings.TrimPrefix(lines[0], bearerSchema+" ")

	var remoteAddr string

	p, ok := realip.FromContext(ctx)
	if ok {
		remoteAddr = p.String()
	}

	if remoteAddr == "" {
		remoteAddr = defaultRemoteAddr
	}

	ip := net.ParseIP(remoteAddr)

	return s.ValidateToken(ctx, tokenString, ip)
}

func (s *Auth) ValidateToken(
	ctx context.Context,
	tokenString string,
	ip net.IP,
) (UserContext, error) {
	res := UserContext{}

	if len(tokenString) == 0 {
		return res, errAuthTokenIsInvalid
	}

	var claims users.Claims

	_, err := s.keycloak.DecodeAccessTokenCustomClaims(
		ctx,
		tokenString,
		s.keycloakCfg.Realm,
		&claims,
	)
	if err != nil {
		return res, fmt.Errorf("%w: %w", errAuthTokenIsInvalid, err)
	}

	id, err := s.repository.EnsureUserImported(ctx, claims, ip)
	if err != nil {
		return res, err
	}

	err = s.repository.RegisterVisit(ctx, id, ip)
	if err != nil {
		return res, err
	}

	return UserContext{
		UserID: id,
		Roles:  claims.ResourceAccess.Autowp.Roles,
		IP:     ip,
	}, nil
}

// GRPCError maps an error from ValidateGRPC to a gRPC status. A missing or unverifiable token is
// the caller's problem (codes.Unauthenticated); anything else that fails while resolving the user
// - a database round-trip in EnsureUserImported or RegisterVisit - is ours (codes.Internal).
func (s *Auth) GRPCError(err error) error {
	if errors.Is(err, errMissingMetadata) || errors.Is(err, errAuthTokenIsInvalid) {
		return status.Error(codes.Unauthenticated, "invalid or missing authentication token")
	}

	return status.Error(codes.Internal, err.Error())
}
