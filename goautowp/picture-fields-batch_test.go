package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/textstorage"
	"github.com/autowp/goautowp/users"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newPictureFieldBatchExtractor(t *testing.T) (*PictureExtractor, *goqu.Database, *sql.DB, []*schema.PictureRow) {
	t.Helper()
	db, pool, _ := newRelatedGroupTestDB(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE picture_moder_vote (picture_id bigint,user_id bigint,vote integer);
 INSERT INTO picture_moder_vote VALUES
 (2,7,0),(3,7,1),(4,7,0),(4,8,2),(5,7,-1),(6,7,NULL),(7,7,0),(7,8,1),(99,7,0);
 CREATE TEMP TABLE comment_topic_subscribe (type_id integer,item_id bigint,user_id bigint,sent boolean);
 INSERT INTO comment_topic_subscribe VALUES (1,1,7,true),(1,2,7,false),(2,3,7,true),(1,4,8,true);
 CREATE TEMP TABLE textstorage_text (id integer,text text);
 INSERT INTO textstorage_text VALUES (0,'Zero ID'),(1,'Shared'),(2,''),(3,'<b>Copyright</b>'),(4,NULL);
 `)
	require.NoError(t, err)

	extractor := NewPictureExtractor(&Container{config: config.LoadConfig("."), goquDB: db})

	rows := make([]*schema.PictureRow, 0, 10)
	for index, status := range []schema.PictureStatus{
		schema.PictureStatusInbox, schema.PictureStatusInbox, schema.PictureStatusInbox,
		schema.PictureStatusInbox, schema.PictureStatusInbox, schema.PictureStatusInbox,
		schema.PictureStatusAccepted, schema.PictureStatusRemoving, schema.PictureStatusRemoved, schema.PictureStatusUnknown,
	} {
		rows = append(rows, &schema.PictureRow{ID: int64(index + 1), Status: status})
	}

	for index, id := range []int32{1, 1, 2, 3, 999, 0} {
		rows[index].CopyrightsTextID = sql.NullInt32{Int32: id, Valid: true}
	}

	rows = append(rows, rows[1])

	db.Logger(nil)

	return extractor, db, pool, rows
}

// Populate the three fields with their former per-picture repository calls.
func legacyPictureExtraFields(t *testing.T, extractor *PictureExtractor, rows []*schema.PictureRow,
	fields *PictureFields, userCtx UserContext,
) []*Picture {
	t.Helper()
	ctx := t.Context()
	result, err := extractor.ExtractRows(ctx, rows, nil, "en", userCtx)
	require.NoError(t, err)
	pictures, err := extractor.container.PicturesRepository(ctx)
	require.NoError(t, err)
	comments, err := extractor.container.CommentsRepository(ctx)
	require.NoError(t, err)
	texts, err := extractor.container.TextStorageRepository(ctx)
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
			result[index].Rights = &PictureRights{
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

	for _, user := range []UserContext{
		{},
		{UserID: 7},
		{UserID: 7, Roles: []string{users.RolePicturesModer}},
		{UserID: 7, Roles: []string{users.RoleModer, users.RolePicturesModer, users.RoleAdmin}},
	} {
		for _, fields := range []*PictureFields{
			{}, {Subscribed: true}, {Rights: true}, {Copyrights: true}, {Subscribed: true, Rights: true, Copyrights: true},
		} {
			original := proto.Clone(fields)
			expected := legacyPictureExtraFields(t, extractor, rows, fields, user)
			logger := &itemBatchQueryLogger{}
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
				logger.queries.Load(),
				"one query per requested batch, regardless of row count",
			)
			require.True(t, proto.Equal(&PicturesList{Items: expected}, &PicturesList{Items: actual}))
			require.True(t, proto.Equal(original, fields))
		}
	}

	fields := &PictureFields{Subscribed: true, Rights: true, Copyrights: true}
	logger := &itemBatchQueryLogger{}
	db.Logger(logger)

	empty, err := extractor.ExtractRows(t.Context(), nil, fields, "en", UserContext{UserID: 7})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
	require.Zero(t, logger.queries.Load())
	// No inbox rows or valid text IDs means neither rights nor copyrights need SQL.
	_, err = extractor.ExtractRows(
		t.Context(),
		rows[6:10],
		&PictureFields{Rights: true, Copyrights: true},
		"en",
		UserContext{},
	)
	require.NoError(t, err)
	require.Zero(t, logger.queries.Load())
}

func TestPictureExtraFieldsBatchesParallel(t *testing.T) {
	t.Parallel()
	extractor, db, _, rows := newPictureFieldBatchExtractor(t)
	fields := &PictureFields{Subscribed: true, Rights: true, Copyrights: true}
	user := UserContext{UserID: 7}
	expected := legacyPictureExtraFields(t, extractor, rows, fields, user)
	logger := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, logger, 3)
	db.Logger(logger)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	done := make(chan error, 1)

	var actual []*Picture

	go func() {
		var err error

		actual, err = extractor.ExtractRows(ctx, rows, fields, "en", user)
		done <- err
	}()

	unblock()
	require.NoError(t, <-done)
	require.EqualValues(t, 3, logger.queries.Load())
	require.True(t, proto.Equal(&PicturesList{Items: expected}, &PicturesList{Items: actual}))
	db.Logger(nil)
	// Nested callers can restrict the existing picture budget to one worker.
	serial := context.WithValue(ctx, pictureExtractionParallelismKey{}, 1)
	actual, err := extractor.ExtractRows(serial, rows, fields, "en", user)
	require.NoError(t, err)
	require.True(t, proto.Equal(&PicturesList{Items: expected}, &PicturesList{Items: actual}))
}

func TestPictureExtraFieldsBatchErrors(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		name      string
		fields    *PictureFields
		statement string
	}{
		{
			name:      "subscriptions",
			fields:    &PictureFields{Subscribed: true},
			statement: "ALTER TABLE pg_temp.comment_topic_subscribe RENAME COLUMN user_id TO broken",
		},
		{"rights", &PictureFields{Rights: true}, "ALTER TABLE pg_temp.picture_moder_vote RENAME COLUMN vote TO broken"},
		{"copyrights", &PictureFields{Copyrights: true}, "UPDATE pg_temp.textstorage_text SET text=NULL WHERE id=1"},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()
			extractor, _, pool, rows := newPictureFieldBatchExtractor(t)
			_, err := pool.ExecContext(t.Context(), test.statement)
			require.NoError(t, err)
			result, err := extractor.ExtractRows(t.Context(), rows, test.fields, "en", UserContext{UserID: 7})
			require.Error(t, err)
			require.Nil(t, result)

			if test.name == "copyrights" {
				texts, err := extractor.container.TextStorageRepository(t.Context())
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

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{}; <-ctx.Done() }})

	done := make(chan error, 1)

	go func() {
		_, err := extractor.ExtractRows(
			ctx,
			rows,
			&PictureFields{Subscribed: true, Rights: true, Copyrights: true},
			"en",
			UserContext{UserID: 7},
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
