package goautowp

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
)

func newBrandCountsTestServer(t *testing.T) (*ItemsGRPCServer, *itemBatchQueryLogger) {
	t.Helper()

	db, database, logger := newRelatedGroupTestDB(t)
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE picture (id bigint PRIMARY KEY, status text);
		CREATE TEMP TABLE picture_item (picture_id bigint, item_id bigint, type integer, perspective_id integer);
		CREATE TEMP TABLE item (id bigint PRIMARY KEY, is_concept boolean);
		CREATE TEMP TABLE item_parent_cache (item_id bigint, parent_id bigint);
		INSERT INTO item VALUES (11, true);
		INSERT INTO item_parent_cache VALUES (11, 10);
		INSERT INTO picture VALUES
			(1, 'accepted'), (2, 'accepted'), (3, 'accepted'), (4, 'inbox'),
			(5, 'removed'), (6, 'accepted'), (7, 'accepted'), (8, 'accepted'),
			(9, 'accepted'), (10, 'accepted'), (11, 'accepted');
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO picture_item VALUES
		(1, 10, 1, $1), (1, 10, 2, $1), (1, 10, 3, $2),
		(2, 10, 1, $3), (2, 10, 2, NULL), (3, 10, 1, NULL),
		(4, 10, 1, $1), (5, 10, 1, $2), (6, 20, 1, $1),
		(7, 10, 1, $2), (8, 10, 1, $1), (10, 10, 1, $1),
		(10, 10, 2, NULL), (11, 11, 1, $1)`,
		items.PerspectiveIDLogo, items.PerspectiveIDMixed, schema.PerspectiveFront)
	require.NoError(t, err)
	i18nBundle, err := cnt.I18n()
	require.NoError(t, err)

	return &ItemsGRPCServer{
		repository: items.NewRepository(db, 0, nil, nil, nil),
		picturesRepository: pictures.NewRepository(db, nil, nil, nil, nil,
			config.DuplicateFinderConfig{}, nil, nil, nil, nil),
		i18n: i18nBundle,
	}, logger
}

func TestBrandCountsMatchesSeparateCounts(t *testing.T) {
	t.Parallel()

	server, logger := newBrandCountsTestServer(t)
	counts, err := server.picturesRepository.BrandCounts(t.Context(), 10)
	require.NoError(t, err)
	require.Equal(t, pictures.BrandPictureCounts{Logotypes: 3, Mixed: 2, Other: 3}, counts)
	require.EqualValues(t, 1, logger.queries.Load())

	for _, group := range []struct {
		perspectiveID int32
		exclude       []int32
		actual        int
	}{
		{perspectiveID: items.PerspectiveIDLogo, actual: counts.Logotypes},
		{perspectiveID: items.PerspectiveIDMixed, actual: counts.Mixed},
		{exclude: []int32{items.PerspectiveIDLogo, items.PerspectiveIDMixed}, actual: counts.Other},
	} {
		expected, err := server.picturesRepository.Count(t.Context(), &query.PictureListOptions{
			Status: schema.PictureStatusAccepted,
			PictureItem: &query.PictureItemListOptions{
				ItemID: 10, PerspectiveID: group.perspectiveID, ExcludePerspectiveID: group.exclude,
			},
		})
		require.NoError(t, err)
		require.Equal(t, expected, group.actual)
	}

	require.EqualValues(t, 4, logger.queries.Load(), "one combined query versus three separate counts")
	logger.queries.Store(0)

	empty, err := server.picturesRepository.BrandCounts(t.Context(), 999)
	require.NoError(t, err)
	require.Equal(t, pictures.BrandPictureCounts{}, empty)
	require.EqualValues(t, 1, logger.queries.Load())

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = server.picturesRepository.BrandCounts(ctx, 10)
	require.ErrorIs(t, err, context.Canceled)
}

func TestBrandOtherGroupsUsesCombinedCount(t *testing.T) {
	t.Parallel()

	server, logger := newBrandCountsTestServer(t)
	groups, err := server.otherGroups(t.Context(), 10, "brand", schema.EnglishLanguageCode)
	require.NoError(t, err)
	require.Len(t, groups, 4)
	require.EqualValues(t, 2, logger.queries.Load(), "one concept check and one combined picture count")
	require.Equal(t, frontend.BrandConceptsRoute("brand"), groups[0].GetRouterLink())
	require.Equal(t, frontend.BrandGroupRoute("brand", frontend.BrandLogotypes), groups[1].GetRouterLink())
	require.EqualValues(t, 3, groups[1].GetCount())
	require.Equal(t, frontend.BrandGroupRoute("brand", frontend.BrandMixed), groups[2].GetRouterLink())
	require.EqualValues(t, 2, groups[2].GetCount())
	require.Equal(t, frontend.BrandGroupRoute("brand", frontend.BrandOther), groups[3].GetRouterLink())
	require.EqualValues(t, 3, groups[3].GetCount())

	for _, group := range groups {
		require.NotEmpty(t, group.GetName())
	}

	logger.queries.Store(0)

	groups, err = server.otherGroups(t.Context(), 20, "other-brand", schema.EnglishLanguageCode)
	require.NoError(t, err)
	require.Len(t, groups, 1, "empty groups are omitted")
	require.Equal(t, frontend.BrandGroupRoute("other-brand", frontend.BrandLogotypes), groups[0].GetRouterLink())
	require.EqualValues(t, 1, groups[0].GetCount())
	require.EqualValues(t, 2, logger.queries.Load())

	groups, err = server.otherGroups(t.Context(), 999, "empty", schema.EnglishLanguageCode)
	require.NoError(t, err)
	require.Empty(t, groups)
}
