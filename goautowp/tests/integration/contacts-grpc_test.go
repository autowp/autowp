package integration

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestCreateDeleteContact(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := pb.NewContactsClient(conn)
	cfg := cnt.Config()
	kc := cnt.Keycloak()
	usersClient := pb.NewUsersClient(conn)

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	// tester (me)
	tester, err := usersClient.Me(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&pb.MeRequest{},
	)
	require.NoError(t, err)

	// create
	_, err = client.CreateContact(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.CreateContactRequest{Contact: &pb.Contact{ContactUserId: tester.GetId()}},
	)
	require.NoError(t, err)

	// get contact
	_, err = client.GetContact(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.GetContactRequest{UserId: tester.GetId()},
	)
	require.NoError(t, err)

	// get contacts
	items, err := client.GetContacts(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
	require.NotEmpty(t, items)

	var contactUser *pb.Contact

	for _, i := range items.GetItems() {
		if i.GetContactUserId() == tester.GetId() {
			contactUser = i

			break
		}
	}

	require.NotNil(t, contactUser)
	require.NotEmpty(t, contactUser.GetUser().GetGravatar())

	// delete
	_, err = client.DeleteContact(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.DeleteContactRequest{UserId: tester.GetId()},
	)
	require.NoError(t, err)
}
