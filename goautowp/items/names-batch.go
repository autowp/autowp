package items

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// NamesBatch keeps Names' nonempty-name filter and language keys for every item.
func (s *Repository) NamesBatch(ctx context.Context, ids []int64) (map[int64]map[string]string, error) {
	result := make(map[int64]map[string]string, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ItemID   int64  `db:"item_id"`
		Language string `db:"language"`
		Name     string `db:"name"`
	}

	err := s.db.From(schema.ItemLanguageTable).
		Select(schema.ItemLanguageTableItemIDCol, schema.ItemLanguageTableLanguageCol, schema.ItemLanguageTableNameCol).
		Where(schema.ItemLanguageTableItemIDCol.In(ids), goqu.Func("length", schema.ItemLanguageTableNameCol).Gt(0)).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		if result[row.ItemID] == nil {
			result[row.ItemID] = make(map[string]string)
		}

		result[row.ItemID][row.Language] = row.Name
	}

	return result, nil
}
