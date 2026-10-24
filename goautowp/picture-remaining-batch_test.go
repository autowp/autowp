package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"testing"
	"time"

	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func newRemainingPictureExtractor(t *testing.T) (*PictureExtractor, *goqu.Database, *sql.DB, []*schema.PictureRow) {
	t.Helper()
	extractor, db, pool, _ := newPictureFieldBatchExtractor(t)
	author := createItem(t, conn, cnt, &Item{Name: "Suggested <author>", ItemTypeId: ItemType_ITEM_TYPE_PERSON})
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE picture AS SELECT * FROM public.picture WITH NO DATA;
 ALTER TABLE pg_temp.picture ADD PRIMARY KEY (id);
 INSERT INTO picture(id,identity,status,width,height,created_at,license_id,owner_id) VALUES
 (1,'one','inbox',10,20,'2020-01-01',0,7),
 (3,'three','accepted',10,20,'2020-01-01',0,7),
 (5,'five','inbox',10,20,'2020-01-01',0,8),
 (6,'six','accepted',10,20,'2020-01-01',0,8),
 (7,'seven','accepted',10,20,'2020-01-01',0,8),
 (9,'nine','removed',10,20,'2020-01-01',0,8),
 (11,'eleven','accepted',10,20,'2020-01-01',0,8),
 (13,'thirteen','inbox',10,20,'2020-01-01',0,NULL);
 CREATE TEMP TABLE picture_item (picture_id bigint,item_id bigint,type integer);
 INSERT INTO picture_item VALUES (1,100,1),(1,200,2),(3,100,1),(3,200,2),(5,200,1),
 (6,200,1),(7,300,1),(9,300,2),(11,400,1),(13,500,1);
 CREATE TEMP TABLE picture_author_suggestion (picture_id bigint,item_id bigint,source text,raw_value text);
 `)
	require.NoError(t, err)
	_, err = pool.ExecContext(t.Context(), `INSERT INTO picture_author_suggestion VALUES
 (1,$1,'exif-artist','First'),(1,$1,'exif-copyright','Second'),
 (3,$1,'exif-artist','Other picture'),(9,-999,'exif-artist','Missing item')`, author)
	require.NoError(t, err)
	repository, err := extractor.container.PicturesRepository(t.Context())
	require.NoError(t, err)
	rows, _, err := repository.Pictures(t.Context(), &query.PictureListOptions{}, nil, pictures.OrderByIDAsc, false)
	require.NoError(t, err)

	rows = append(rows, rows[1], &schema.PictureRow{ID: 15, Status: schema.PictureStatusAccepted})

	db.Logger(nil)

	return extractor, db, pool, rows
}

func legacyRemainingPictureFields(t *testing.T, extractor *PictureExtractor, rows []*schema.PictureRow,
	fields *PictureFields, lang string, user UserContext,
) []*Picture {
	t.Helper()
	ctx := t.Context()
	other, _ := proto.Clone(fields).(*PictureFields)
	other.AcceptedCount = false
	other.IsLast = false
	other.AuthorSuggestions = false
	other.Siblings = nil
	result, err := extractor.ExtractRows(ctx, rows, other, lang, user)
	require.NoError(t, err)
	repository, err := extractor.container.PicturesRepository(ctx)
	require.NoError(t, err)

	for index, row := range rows {
		options := &query.PictureListOptions{
			Status: schema.PictureStatusAccepted,
			PictureItem: &query.PictureItemListOptions{
				PictureItemByItemID: &query.PictureItemListOptions{PictureID: row.ID},
			},
		}
		if fields.GetAcceptedCount() {
			count, err := repository.Count(ctx, options)
			require.NoError(t, err)

			result[index].AcceptedCount = int32(count) //nolint:gosec // Original conversion.
		}

		if fields.GetIsLast() && row.Status == schema.PictureStatusAccepted {
			options.ExcludeID = row.ID
			exists, err := repository.Exists(ctx, options)
			require.NoError(t, err)

			result[index].IsLast = !exists
		}

		if fields.GetAuthorSuggestions() && (containsRole(user.Roles, users.RoleModer) ||
			(user.UserID != 0 && row.OwnerID.Valid && row.OwnerID.Int64 == user.UserID)) {
			suggestions, err := repository.PictureAuthorSuggestions(ctx, row.ID)
			require.NoError(t, err)

			if len(suggestions) > 0 {
				itemRepository, err := extractor.container.ItemsRepository(ctx)
				require.NoError(t, err)

				ids := make([]int64, 0, len(suggestions))
				for _, suggestion := range suggestions {
					ids = append(ids, suggestion.ItemID)
				}

				itemFields := &ItemFields{NameHtml: true, NameText: true}
				itemRows, _, err := itemRepository.List(
					ctx,
					&query.ItemListOptions{ItemIDs: ids},
					convertItemFields(itemFields),
					items.OrderByNone,
					false,
				)
				require.NoError(t, err)
				values, err := extractor.container.ItemExtractor().ExtractRows(ctx, itemRows, itemFields, lang, user)
				require.NoError(t, err)

				byID := make(map[int64]*Item)
				for _, item := range values {
					byID[item.GetId()] = item
				}

				for _, suggestion := range suggestions {
					result[index].AuthorSuggestions = append(result[index].AuthorSuggestions, &PictureAuthorSuggestion{
						ItemId:   suggestion.ItemID,
						Item:     byID[suggestion.ItemID],
						Source:   suggestion.Source,
						RawValue: suggestion.RawValue,
					})
				}
			}
		}

		if request := fields.GetSiblings(); request != nil {
			values := make([]*Picture, 4)

			for slot, option := range []*query.PictureListOptions{
				{IDLt: row.ID},
				{IDGt: row.ID},
				{IDLt: row.ID, Status: schema.PictureStatusInbox},
				{IDGt: row.ID, Status: schema.PictureStatusInbox},
			} {
				order := pictures.OrderByIDDesc
				if slot%2 != 0 {
					order = pictures.OrderByIDAsc
				}

				neighbor, err := repository.Picture(ctx, option, convertPictureFields(request.GetFields()), order)
				if errors.Is(err, sql.ErrNoRows) {
					continue
				}

				require.NoError(t, err)
				nested := legacyRemainingPictureFields(
					t,
					extractor,
					[]*schema.PictureRow{neighbor},
					request.GetFields(),
					lang,
					user,
				)
				values[slot] = nested[0]
			}

			result[index].Siblings = &PictureSiblings{
				Prev:    values[0],
				Next:    values[1],
				PrevNew: values[2],
				NextNew: values[3],
			}
		}
	}

	return result
}

func containsRole(roles []string, role string) bool {
	for _, value := range roles {
		if value == role {
			return true
		}
	}

	return false
}

func TestPictureRemainingBatchesMatchIndividualReads(t *testing.T) {
	t.Parallel()
	extractor, db, _, rows := newRemainingPictureExtractor(t)
	// A zero source ID means an unset ID filter in the original query builders.
	rows = append(rows, &schema.PictureRow{ID: 0, Status: schema.PictureStatusAccepted})

	for _, lang := range []string{"en", "ru"} {
		for _, user := range []UserContext{{}, {UserID: 7}, {UserID: 8}, {UserID: 42, Roles: []string{users.RoleModer}}} {
			fields := &PictureFields{
				AcceptedCount:     true,
				IsLast:            true,
				AuthorSuggestions: true,
				Siblings:          &PicturesRequest{Fields: &PictureFields{}},
			}
			original := proto.Clone(fields)
			expected := legacyRemainingPictureFields(t, extractor, rows, fields, lang, user)
			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			actual, err := extractor.ExtractRows(t.Context(), rows, fields, lang, user)
			require.NoError(t, err)
			db.Logger(nil)
			require.True(
				t,
				proto.Equal(&PicturesList{Items: expected}, &PicturesList{Items: actual}),
				"language %s user %v",
				lang,
				user,
			)

			want := 5
			if user.UserID == 0 {
				want = 3
			}

			require.EqualValues(t, want, logger.queries.Load())
			require.True(t, proto.Equal(original, fields))
		}
	}
	// Nested sibling requests keep their independent field sets and terminate at the request depth.
	fields := &PictureFields{Siblings: &PicturesRequest{Fields: &PictureFields{
		AcceptedCount: true, IsLast: true, Siblings: &PicturesRequest{Fields: &PictureFields{Rights: true}},
	}}}
	expected := legacyRemainingPictureFields(t, extractor, rows[:2], fields, "en", UserContext{})
	actual, err := extractor.ExtractRows(t.Context(), rows[:2], fields, "en", UserContext{})
	require.NoError(t, err)
	require.True(t, proto.Equal(&PicturesList{Items: expected}, &PicturesList{Items: actual}))
}

func TestPictureRemainingBatchesEmptyAndUnrequested(t *testing.T) {
	t.Parallel()
	extractor, db, _, rows := newRemainingPictureExtractor(t)
	logger := &itemBatchQueryLogger{}
	db.Logger(logger)

	fields := &PictureFields{
		AcceptedCount:     true,
		IsLast:            true,
		AuthorSuggestions: true,
		Siblings:          &PicturesRequest{Fields: &PictureFields{}},
	}
	result, err := extractor.ExtractRows(t.Context(), nil, fields, "en", UserContext{UserID: 7})
	require.NoError(t, err)
	require.NotNil(t, result)
	require.Empty(t, result)
	require.Zero(t, logger.queries.Load())
	_, err = extractor.ExtractRows(t.Context(), rows, &PictureFields{}, "en", UserContext{})
	require.NoError(t, err)
	require.Zero(t, logger.queries.Load())
	_, err = extractor.ExtractRows(
		t.Context(),
		rows[:1],
		&PictureFields{IsLast: true, AuthorSuggestions: true},
		"en",
		UserContext{},
	)
	require.NoError(t, err)
	require.Zero(t, logger.queries.Load())
}

func TestPictureRemainingBatchesCancellation(t *testing.T) {
	t.Parallel()
	extractor, db, _, rows := newRemainingPictureExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	started := make(chan struct{}, 3)

	db.Logger(&itemBatchQueryLogger{beforeQuery: func() { started <- struct{}{}; <-ctx.Done() }})

	done := make(chan error, 1)

	go func() {
		_, err := extractor.ExtractRows(ctx, rows, &PictureFields{
			AcceptedCount: true, AuthorSuggestions: true,
			Siblings: &PicturesRequest{Fields: &PictureFields{}},
		}, "en", UserContext{UserID: 7})
		done <- err
	}()

	for range 3 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("three independent preloads must start together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
}

func TestPictureRemainingBatchErrors(t *testing.T) {
	t.Parallel()

	for _, test := range []struct {
		name      string
		fields    *PictureFields
		statement string
	}{
		{
			name: "authors", fields: &PictureFields{AuthorSuggestions: true},
			statement: "ALTER TABLE pg_temp.picture_author_suggestion RENAME COLUMN source TO broken",
		},
		{
			name: "counts", fields: &PictureFields{AcceptedCount: true, IsLast: true},
			statement: "ALTER TABLE pg_temp.picture_item RENAME COLUMN item_id TO broken",
		},
		{
			name: "siblings", fields: &PictureFields{Siblings: &PicturesRequest{Fields: &PictureFields{}}},
			statement: "ALTER TABLE pg_temp.picture RENAME COLUMN id TO broken",
		},
		{
			name:   "nested sibling extraction",
			fields: &PictureFields{Siblings: &PicturesRequest{Fields: &PictureFields{Copyrights: true}}},
			statement: `UPDATE pg_temp.picture SET copyrights_text_id=1;
 UPDATE pg_temp.textstorage_text SET text=NULL WHERE id=1`,
		},
	} {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()
			extractor, _, pool, rows := newRemainingPictureExtractor(t)
			_, err := pool.ExecContext(t.Context(), test.statement)
			require.NoError(t, err)

			if test.name == "authors" {
				_, err = extractor.ExtractRows(t.Context(), rows, test.fields, "en", UserContext{})
				require.NoError(t, err, "invisible suggestions must never be queried")
			}

			result, err := extractor.ExtractRows(t.Context(), rows, test.fields, "en", UserContext{UserID: 7})
			require.Error(t, err)
			require.Nil(t, result)
			require.NoError(t, t.Context().Err())
		})
	}
}
