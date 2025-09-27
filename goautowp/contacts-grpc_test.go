package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestCreateDeleteContact(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	client := NewContactsClient(conn)
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	usersClient := NewUsersClient(conn)

	// admin
	adminToken, err := kc.Login(
		ctx,
		"frontend",
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
		"frontend",
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
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&APIMeRequest{},
	)
	require.NoError(t, err)

	// create
	_, err = client.CreateContact(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&CreateContactRequest{Contact: &Contact{ContactUserId: tester.GetId()}},
	)
	require.NoError(t, err)

	// get contact
	_, err = client.GetContact(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&GetContactRequest{UserId: tester.GetId()},
	)
	require.NoError(t, err)

	// get contacts
	items, err := client.GetContacts(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
	require.NotEmpty(t, items)

	var contactUser *Contact

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
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&DeleteContactRequest{UserId: tester.GetId()},
	)
	require.NoError(t, err)
}
