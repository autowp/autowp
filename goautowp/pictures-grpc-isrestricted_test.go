package goautowp

import (
	"testing"

	"github.com/stretchr/testify/require"
)

func TestIsRestrictedInboxStatus(t *testing.T) {
	t.Parallel()

	server := &PicturesGRPCServer{}

	inboxRequest := &PicturesRequest{
		Options: &PictureListOptions{
			Status:  PictureStatus_PICTURE_STATUS_INBOX,
			OwnerId: 5,
		},
	}

	// Anonymous users are always rejected, even when scoped to an ownerId.
	err := server.isRestricted(inboxRequest, false, 0)
	require.Error(t, err)

	// Any registered user (not just the owner) may request the inbox status.
	err = server.isRestricted(inboxRequest, false, 1)
	require.NoError(t, err)

	// Moderators are always allowed, regardless of userID.
	err = server.isRestricted(inboxRequest, true, 0)
	require.NoError(t, err)
}

func TestIsRestrictedRequiresScopingFilter(t *testing.T) {
	t.Parallel()

	server := &PicturesGRPCServer{}

	// A non-moderator without any scoping filter (item, owner, etc.) is restricted.
	err := server.isRestricted(&PicturesRequest{Options: &PictureListOptions{}}, false, 1)
	require.Error(t, err)

	// Providing an OwnerId satisfies the scoping requirement.
	err = server.isRestricted(&PicturesRequest{Options: &PictureListOptions{OwnerId: 1}}, false, 1)
	require.NoError(t, err)

	// Moderators are exempt from the scoping requirement.
	err = server.isRestricted(&PicturesRequest{Options: &PictureListOptions{}}, true, 1)
	require.NoError(t, err)
}

func TestIsRestrictedRestrictedOptions(t *testing.T) {
	t.Parallel()

	server := &PicturesGRPCServer{}

	testCases := []struct {
		name    string
		options *PictureListOptions
	}{
		{name: "HasNoComments", options: &PictureListOptions{OwnerId: 1, HasNoComments: true}},
		{name: "CommentTopic", options: &PictureListOptions{OwnerId: 1, CommentTopic: &CommentTopicListOptions{}}},
		{name: "HasSpecialName", options: &PictureListOptions{OwnerId: 1, HasSpecialName: true}},
		{name: "HasNoPictureModerVote", options: &PictureListOptions{OwnerId: 1, HasNoPictureModerVote: true}},
		{name: "HasNoReplacePicture", options: &PictureListOptions{OwnerId: 1, HasNoReplacePicture: true}},
		{name: "HasNoPictureItem", options: &PictureListOptions{OwnerId: 1, HasNoPictureItem: true}},
		{name: "HasNoPoint", options: &PictureListOptions{OwnerId: 1, HasNoPoint: true}},
	}

	for _, testCase := range testCases {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			err := server.isRestricted(&PicturesRequest{Options: testCase.options}, false, 1)
			require.Error(t, err)

			err = server.isRestricted(&PicturesRequest{Options: testCase.options}, true, 1)
			require.NoError(t, err)
		})
	}
}

func TestIsRestrictedRestrictedFields(t *testing.T) {
	t.Parallel()

	server := &PicturesGRPCServer{}

	testCases := []struct {
		name   string
		fields *PictureFields
	}{
		{name: "AcceptedCount", fields: &PictureFields{AcceptedCount: true}},
		{name: "Exif", fields: &PictureFields{Exif: true}},
		{name: "IsLast", fields: &PictureFields{IsLast: true}},
		{name: "SpecialName", fields: &PictureFields{SpecialName: true}},
		{name: "Siblings", fields: &PictureFields{Siblings: &PicturesRequest{}}},
	}

	for _, testCase := range testCases {
		t.Run(testCase.name, func(t *testing.T) {
			t.Parallel()

			req := &PicturesRequest{
				Options: &PictureListOptions{OwnerId: 1},
				Fields:  testCase.fields,
			}

			err := server.isRestricted(req, false, 1)
			require.Error(t, err)

			err = server.isRestricted(req, true, 1)
			require.NoError(t, err)
		})
	}
}
