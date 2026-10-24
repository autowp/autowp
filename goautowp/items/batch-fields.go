package items

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// LanguageNames returns names in one language for the requested items.
func (s *Repository) LanguageNames(ctx context.Context, ids []int64, lang string) (map[int64]string, error) {
	result := make(map[int64]string, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ItemID int64  `db:"item_id"`
		Name   string `db:"name"`
	}

	err := s.db.From(schema.ItemLanguageTable).
		Select(schema.ItemLanguageTableItemIDCol, schema.ItemLanguageTableNameCol).
		Where(schema.ItemLanguageTableItemIDCol.In(ids), schema.ItemLanguageTableLanguageCol.Eq(lang)).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = row.Name
	}

	return result, nil
}

// ItemLanguageCounts excludes the default language, matching the moderator counter.
func (s *Repository) ItemLanguageCounts(ctx context.Context, ids []int64) (map[int64]int32, error) {
	result := make(map[int64]int32, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ItemID int64 `db:"item_id"`
		Count  int32 `db:"count"`
	}

	err := s.db.From(schema.ItemLanguageTable).
		Select(schema.ItemLanguageTableItemIDCol, goqu.COUNT(goqu.Star()).As("count")).
		Where(schema.ItemLanguageTableItemIDCol.In(ids),
			schema.ItemLanguageTableLanguageCol.Neq(schema.DefaultLanguageCode)).
		GroupBy(schema.ItemLanguageTableItemIDCol).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = row.Count
	}

	return result, nil
}

// LinksCounts returns per-item link counts for the requested items.
func (s *Repository) LinksCounts(ctx context.Context, ids []int64) (map[int64]int32, error) {
	result := make(map[int64]int32, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ItemID int64 `db:"item_id"`
		Count  int32 `db:"count"`
	}

	err := s.db.From(schema.ItemLinkTable).
		Select(schema.ItemLinkTableItemIDCol, goqu.COUNT(goqu.Star()).As("count")).
		Where(schema.ItemLinkTableItemIDCol.In(ids)).GroupBy(schema.ItemLinkTableItemIDCol).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = row.Count
	}

	return result, nil
}

// ItemsWithFullText reports items with nonempty full text in any language.
func (s *Repository) ItemsWithFullText(ctx context.Context, ids []int64) (map[int64]bool, error) {
	result := make(map[int64]bool, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var found []int64

	err := s.db.From(schema.ItemLanguageTable).
		Select(schema.ItemLanguageTableItemIDCol).Distinct().
		Join(schema.TextstorageTextTable, goqu.On(
			schema.ItemLanguageTableFullTextIDCol.Eq(schema.TextstorageTextTableIDCol))).
		Where(schema.ItemLanguageTableItemIDCol.In(ids),
			goqu.L("? > 0", goqu.Func("LENGTH", schema.TextstorageTextTableTextCol))).
		ScanValsContext(ctx, &found)
	if err != nil {
		return nil, err
	}

	for _, id := range found {
		result[id] = true
	}

	return result, nil
}

// SubscribedItems reports the requested items to which this user is subscribed.
func (s *Repository) SubscribedItems(ctx context.Context, ids []int64, userID int64) (map[int64]bool, error) {
	result := make(map[int64]bool, len(ids))
	if len(ids) == 0 || userID == 0 {
		return result, nil
	}

	var found []int64

	err := s.db.From(schema.UserItemSubscribeTable).Select(schema.UserItemSubscribeTableItemIDCol).
		Where(schema.UserItemSubscribeTableItemIDCol.In(ids), schema.UserItemSubscribeTableUserIDCol.Eq(userID)).
		ScanValsContext(ctx, &found)
	if err != nil {
		return nil, err
	}

	for _, id := range found {
		result[id] = true
	}

	return result, nil
}
