package pictures

import (
	"context"
	"fmt"
	"maps"
	"slices"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// GroupedPictures retains each selection's filters, ordering and limit. A
// window ordinal lets the outer UNION ALL preserve the order within each group.
func (s *Repository) GroupedPictures(
	ctx context.Context, groups map[int64]*query.PictureListOptions, fields *PictureFields, order OrderBy,
) (map[int64][]*schema.PictureRow, error) {
	result := make(map[int64][]*schema.PictureRow, len(groups))

	var combined *goqu.SelectDataset

	for _, id := range slices.Sorted(maps.Keys(groups)) {
		options := groups[id]

		selection, err := s.PictureSelect(options, fields, order)
		if err != nil {
			return nil, fmt.Errorf("PictureSelect(): %w", err)
		}

		var orderColumns []interface{}

		if selection.GetClauses().HasOrder() {
			for _, column := range selection.GetClauses().Order().Columns() {
				orderColumns = append(orderColumns, column)
			}
		}

		selection = selection.SelectAppend(goqu.V(id).As("batch_group"),
			goqu.ROW_NUMBER().Over(goqu.W().OrderBy(orderColumns...)).As("batch_position"))
		if options.Limit > 0 {
			selection = selection.Limit(uint(options.Limit))
		}
		// Wrapping isolates ORDER BY and LIMIT from the neighboring group queries.
		part := s.db.From(selection.As("selection")).Select(goqu.Star())
		if combined == nil {
			combined = part
		} else {
			combined = combined.UnionAll(part)
		}
	}

	if combined == nil {
		return result, nil
	}

	var rows []struct {
		schema.PictureRow

		GroupID  int64 `db:"batch_group"`
		Position int64 `db:"batch_position"`
	}

	err := s.db.From(combined.As("batch")).Select(goqu.Star()).
		Order(goqu.C("batch_group").Asc(), goqu.C("batch_position").Asc()).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		picture := row.PictureRow
		result[row.GroupID] = append(result[row.GroupID], &picture)
	}

	return result, nil
}
