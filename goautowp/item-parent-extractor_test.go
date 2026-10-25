package goautowp

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/proto"
)

func TestItemParentExtractorParallelPreloads(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	token, err := cnt.Keycloak().Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	parentID := createItem(t, conn, cnt, &Item{
		Name: "parent-prefetch-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE, IsGroup: true,
	})
	apiCtx := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken)

	for range 2 {
		childID := createItem(t, conn, cnt, &Item{
			Name: "child-prefetch-" + uuid.NewString(), ItemTypeId: ItemType_ITEM_TYPE_VEHICLE,
		})
		_, err := NewItemsClient(conn).CreateItemParent(apiCtx, &ItemParent{ItemId: childID, ParentId: parentID})
		require.NoError(t, err)
	}

	fields := &ItemParentFields{
		Item:            &ItemFields{NameText: true, HasText: true},
		Parent:          &ItemFields{NameHtml: true, CommentsCount: true},
		DuplicateParent: &ItemFields{NameText: true},
		DuplicateChild:  &ItemFields{NameText: true},
		ChildDescendantPictures: &PicturesRequest{Options: &PictureListOptions{
			Status: PictureStatus_PICTURE_STATUS_ACCEPTED,
		}},
	}
	requested := proto.Clone(fields)

	// Keep logging isolated from the shared integration container.
	database, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)
	database.SetMaxOpenConns(2)
	t.Cleanup(func() { require.NoError(t, database.Close()) })

	goquDB := goqu.New("postgres", database)
	container := &Container{config: cfg, goquDB: goquDB}
	repository, err := container.ItemsRepository(ctx)
	require.NoError(t, err)
	rows, _, err := repository.ItemParents(ctx, &query.ItemParentListOptions{
		ParentID: parentID, Language: schema.EnglishLanguageCode,
	}, items.ItemParentFields{}, items.ItemParentOrderByNone)
	require.NoError(t, err)
	require.Len(t, rows, 2)

	rows[0], rows[1] = rows[1], rows[0]
	rows = append(rows, rows[0])
	extractor := NewItemParentExtractor(container)
	inlineCtx := context.WithValue(ctx, itemLookupContextKey{}, true)

	expected := make([]*ItemParent, 0, len(rows))
	for _, row := range rows {
		individual, err := extractor.ExtractRows(
			inlineCtx,
			[]*items.ItemParent{row},
			fields,
			schema.EnglishLanguageCode,
			UserContext{},
		)
		require.NoError(t, err)

		expected = append(expected, individual...)
	}

	// Both SQL reads must start before either can proceed.
	logger := &itemBatchQueryLogger{}
	unblock := blockItemExtractionQueries(t, logger, 2)
	goquDB.Logger(logger)

	lookupCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	type extractionResult struct {
		items []*ItemParent
		err   error
	}

	done := make(chan extractionResult, 1)

	go func() {
		result, err := extractor.ExtractRows(lookupCtx, rows, fields, schema.EnglishLanguageCode, UserContext{})
		done <- extractionResult{items: result, err: err}
	}()

	unblock()

	actual := <-done
	require.NoError(t, actual.err)
	require.Len(t, actual.items, len(rows))

	for index, result := range actual.items {
		require.True(t, proto.Equal(expected[index], result), "parallel preloads differ at row %d", index)
		require.Equal(t, rows[index].ItemID, result.GetItemId())
		require.Equal(t, rows[index].ItemID, result.GetItem().GetId())
		require.Equal(t, rows[index].ParentID, result.GetParentId())
		require.Equal(t, parentID, result.GetParent().GetId())
		require.NotEmpty(t, result.GetItem().GetNameText())
		require.Empty(t, result.GetItem().GetNameHtml())
		require.NotEmpty(t, result.GetParent().GetNameHtml())
		require.Empty(t, result.GetParent().GetNameText())
	}

	require.True(t, proto.Equal(requested, fields), "preloads must not change field selections")

	canceledCtx, cancelRequest := context.WithCancel(ctx)
	cancelRequest()

	result, err := extractor.ExtractRows(canceledCtx, rows, fields, schema.EnglishLanguageCode, UserContext{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, result)

	logger.queries.Store(0)

	result, err = extractor.ExtractRows(ctx, rows, nil, schema.EnglishLanguageCode, UserContext{})
	require.NoError(t, err)
	require.Len(t, result, len(rows))
	require.Zero(t, logger.queries.Load(), "unselected child/parent fields require no prefetch")

	for _, relation := range result {
		require.Nil(t, relation.GetItem())
		require.Nil(t, relation.GetParent())
	}

	result, err = extractor.ExtractRows(ctx, nil, fields, schema.EnglishLanguageCode, UserContext{})
	require.NoError(t, err)
	require.NotNil(t, result)
	require.Empty(t, result)
	require.Zero(t, logger.queries.Load(), "empty input requires no prefetch")
}
