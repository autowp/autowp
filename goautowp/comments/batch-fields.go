package comments

import (
	"context"

	"github.com/autowp/goautowp/schema"
)

// TopicStats returns counts for the requested items; absent items have zero counts.
func (s *Repository) TopicStats(
	ctx context.Context, ids []int64, typeID schema.CommentMessageType,
) (map[int64]int32, error) {
	result := make(map[int64]int32, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	var rows []struct {
		ItemID int64 `db:"item_id"`
		Count  int32 `db:"count"`
	}

	err := s.db.From(schema.CommentTopicTable).
		Select(schema.CommentTopicTableItemIDCol, schema.CommentTopicTableMessagesCol.As("count")).
		Where(schema.CommentTopicTableItemIDCol.In(ids), schema.CommentTopicTableTypeIDCol.Eq(typeID)).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		result[row.ItemID] = row.Count
	}

	return result, nil
}
