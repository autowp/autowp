package pictures

import (
	"context"
	"database/sql"
	"maps"
	"slices"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// RelatedGroupPreviews chooses one accepted picture per group, using the same
// aggregate priorities as Picture(..., OrderByAncestorStockFrontFirst).
func (s *Repository) RelatedGroupPreviews(
	ctx context.Context, groups map[int64][]int64,
) (map[int64]*schema.PictureRow, error) {
	result := make(map[int64]*schema.PictureRow, len(groups))

	var membership *goqu.SelectDataset

	parents := make(map[int64]bool)

	for _, groupID := range slices.Sorted(maps.Keys(groups)) {
		for _, parentID := range groups[groupID] {
			pair := s.db.Select(goqu.V(groupID).As("group_id"), goqu.V(parentID).As("parent_id"))
			if membership == nil {
				membership = pair
			} else {
				membership = membership.UnionAll(pair)
			}

			parents[parentID] = true
		}
	}

	if membership == nil {
		return result, nil
	}

	options := &query.PictureListOptions{
		Status: schema.PictureStatusAccepted,
		PictureItem: &query.PictureItemListOptions{
			ItemParentCacheAncestor: &query.ItemParentCacheListOptions{
				ParentIDs: slices.Collect(maps.Keys(parents)), ItemsByParentID: &query.ItemListOptions{},
			},
		},
	}

	selection, err := s.PictureSelect(options, nil, OrderByAncestorStockFrontFirst)
	if err != nil {
		return nil, err
	}

	groupTable := goqu.T("preview_groups")
	groupIDCol := groupTable.Col("group_id")
	cacheAlias := options.PictureItem.ItemParentCacheAncestorAlias(options.PictureItemAlias(query.PictureAlias, 0))
	selection = selection.Join(membership.As("preview_groups"), goqu.On(
		goqu.T(cacheAlias).Col(schema.ItemParentCacheTableParentIDColName).Eq(groupTable.Col("parent_id")),
	)).Select(
		groupIDCol,
		goqu.T(query.PictureAlias).Col(schema.PictureTableIDColName),
		goqu.T(query.PictureAlias).Col(schema.PictureTableImageIDColName),
	).GroupByAppend(groupIDCol).Distinct(groupIDCol).OrderPrepend(groupIDCol.Asc())

	var rows []struct {
		GroupID int64         `db:"group_id"`
		ID      int64         `db:"id"`
		ImageID sql.NullInt64 `db:"image_id"`
	}

	if err := selection.ScanStructsContext(ctx, &rows); err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.GroupID] = &schema.PictureRow{ID: row.ID, ImageID: row.ImageID}
	}

	return result, nil
}
