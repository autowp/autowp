package textstorage

import (
	"context"

	"github.com/autowp/goautowp/schema"
)

// Texts loads a batch of texts. Missing IDs are omitted; a stored NULL retains
// the scanning error of Text instead of silently becoming an empty string.
func (s *Repository) Texts(ctx context.Context, ids []int32) (map[int32]string, error) {
	result := make(map[int32]string, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ID   int32  `db:"id"`
		Text string `db:"text"`
	}

	err := s.db.From(schema.TextstorageTextTable).
		Select(schema.TextstorageTextTableIDCol, schema.TextstorageTextTableTextCol).
		Where(schema.TextstorageTextTableIDCol.In(ids)).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ID] = row.Text
	}

	return result, nil
}
