package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func TestAddEmptyCommentShouldReturnError(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ITEM_TYPE_ID,
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

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	r2, err := client.GetMessage(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&GetMessageRequest{
			Id: commentItem.GetId(),
			Fields: &CommentMessageFields{
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
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&GetMessagesRequest{
			ItemId:    r2.GetItemId(),
			TypeId:    r2.GetTypeId(),
			ParentId:  0,
			NoParents: true,
			UserId:    r2.GetAuthorId(),
			Order:     1,
			Limit:     1,
			Page:      1,
			Fields: &CommentMessageFields{
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
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&CommentsViewRequest{
			ItemId: 1,
			TypeId: CommentsType_ARTICLES_TYPE_ID,
		},
	)
	require.NoError(t, err)
}

func TestCommentReplyNotificationShouldBeDelivered(t *testing.T) { //nolint:paralleltest
	ctx := t.Context()

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, user1Token := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, user2Token := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	response, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+user1Token),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
			Message:            "Root comment",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, response.GetId())

	response, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+user2Token),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
			Message:            "Reply comment",
			ModeratorAttention: false,
			ParentId:           response.GetId(),
			Resolve:            false,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, response.GetId())

	messagesClient := NewMessagingClient(conn)
	messages, err := messagesClient.GetMessages(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+user1Token),
		&MessagingGetMessagesRequest{
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

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	_, err = client.Subscribe(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&CommentsSubscribeRequest{
			ItemId: 1,
			TypeId: CommentsType_ARTICLES_TYPE_ID,
		},
	)
	require.NoError(t, err)

	_, err = client.UnSubscribe(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&CommentsUnSubscribeRequest{
			ItemId: 1,
			TypeId: CommentsType_ARTICLES_TYPE_ID,
		},
	)
	require.NoError(t, err)
}

func TestVoteComment(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	// vote comment
	_, err = client.VoteComment(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&CommentsVoteCommentRequest{
			CommentId: commentItem.GetId(),
			Vote:      1,
		},
	)
	require.ErrorContains(t, err, "self-vote forbidden")

	_, err = client.VoteComment(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CommentsVoteCommentRequest{
			CommentId: commentItem.GetId(),
			Vote:      1,
		},
	)
	require.NoError(t, err)

	// get comment votes
	_, err = client.GetCommentVotes(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&GetCommentVotesRequest{
			CommentId: commentItem.GetId(),
		},
	)
	require.NoError(t, err)

	// vote negative
	_, err = client.VoteComment(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CommentsVoteCommentRequest{
			CommentId: commentItem.GetId(),
			Vote:      -1,
		},
	)
	require.NoError(t, err)

	// delete comment
	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&CommentsSetDeletedRequest{
			CommentId: commentItem.GetId(),
			Deleted:   true,
		},
	)
	require.ErrorContains(t, err, "PermissionDenied")

	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CommentsSetDeletedRequest{
			CommentId: commentItem.GetId(),
			Deleted:   true,
		},
	)
	require.NoError(t, err)

	// vote deleted comment
	_, err = client.VoteComment(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CommentsVoteCommentRequest{
			CommentId: commentItem.GetId(),
			Vote:      1,
		},
	)
	require.Error(t, err)

	// restore comment
	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&CommentsSetDeletedRequest{
			CommentId: commentItem.GetId(),
			Deleted:   false,
		},
	)
	require.ErrorContains(t, err, "PermissionDenied")

	_, err = client.SetDeleted(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CommentsSetDeletedRequest{
			CommentId: commentItem.GetId(),
			Deleted:   false,
		},
	)
	require.NoError(t, err)

	// move message
	_, err = client.MoveComment(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CommentsMoveCommentRequest{
			CommentId: commentItem.GetId(),
			ItemId:    2,
			TypeId:    CommentsType_ARTICLES_TYPE_ID,
		},
	)
	require.ErrorContains(t, err, "PermissionDenied")
}

func TestCompleteComment(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: true,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
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

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, token := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	_, err = client.GetMessages(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token),
		&GetMessagesRequest{
			ItemId:       1,
			TypeId:       CommentsType_ITEM_TYPE_ID,
			UserIdentity: "test",
			Page:         2,
		},
	)
	require.NoError(t, err)
}

//nolint:paralleltest
func TestMoveComment(t *testing.T) {
	ctx := t.Context()

	client := NewCommentsClient(conn)
	forumsClient := NewForumsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	topic, err := forumsClient.CreateTopic(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&APICreateTopicRequest{
			ThemeId:            2,
			Name:               "Topic name",
			Message:            "Test message",
			ModeratorAttention: false,
			Subscription:       true,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topic)

	commentItem, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&AddCommentRequest{
			ItemId:             topic.GetId(),
			TypeId:             CommentsType_FORUMS_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           0,
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&AddCommentRequest{
			ItemId:             topic.GetId(),
			TypeId:             CommentsType_FORUMS_TYPE_ID,
			Message:            "Test",
			ModeratorAttention: false,
			ParentId:           commentItem.GetId(),
			Resolve:            false,
		},
	)
	require.NoError(t, err)

	topic2, err := forumsClient.CreateTopic(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&APICreateTopicRequest{
			ThemeId:            2,
			Name:               "Topic 2 name",
			Message:            "Test 2 message",
			ModeratorAttention: false,
			Subscription:       true,
		},
	)
	require.NoError(t, err)
	require.NotEmpty(t, topic)

	_, err = client.MoveComment(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CommentsMoveCommentRequest{
			CommentId: commentItem.GetId(),
			ItemId:    topic2.GetId(),
			TypeId:    CommentsType_FORUMS_TYPE_ID,
		},
	)
	require.NoError(t, err)
}

func TestAddCommentOfUnexpectedItemType(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)

	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&AddCommentRequest{
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

	client := NewCommentsClient(conn)
	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	_, userToken := getUserWithCleanHistory(t, conn, cfg, goquDB, testUsername, testPassword)
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	response, err := client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+userToken),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
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
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&CommentsSetDeletedRequest{
			CommentId: response.GetId(),
			Deleted:   true,
		},
	)
	require.NoError(t, err)

	// reply to deleted comment
	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
			Message:            "Reply comment",
			ModeratorAttention: false,
			ParentId:           response.GetId(),
			Resolve:            false,
		},
	)
	require.Error(t, err)

	// reply to non-existent comment
	_, err = client.Add(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+adminToken),
		&AddCommentRequest{
			ItemId:             1,
			TypeId:             CommentsType_ARTICLES_TYPE_ID,
			Message:            "Reply comment",
			ModeratorAttention: false,
			ParentId:           9999999999,
			Resolve:            false,
		},
	)
	require.Error(t, err)
}
