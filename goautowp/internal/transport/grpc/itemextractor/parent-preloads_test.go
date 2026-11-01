package itemextractor

import (
	"context"
	"fmt"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/transport/extraction"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestItemParentExtractorParallelPreloads(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	fixture, goquDB := newPreloadTestExtractor(t)

	parentID := createTestItem(t, fixture, schema.ItemTableItemTypeIDVehicle, true, "Parent prefetch")

	for index := range 2 {
		childID := createTestItem(t, fixture, schema.ItemTableItemTypeIDVehicle, false, "Child prefetch")
		_, err := fixture.itemsRepository.CreateItemParent(ctx, childID, parentID, schema.ItemParentTypeDefault,
			fmt.Sprintf("child-%d", index))
		require.NoError(t, err)
	}

	fields := &pb.ItemParentFields{
		Item:            &pb.ItemFields{NameText: true, HasText: true},
		Parent:          &pb.ItemFields{NameHtml: true, CommentsCount: true},
		DuplicateParent: &pb.ItemFields{NameText: true},
		DuplicateChild:  &pb.ItemFields{NameText: true},
		ChildDescendantPictures: &pb.PicturesRequest{Options: &pb.PictureListOptions{
			Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED,
		}},
	}
	requested := proto.Clone(fields)

	repository := fixture.itemsRepository
	rows, _, err := repository.ItemParents(ctx, &query.ItemParentListOptions{
		ParentID: parentID, Language: schema.EnglishLanguageCode,
	}, items.ItemParentFields{}, items.ItemParentOrderByNone)
	require.NoError(t, err)
	require.Len(t, rows, 2)

	rows[0], rows[1] = rows[1], rows[0]
	rows = append(rows, rows[0])
	extractor := fixture.parentExtractor
	inlineCtx := extraction.WithInlineLookups(ctx)

	expected := make([]*pb.ItemParent, 0, len(rows))
	for _, row := range rows {
		individual, err := extractor.ExtractRows(
			inlineCtx,
			[]*items.ItemParent{row},
			fields,
			schema.EnglishLanguageCode,
			authn.User{},
		)
		require.NoError(t, err)

		expected = append(expected, individual...)
	}

	// Both SQL reads must start before either can proceed.
	logger := &testutil.QueryLogger{}
	unblock := testutil.BlockQueries(t, logger, 2)
	goquDB.Logger(logger)

	lookupCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()

	type extractionResult struct {
		items []*pb.ItemParent
		err   error
	}

	done := make(chan extractionResult, 1)

	go func() {
		result, err := extractor.ExtractRows(lookupCtx, rows, fields, schema.EnglishLanguageCode, authn.User{})
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

	result, err := extractor.ExtractRows(canceledCtx, rows, fields, schema.EnglishLanguageCode, authn.User{})
	require.ErrorIs(t, err, context.Canceled)
	require.Nil(t, result)
}
