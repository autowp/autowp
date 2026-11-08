package pictures_test

import (
	"context"
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func TestRelatedGroupPreviewsBatchSelection(t *testing.T) {
	t.Parallel()

	db, database, logger := testutil.NewPostgres(t, "../..")
	_, err := database.ExecContext(t.Context(), `
		CREATE TEMP TABLE picture (id bigint PRIMARY KEY, image_id bigint, status text);
		CREATE TEMP TABLE picture_item (picture_id bigint, item_id bigint, perspective_id integer);
		CREATE TEMP TABLE item_parent_cache (item_id bigint, parent_id bigint, tuning boolean, sport boolean);
		CREATE TEMP TABLE item (id bigint PRIMARY KEY, is_concept boolean);
		INSERT INTO item VALUES (10, false), (20, false), (30, true), (40, false), (50, false);
		INSERT INTO item_parent_cache VALUES
			(11, 10, false, false), (12, 10, true, false), (13, 10, false, true),
			(21, 20, false, false), (31, 30, false, false), (41, 40, false, false),
			(51, 50, false, false);
		INSERT INTO picture VALUES
			(1, 101, 'accepted'), (2, 102, 'accepted'), (3, 103, 'accepted'),
			(4, 104, 'accepted'), (5, 105, 'accepted'), (6, 106, 'inbox'),
			(7, 107, 'accepted'), (8, NULL, 'accepted');
	`)
	require.NoError(t, err)
	_, err = database.ExecContext(t.Context(), `INSERT INTO picture_item VALUES
		(1, 11, $1), (2, 11, $2), (3, 12, $1), (4, 13, $1),
		(5, 21, $2), (6, 21, $1), (7, 31, $2), (5, 31, $1),
		(8, 41, $1), (1, 51, $2), (2, 51, $1), (5, 51, NULL)`,
		schema.PerspectiveFrontStrict, schema.PerspectiveFront)
	require.NoError(t, err)

	repository := pictures.NewRepository(db, nil, nil, nil, nil, "", config.DuplicateFinderConfig{}, nil, nil, nil, nil)

	groups := map[int64][]int64{
		1: {10}, 2: {20}, 3: {10, 20}, 4: {30, 20}, 5: {40}, 6: {999}, 7: {}, 8: {50},
	}
	for id := int64(100); id < 150; id++ {
		groups[id] = []int64{10, 20}
	}

	result, err := repository.RelatedGroupPreviews(t.Context(), groups)
	require.NoError(t, err)
	require.EqualValues(t, 1, logger.Queries.Load())
	require.EqualValues(t, 1, result[1].ID, "stock strict-front beats tuning, sport and other perspectives")
	require.EqualValues(t, 5, result[2].ID, "inbox pictures are excluded")
	require.False(t, result[5].ImageID.Valid)
	require.Nil(t, result[6])
	require.Nil(t, result[7])

	for groupID, ancestors := range groups {
		if len(ancestors) == 0 {
			continue
		}

		selection, err := repository.PictureSelect(&query.PictureListOptions{
			Status: schema.PictureStatusAccepted,
			PictureItem: &query.PictureItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
					ParentIDs: ancestors, ItemsByParentID: &query.ItemListOptions{},
				},
			},
		}, nil, pictures.OrderByAncestorStockFrontFirst)
		require.NoError(t, err)

		var expected struct {
			ID      int64         `db:"id"`
			ImageID sql.NullInt64 `db:"image_id"`
		}

		found, err := selection.Select(goqu.T(query.PictureAlias).Col("id"),
			goqu.T(query.PictureAlias).Col("image_id")).Limit(1).ScanStructContext(t.Context(), &expected)
		require.NoError(t, err)

		if !found {
			require.Nil(t, result[groupID])

			continue
		}

		require.NotNil(t, result[groupID])
		require.Equal(t, expected.ID, result[groupID].ID)
		require.Equal(t, expected.ImageID, result[groupID].ImageID)
	}

	logger.Queries.Store(0)

	empty, err := repository.RelatedGroupPreviews(t.Context(), map[int64][]int64{1: {}})
	require.NoError(t, err)
	require.Empty(t, empty)
	require.Zero(t, logger.Queries.Load())

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.RelatedGroupPreviews(ctx, groups)
	require.ErrorIs(t, err, context.Canceled)
}
