package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

func TestGetThemes(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	themes, err := client.ListThemes(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.ListThemesRequest{},
	)
	require.NoError(t, err)
	require.NotEmpty(t, themes.GetItems())
}

func TestGetTheme(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	theme, err := client.GetTheme(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.GetThemeRequest{Id: 2},
	)
	require.NoError(t, err)
	require.NotEmpty(t, theme)
	require.NotEmpty(t, theme.GetId())
	require.NotEmpty(t, theme.GetName())
}

//nolint:paralleltest
func TestGetLastTopicAndLastMessage(t *testing.T) {
	ctx := t.Context()

	client := pb.NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	userID, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	topicID, err := client.CreateTopic(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.CreateTopicRequest{
			Message:            "Test message",
			ModeratorAttention: false,
			Topic: &pb.Topic{
				ThemeId:      2,
				Name:         "Topic name",
				Subscription: true,
			},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topicID)

	topic, err := client.GetLastTopic(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.GetThemeRequest{Id: 2},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topic)

	message, err := client.GetLastMessage(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.GetTopicRequest{Id: topic.GetId()},
	)
	require.NoError(t, err)
	require.NotEmpty(t, message)
	require.Equal(t, topic.GetId(), message.GetItemId())
	require.Equal(t, pb.CommentsType_FORUMS_TYPE_ID, message.GetTypeId())
	require.Equal(t, userID, message.GetAuthorId())
	require.NotNil(t, message.GetCreateTime())

	topics, err := client.ListTopics(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.ListTopicsRequest{ThemeId: 2, Page: 1},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topics.GetItems())
}

func TestGetUserSummary(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	_, err = client.GetUserSummary(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&emptypb.Empty{},
	)
	require.NoError(t, err)
}

func TestUpdateTopicStatus(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewForumsClient(conn)
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
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.CreateTopicRequest{
			Message:            "Test message",
			ModeratorAttention: false,
			Topic: &pb.Topic{
				ThemeId:      2,
				Name:         "Topic name",
				Subscription: true,
			},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topic)

	_, err = client.UpdateTopic(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.UpdateTopicRequest{
			Topic:      &pb.Topic{Id: topic.GetId(), Status: "closed"},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdateTopic(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.UpdateTopicRequest{
			Topic:      &pb.Topic{Id: topic.GetId(), Status: "normal"},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	_, err = client.UpdateTopic(
		metadata.AppendToOutgoingContext(
			ctx,
			authn.AuthorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&pb.UpdateTopicRequest{
			Topic:      &pb.Topic{Id: topic.GetId(), Status: "deleted"},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)
}
