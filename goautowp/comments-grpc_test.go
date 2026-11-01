package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

func TestAddEmptyCommentShouldReturnError(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ITEM_TYPE_ID,
			Message:            "",
			ModeratorAttention: true,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.Error(t, err)
}

func TestAddComment(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	r2, err := client.GetMessage(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.GetMessageRequest{
			Id: commentItem.GetId(),
			Fields: &pb.CommentMessageFields{
				Preview:  true,
				Route:    true,
				Text:     true,
				Vote:     true,
				UserVote: true,
				Replies:  true,
				Status:   true,
			},
		},
	)
	require.NoError(t, err)
	require.Equal(t, "Test", r2.GetText())

	r3, err := client.GetMessages(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.GetMessagesRequest{
			ItemId:    r2.GetItemId(),
			TypeId:    r2.GetTypeId(),
			ParentId:  0,
			NoParents: true,
			UserId:    r2.GetAuthorId(),
			Order:     1,
			Limit:     1,
			Page:      1,
			Fields: &pb.CommentMessageFields{
				Preview:  true,
				Route:    true,
				Text:     true,
				Vote:     true,
				UserVote: true,
				Replies:  true,
				Status:   true,
			},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, r3.GetItems())

	_, err = client.View(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.CommentsViewRequest{
			ItemId: 1,
			TypeId: pb.CommentsType_ARTICLES_TYPE_ID,
		},
	)
	require.NoError(t, err)
}

func TestCommentReplyNotificationShouldBeDelivered(t *testing.T) { //nolint:paralleltest
	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	user1ID, user1Token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, user2Token := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	_, err = pb.NewUsersClient(conn).UpdateUser(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+user1Token),
		&pb.UpdateUserRequest{
			User:       &pb.User{Id: user1ID, Language: schema.EnglishLanguageCode},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"language"}},
		},
	)
	require.NoError(t, err)

	response, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+user1Token),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Root comment",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, response.GetId())

	response, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+user2Token),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Reply comment",
			ModeratorAttention: false,
			ParentId:           response.GetId(),
			Resolve:            false,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, response.GetId())

	messagesClient := pb.NewMessagingClient(conn)
	messages, err := messagesClient.GetMessages(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+user1Token),
		&pb.MessagingGetMessagesRequest{
			Folder: "system",
			Page:   1,
		},
	)
	require.NoError(t, err)
	require.Contains(t, messages.GetItems()[0].GetText(), "replies to you")
}

func TestSubscribeComment(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	_, err = client.Subscribe(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.CommentsSubscribeRequest{
			ItemId: 1,
			TypeId: pb.CommentsType_ARTICLES_TYPE_ID,
		},
	)
	require.NoError(t, err)

	_, err = client.UnSubscribe(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.CommentsUnSubscribeRequest{
			ItemId: 1,
			TypeId: pb.CommentsType_ARTICLES_TYPE_ID,
		},
	)
	require.NoError(t, err)
}

func TestVoteComment(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	// vote comment
	_, err = client.VoteComment(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.CommentsVoteCommentRequest{
			CommentId: commentItem.GetId(),
			Vote:      1,
		},
	)
	require.ErrorContains(t, err, "self-vote forbidden")

	_, err = client.VoteComment(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.CommentsVoteCommentRequest{
			CommentId: commentItem.GetId(),
			Vote:      1,
		},
	)
	require.NoError(t, err)

	// get comment votes
	_, err = client.GetCommentVotes(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.GetCommentVotesRequest{
			CommentId: commentItem.GetId(),
		},
	)
	require.NoError(t, err)

	// vote negative
	_, err = client.VoteComment(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.CommentsVoteCommentRequest{
			CommentId: commentItem.GetId(),
			Vote:      -1,
		},
	)
	require.NoError(t, err)

	// delete comment - author self-delete is covered by TestDeleteOwnComment; here a moderator
	// removes it so the comment survives as a tombstone for the vote/restore checks below.
	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.CommentsSetDeletedRequest{
			CommentId: commentItem.GetId(),
			Deleted:   true,
		},
	)
	require.NoError(t, err)

	// vote deleted comment
	_, err = client.VoteComment(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.CommentsVoteCommentRequest{
			CommentId: commentItem.GetId(),
			Vote:      1,
		},
	)
	require.Error(t, err)

	// restore comment
	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.CommentsSetDeletedRequest{
			CommentId: commentItem.GetId(),
			Deleted:   false,
		},
	)
	require.ErrorContains(t, err, "PermissionDenied")

	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.CommentsSetDeletedRequest{
			CommentId: commentItem.GetId(),
			Deleted:   false,
		},
	)
	require.NoError(t, err)

	// move message
	_, err = client.MoveComment(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.CommentsMoveCommentRequest{
			CommentId: commentItem.GetId(),
			ItemId:    2,
			TypeId:    pb.CommentsType_ARTICLES_TYPE_ID,
		},
	)
	require.ErrorContains(t, err, "PermissionDenied")
}

