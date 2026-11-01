package auth

import (
	"context"
	"errors"
	"fmt"
	"net"
	"net/http"
	"strings"

	"github.com/Nerzal/gocloak/v14"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/users"
	"github.com/gin-gonic/gin"
	"github.com/grpc-ecosystem/go-grpc-middleware/v2/interceptors/realip"
	"google.golang.org/grpc/metadata"
)

var (
	ErrInvalidToken    = errors.New("authorization token is invalid")
	ErrMissingMetadata = errors.New("missing metadata")
)

const (
	AuthorizationHeader = "authorization"
	DefaultRemoteAddr   = "127.0.0.1"
	bearerSchema        = "Bearer"
)

type Service struct {
	keycloak    *gocloak.GoCloak
	keycloakCfg config.KeycloakConfig
	repository  *users.Repository
}

func New(
	keycloak *gocloak.GoCloak,
	keycloakCfg config.KeycloakConfig,
	repository *users.Repository,
) *Service {
	return &Service{
		keycloak:    keycloak,
		keycloakCfg: keycloakCfg,
		repository:  repository,
	}
}

func (s *Service) ValidateREST(ctx *gin.Context) (User, error) {
	if user, ok := FromContext(ctx.Request.Context()); ok {
		return user, nil
	}

	header := ctx.GetHeader(AuthorizationHeader)

	if len(header) == 0 {
		return User{}, nil
	}

	tokenString := strings.TrimPrefix(header, bearerSchema+" ")

	remoteAddr := ctx.ClientIP()
	if remoteAddr == "" {
		remoteAddr = DefaultRemoteAddr
	}

	ip := net.ParseIP(remoteAddr)

	return s.ValidateToken(ctx, tokenString, ip)
}

func (s *Service) ValidateGRPC(ctx context.Context) (User, error) {
	if user, ok := FromContext(ctx); ok {
		return user, nil
	}

	md, ok := metadata.FromIncomingContext(ctx)
	if !ok {
		return User{}, ErrMissingMetadata
	}

	lines := md[AuthorizationHeader]

	if len(lines) < 1 {
		return User{}, nil
	}

	tokenString := strings.TrimPrefix(lines[0], bearerSchema+" ")

	var remoteAddr string

	p, ok := realip.FromContext(ctx)
	if ok {
		remoteAddr = p.String()
	}

	if remoteAddr == "" {
		remoteAddr = DefaultRemoteAddr
	}

	ip := net.ParseIP(remoteAddr)

	return s.ValidateToken(ctx, tokenString, ip)
}

func (s *Service) ValidateToken(
	ctx context.Context,
	tokenString string,
	ip net.IP,
) (User, error) {
	res := User{}

	if len(tokenString) == 0 {
		return res, ErrInvalidToken
	}

	var claims users.Claims

	_, err := s.keycloak.DecodeAccessTokenCustomClaims(
		ctx,
		tokenString,
		s.keycloakCfg.Realm,
		&claims,
	)
	if err != nil {
		return res, fmt.Errorf("%w: %w", ErrInvalidToken, err)
	}

	id, err := s.repository.EnsureUserImported(ctx, claims, ip)
	if err != nil {
		return res, err
	}

	err = s.repository.RegisterVisit(ctx, id, ip)
	if err != nil {
		return res, err
	}

	return User{
		UserID: id,
		Roles:  claims.ResourceAccess.Autowp.Roles,
		IP:     ip,
	}, nil
}

// RESTError writes the HTTP response for an error from ValidateREST: a missing or
// unverifiable token is 401, anything else is 500.
func (s *Service) RESTError(ctx *gin.Context, err error) {
	if errors.Is(err, ErrMissingMetadata) || errors.Is(err, ErrInvalidToken) {
		ctx.String(http.StatusUnauthorized, "invalid or missing authentication token")

		return
	}

	ctx.String(http.StatusInternalServerError, err.Error())
}
