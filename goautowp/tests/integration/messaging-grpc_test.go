package integration

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestMessaging(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	messagingClient := pb.NewMessagingClient(conn)

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
	_, err = messagingClient.CreateMessage(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.CreateMessageRequest{Message: &pb.Message{
			ToUserId: tester.GetId(),
			Text:     "Test message",
		}},
	)
	require.NoError(t, err)

	// get message
	res, err := messagingClient.GetMessages(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.MessagingGetMessagesRequest{
			UserId: tester.GetId(),
			Folder: "sent",
			Page:   1,
		},
	)
	require.NoError(t, err)
	require.Equal(t, "Test message", res.GetItems()[0].GetText())

	_, err = messagingClient.GetMessagesNewCount(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = messagingClient.GetMessagesSummary(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = messagingClient.DeleteMessage(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.MessagingDeleteMessage{MessageId: res.GetItems()[0].GetId()},
	)
	require.NoError(t, err)

	_, err = messagingClient.ClearFolder(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.MessagingClearFolder{Folder: "sent"},
	)
	require.NoError(t, err)

	_, err = messagingClient.ClearFolder(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.MessagingClearFolder{Folder: "system"},
	)
	require.NoError(t, err)
}