func TestDeleteOwnComment(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.AddCommentRequest{
			ItemId:  1,
			TypeId:  pb.CommentsType_ARTICLES_TYPE_ID,
			Message: "to be removed by its author",
		},
	)
	require.NoError(t, err)

	// the author removes their own comment - no moderator role required
	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.CommentsSetDeletedRequest{CommentId: commentItem.GetId(), Deleted: true},
	)
	require.NoError(t, err)

	// nothing hangs off it, so it is gone entirely
	_, err = client.GetMessage(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.GetMessageRequest{Id: commentItem.GetId()},
	)
	require.Error(t, err)
}

func TestUpdateOwnComment(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.AddCommentRequest{
			ItemId:  1,
			TypeId:  pb.CommentsType_ARTICLES_TYPE_ID,
			Message: "first revision",
		},
	)
	require.NoError(t, err)

	_, err = client.UpdateComment(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.UpdateCommentRequest{
			Comment:    &pb.CommentMessage{Id: commentItem.GetId(), Text: "second revision"},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"text"}},
		},
	)
	require.NoError(t, err)

	msg, err := client.GetMessage(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.GetMessageRequest{
			Id:     commentItem.GetId(),
			Fields: &pb.CommentMessageFields{Text: true},
		},
	)
	require.NoError(t, err)
	require.Equal(t, "second revision", msg.GetText())
}

func TestCompleteComment(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: true,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: true,
			Resolve:            true,
			ParentId:           commentItem.GetId(),
		},
	)
	require.NoError(t, err)
}

func TestMessagesByUserIdentity(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	_, err = client.GetMessages(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token),
		&pb.GetMessagesRequest{
			ItemId:       1,
			TypeId:       pb.CommentsType_ITEM_TYPE_ID,
			UserIdentity: "test",
			Page:         2,
		},
	)
	require.NoError(t, err)
}

//nolint:paralleltest
func TestMoveComment(t *testing.T) {
	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	forumsClient := pb.NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	topic, err := forumsClient.CreateTopic(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.CreateTopicRequest{
			Topic: &pb.Topic{
				ThemeId:      2,
				Name:         "Topic name",
				Subscription: true,
			},
			Message:            "Test message",
			ModeratorAttention: false,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topic)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.AddCommentRequest{
			ItemId:             topic.GetId(),
			TypeId:             pb.CommentsType_FORUMS_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.AddCommentRequest{
			ItemId:             topic.GetId(),
			TypeId:             pb.CommentsType_FORUMS_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           commentItem.GetId(),
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	topic2, err := forumsClient.CreateTopic(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.CreateTopicRequest{
			Message:            "Test 2 message",
			ModeratorAttention: false,
			Topic: &pb.Topic{
				ThemeId:      2,
				Name:         "Topic 2 name",
				Subscription: true,
			},
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topic)

	_, err = client.MoveComment(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.CommentsMoveCommentRequest{
			CommentId: commentItem.GetId(),
			ItemId:    topic2.GetId(),
			TypeId:    pb.CommentsType_FORUMS_TYPE_ID,
		},
	)
	require.NoError(t, err)
}

func TestAddCommentOfUnexpectedItemType(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             6,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.Error(t, err)
}

func TestAddCommentToDeletedOrNotExistentParent(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	response, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+userToken),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Root comment",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, response.GetId())

	// delete comment
	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.CommentsSetDeletedRequest{
			CommentId: response.GetId(),
			Deleted:   true,
		},
	)
	require.NoError(t, err)

	// reply to deleted comment
	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Reply comment",
			ModeratorAttention: false,
			ParentId:           response.GetId(),
			Resolve:            false,
		},
	)
	require.Error(t, err)

	// reply to non-existent comment
	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken),
		&pb.AddCommentRequest{
			ItemId:             1,
			TypeId:             pb.CommentsType_ARTICLES_TYPE_ID,
			Message:            "Reply comment",
			ModeratorAttention: false,
			ParentId:           9999999999,
			Resolve:            false,
		},
	)
	require.Error(t, err)
}
