package log

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/doug-martin/goqu/v9/exp"
	"golang.org/x/sync/errgroup"
)

func (s *Repository) preloadEventLinks(ctx context.Context, rows []Event) error {
	if len(rows) == 0 {
		return nil
	}

	ids := make([]int64, 0, len(rows))
	for _, row := range rows {
		ids = append(ids, row.ID)
	}

	var items, pictures map[int64][]int64

	group, lookupCtx := errgroup.WithContext(ctx)
	group.Go(func() error {
		var err error

		items, err = s.eventLinks(lookupCtx, ids, schema.LogEventItemTable,
			schema.LogEventItemTableLogEventIDCol, schema.LogEventItemTableItemIDCol)

		return err
	})
	group.Go(func() error {
		var err error

		pictures, err = s.eventLinks(lookupCtx, ids, schema.LogEventPictureTable,
			schema.LogEventPictureTableLogEventIDCol, schema.LogEventPictureTablePictureIDCol)

		return err
	})

	if err := group.Wait(); err != nil {
		return err
	}

	for index, row := range rows {
		rows[index].Items = items[row.ID]
		rows[index].Pictures = pictures[row.ID]
	}

	return nil
}

// Preserve each event's original equality lookup and traversal order while
// batching the round trips. Repeated events reuse the same selection.
func (s *Repository) eventLinks(
	ctx context.Context, ids []int64, table, eventIDCol, valueCol exp.IdentifierExpression,
) (map[int64][]int64, error) {
	result := make(map[int64][]int64, len(ids))

	var combined *goqu.SelectDataset

	seen := make(map[int64]bool, len(ids))
	for _, id := range ids {
		if seen[id] {
			continue
		}

		seen[id] = true
		selection := s.db.From(table).
			Select(goqu.V(id).As("event_id"), valueCol.As("linked_id"),
				goqu.ROW_NUMBER().Over(goqu.W()).As("position")).Where(eventIDCol.Eq(id))

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
		EventID  int64 `db:"event_id"`
		LinkedID int64 `db:"linked_id"`
		Position int64 `db:"position"`
	}

	err := s.db.From(combined.As("batch")).Select(goqu.Star()).
		Order(goqu.C("event_id").Asc(), goqu.C("position").Asc()).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.EventID] = append(result[row.EventID], row.LinkedID)
	}

	return result, nil
}
