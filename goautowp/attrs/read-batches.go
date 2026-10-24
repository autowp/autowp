package attrs

import (
	"context"
	"maps"
	"slices"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
)

func (s *Repository) ContributorsBatch(ctx context.Context, ids []int64) (map[int64][]Contributor, error) {
	result := make(map[int64][]Contributor, len(ids))
	selected := make(map[int64]struct{})

	for _, id := range ids {
		if id != 0 {
			selected[id] = struct{}{}
		}
	}

	if len(selected) == 0 {
		return result, nil
	}

	var rows []struct {
		Contributor

		ItemID int64 `db:"item_id"`
	}

	err := s.db.From(schema.AttrsUserValuesTable).
		Select(
			schema.AttrsUserValuesTableItemIDCol,
			schema.AttrsUserValuesTableUserIDCol,
			goqu.COUNT(goqu.Star()).As("count"),
		).
		Where(schema.AttrsUserValuesTableItemIDCol.In(slices.Collect(maps.Keys(selected)))).
		GroupBy(schema.AttrsUserValuesTableItemIDCol, schema.AttrsUserValuesTableUserIDCol).
		Order(goqu.C("count").Desc()).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = append(result[row.ItemID], row.Contributor)
	}

	return result, nil
}

// UserValueRowsForValues reads only the requested attribute/item pairs. Zero
// retains the unset-filter semantics of UserValueRows.
func (s *Repository) UserValueRowsForValues(
	ctx context.Context,
	values []schema.AttrsValueRow,
) ([]schema.AttrsUserValueRow, error) {
	rows := make([]schema.AttrsUserValueRow, 0)
	if len(values) == 0 {
		return rows, nil
	}

	source := goqu.T(query.AttrsUserValuesAlias)
	conditions := make([]exp.Expression, 0, len(values))
	seen := make(map[[2]int64]bool)

	for _, value := range values {
		key := [2]int64{value.AttributeID, value.ItemID}
		if seen[key] {
			continue
		}

		seen[key] = true

		var pair []exp.Expression
		if value.AttributeID != 0 {
			pair = append(pair, source.Col(schema.AttrsUserValuesTableAttributeIDColName).Eq(value.AttributeID))
		}

		if value.ItemID != 0 {
			pair = append(pair, source.Col(schema.AttrsUserValuesTableItemIDColName).Eq(value.ItemID))
		}

		if len(pair) == 0 {
			conditions = append(conditions, goqu.V(true))
		} else {
			conditions = append(conditions, goqu.And(pair...))
		}
	}

	options := query.AttrsUserValueListOptions{}
	err := options.Select(s.db, query.AttrsUserValuesAlias).
		Select(
			source.Col(schema.AttrsUserValuesTableAttributeIDColName),
			source.Col(schema.AttrsUserValuesTableItemIDColName),
			source.Col(schema.AttrsUserValuesTableUserIDColName),
			source.Col(schema.AttrsUserValuesTableUpdateDateColName)).
		Where(goqu.Or(conditions...)).ScanStructsContext(ctx, &rows)

	return rows, err
}
