package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestMessaging(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	messagingClient := NewMessagingClient(conn)

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
	_, err = messagingClient.CreateMessage(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&CreateMessageRequest{Message: &Message{
			ToUserId: tester.GetId(),
			Text:     "Test message",
		}},
	)
	require.NoError(t, err)

	// get message
	res, err := messagingClient.GetMessages(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&MessagingGetMessagesRequest{
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
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = messagingClient.GetMessagesSummary(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&emptypb.Empty{},
	)
	require.NoError(t, err)

	_, err = messagingClient.DeleteMessage(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&MessagingDeleteMessage{MessageId: res.GetItems()[0].GetId()},
	)
	require.NoError(t, err)

	_, err = messagingClient.ClearFolder(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&MessagingClearFolder{Folder: "sent"},
	)
	require.NoError(t, err)

	_, err = messagingClient.ClearFolder(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&MessagingClearFolder{Folder: "system"},
	)
	require.NoError(t, err)
}
