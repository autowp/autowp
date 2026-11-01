package pictureextractor_test

import (
	"context"
	"errors"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/textstorage"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/autowp/goautowp/internal/users"
	"github.com/autowp/goautowp/internal/util"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

// Populate the three fields with their former per-picture repository calls.
func legacyPictureExtraFields(t *testing.T, extractor *pictureFieldFixture, rows []*schema.PictureRow,
	fields *pb.PictureFields, userCtx authn.User,
) []*pb.Picture {
	t.Helper()
	ctx := t.Context()
	result, err := extractor.ExtractRows(ctx, rows, nil, "en", userCtx)
	require.NoError(t, err)
	pictures, err := extractor.dependencies.PicturesRepository(ctx)
	require.NoError(t, err)
	comments, err := extractor.dependencies.CommentsRepository(ctx)
	require.NoError(t, err)
	texts, err := extractor.dependencies.TextStorageRepository(ctx)
	require.NoError(t, err)

	for index, row := range rows {
		if fields.GetSubscribed() && userCtx.UserID > 0 {
			result[index].Subscribed, err = comments.IsSubscribed(
				ctx,
				userCtx.UserID,
				schema.CommentMessageTypeIDPictures,
				row.ID,
			)
			require.NoError(t, err)
		}

		if fields.GetCopyrights() && row.CopyrightsTextID.Valid {
			value, err := texts.Text(ctx, row.CopyrightsTextID.Int32)
			if !errors.Is(err, textstorage.ErrTextNotFound) {
				require.NoError(t, err)
			}

			result[index].Copyrights = value
		}

		if fields.GetRights() {
			accept, err := pictures.CanAccept(ctx, row)
			require.NoError(t, err)
			remove, err := pictures.CanDelete(ctx, row)
			require.NoError(t, err)

			moder := util.Contains(userCtx.Roles, users.RolePicturesModer)
			result[index].Rights = &pb.PictureRights{
				Move:     moder,
				Unaccept: row.Status == schema.PictureStatusAccepted && moder,
				Accept:   accept && moder,
				Restore:  row.Status == schema.PictureStatusRemoving && util.Contains(userCtx.Roles, users.RoleAdmin),
				Normalize: row.Status == schema.PictureStatusInbox &&
					moder,
				Flop:   row.Status == schema.PictureStatusInbox && moder,
				Crop:   moder,
				Delete: remove,
			}
		}
	}

	return result
}

func TestPictureExtraFieldsBatchesMatchIndividualReads(t *testing.T) {
	t.Parallel()
	extractor, db, _, rows := newPictureFieldBatchExtractor(t)

	for _, user := range []authn.User{
		{},
		{UserID: 7},
		{UserID: 7, Roles: []string{users.RolePicturesModer}},
		{UserID: 7, Roles: []string{users.RoleModer, users.RolePicturesModer, users.RoleAdmin}},
	} {
		for _, fields := range []*pb.PictureFields{
			{}, {Subscribed: true}, {Rights: true}, {Copyrights: true}, {Subscribed: true, Rights: true, Copyrights: true},
		} {
			original := proto.Clone(fields)
			expected := legacyPictureExtraFields(t, extractor, rows, fields, user)
			logger := &testutil.QueryLogger{}
			db.Logger(logger)

			actual, err := extractor.ExtractRows(t.Context(), rows, fields, "en", user)
			require.NoError(t, err)
			db.Logger(nil)

			want := 0
			if fields.GetSubscribed() && user.UserID > 0 {
				want++
			}

			if fields.GetRights() {
				want++
			}

			if fields.GetCopyrights() {
				want++
			}

			require.EqualValues(
				t,
				want,
				logger.Queries.Load(),
				"one query per requested batch, regardless of row count",
			)
			require.True(t, proto.Equal(&pb.PicturesList{Items: expected}, &pb.PicturesList{Items: actual}))
			require.True(t, proto.Equal(original, fields))
		}
	}

	fields := &pb.PictureFields{Subscribed: true, Rights: true, Copyrights: true}
	logger := &testutil.QueryLogger{}
	db.Logger(logger)

	empty, err := extractor.ExtractRows(t.Context(), nil, fields, "en", authn.User{UserID: 7})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
	require.Zero(t, logger.Queries.Load())
	// No inbox rows or valid text IDs means neither rights nor copyrights need SQL.
	_, err = extractor.ExtractRows(
		t.Context(),
		rows[6:10],
		&pb.PictureFields{Rights: true, Copyrights: true},
		"en",
		authn.User{},
	)
	require.NoError(t, err)
	require.Zero(t, logger.Queries.Load())
}

func TestPictureExtraFieldsBatchesParallel(t *testing.T) {
	t.Parallel()
	extractor, db, _, rows := newPictureFieldBatchExtractor(t)
	fields := &pb.PictureFields{Subscribed: true, Rights: true, Copyrights: true}
	user := authn.User{UserID: 7}
	expected := legacyPictureExtraFields(t, extractor, rows, fields, user)
	logger := &testutil.QueryLogger{}
	unblock := testutil.BlockQueries(t, logger, 3)
	db.Logger(logger)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	done := make(chan error, 1)

	var actual []*pb.Picture

	go func() {
		var err error

		actual, err = extractor.ExtractRows(ctx, rows, fields, "en", user)
		done <- err
	}()

	unblock()
	require.NoError(t, <-done)
	require.EqualValues(t, 3, logger.Queries.Load())
	require.True(t, proto.Equal(&pb.PicturesList{Items: expected}, &pb.PicturesList{Items: actual}))
	db.Logger(nil)
	// Nested callers can restrict the existing picture budget to one worker.
	serial := extraction.WithPictureParallelism(ctx, 1)
	actual, err := extractor.ExtractRows(serial, rows, fields, "en", user)
	require.NoError(t, err)
	require.True(t, proto.Equal(&pb.PicturesList{Items: expected}, &pb.PicturesList{Items: actual}))
}

func TestPictureExtraFieldsBatchErrors(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		name      string
		fields    *pb.PictureFields
		statement string
	}{
		{
			name:      "subscriptions",
			fields:    &pb.PictureFields{Subscribed: true},
			statement: "ALTER TABLE pg_temp.comment_topic_subscribe RENAME COLUMN user_id TO broken",
		},
		{"rights", &pb.PictureFields{Rights: true}, "ALTER TABLE pg_temp.picture_moder_vote RENAME COLUMN vote TO broken"},
		{"copyrights", &pb.PictureFields{Copyrights: true}, "UPDATE pg_temp.textstorage_text SET text=NULL WHERE id=1"},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()
			extractor, _, pool, rows := newPictureFieldBatchExtractor(t)
			_, err := pool.ExecContext(t.Context(), test.statement)
			require.NoError(t, err)
			result, err := extractor.ExtractRows(t.Context(), rows, test.fields, "en", authn.User{UserID: 7})
			require.Error(t, err)
			require.Nil(t, result)

			if test.name == "copyrights" {
				texts, err := extractor.dependencies.TextStorageRepository(t.Context())
				require.NoError(t, err)
				_, err = texts.Text(t.Context(), 1)
				require.Error(t, err, "a stored NULL must retain the original Text error")
			}
		})
	}
}

func TestPictureExtraFieldsBatchCancellation(t *testing.T) {
	t.Parallel()
	extractor, db, _, rows := newPictureFieldBatchExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	started := make(chan struct{}, 3)

	db.Logger(&testutil.QueryLogger{BeforeQuery: func() { started <- struct{}{}; <-ctx.Done() }})

	done := make(chan error, 1)

	go func() {
		_, err := extractor.ExtractRows(
			ctx,
			rows,
			&pb.PictureFields{Subscribed: true, Rights: true, Copyrights: true},
			"en",
			authn.User{UserID: 7},
		)
		done <- err
	}()

	for range 3 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("the three batches did not start together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
}
