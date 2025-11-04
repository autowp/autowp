package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
)

func TestGetThemes(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	themes, err := client.ListThemes(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&ListThemesRequest{},
	)
	require.NoError(t, err)
	require.NotEmpty(t, themes.GetItems())
}

func TestGetTheme(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	theme, err := client.GetTheme(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&GetThemeRequest{Id: 2},
	)
	require.NoError(t, err)
	require.NotEmpty(t, theme)
	require.NotEmpty(t, theme.GetId())
	require.NotEmpty(t, theme.GetName())
}

//nolint:paralleltest
func TestGetLastTopicAndLastMessage(t *testing.T) {
	ctx := t.Context()

	client := NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	topicID, err := client.CreateTopic(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&CreateTopicRequest{
			Message:            "Test message",
			ModeratorAttention: false,
			Topic: &Topic{
				ThemeId:      2,
				Name:         "Topic name",
				Subscription: true,
			},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topicID)

	topic, err := client.GetLastTopic(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&GetThemeRequest{Id: 2},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topic)

	message, err := client.GetLastMessage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&GetTopicRequest{Id: topic.GetId()},
	)
	require.NoError(t, err)
	require.NotEmpty(t, message)

	topics, err := client.ListTopics(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&ListTopicsRequest{ThemeId: 2, Page: 1},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topics.GetItems())
}

func TestGetUserSummary(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	_, err = client.GetUserSummary(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestCloseTopic(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	kc := cnt.Keycloak()

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

	topic, err := client.CreateTopic(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&CreateTopicRequest{
			Message:            "Test message",
			ModeratorAttention: false,
			Topic: &Topic{
				ThemeId:      2,
				Name:         "Topic name",
				Subscription: true,
			},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topic)

	_, err = client.CloseTopic(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&SetTopicStatusRequest{
			Id: topic.GetId(),
		},
	)
	require.NoError(t, err)

	_, err = client.OpenTopic(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&SetTopicStatusRequest{
			Id: topic.GetId(),
		},
	)
	require.NoError(t, err)

	_, err = client.DeleteTopic(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&SetTopicStatusRequest{
			Id: topic.GetId(),
		},
	)
	require.NoError(t, err)
}
