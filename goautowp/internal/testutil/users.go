package testutil

import (
	"testing"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc"
	"google.golang.org/grpc/metadata"
)

// UserWithCleanHistory logs in a fixture account and resets its posting and voting limits.
func UserWithCleanHistory(
	t *testing.T,
	conn *grpc.ClientConn,
	cfg config.KeycloakConfig,
	db *goqu.Database,
	username string,
	password string,
) (int64, string) {
	t.Helper()

	ctx := t.Context()
	kc := gocloak.NewClient(cfg.URL)

	token, err := kc.Login(ctx, "frontend", "", cfg.Realm, username, password)
	require.NoError(t, err)
	require.NotNil(t, token)

	user, err := pb.NewUsersClient(conn).Me(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, "Bearer "+token.AccessToken),
		&pb.MeRequest{},
	)
	require.NoError(t, err)

	const exampleVotesLeftColName = 100

	_, err = db.Update(schema.UserTable).
		Set(goqu.Record{
			schema.UserTableLastMessageTimeColName: "2000-01-01",
			schema.UserTableVotesLeftColName:       exampleVotesLeftColName,
		}).
		Where(schema.UserTableIDCol.Eq(user.GetId())).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	return user.GetId(), token.AccessToken
}
