package messaging

import (
	"context"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

func (s *Repository) dialogCounts(ctx context.Context, userID int64, peers []int64) (map[int64]int32, error) {
	counts := make(map[int64]int32, len(peers))
	if len(peers) == 0 {
		return counts, nil
	}

	from := schema.PersonalMessageTableFromUserIDCol
	to := schema.PersonalMessageTableToUserIDCol
	peer := goqu.Case().When(from.Eq(userID), to).Else(from)

	var rows []struct {
		UserID int64 `db:"user_id"`
		Count  int64 `db:"count"`
	}
	// A single OR counts self-addressed messages once, including when only one
	// of the two deletion flags permits visibility, just like getDialogSelect.
	err := s.db.From(schema.PersonalMessageTable).
		Select(peer.As("user_id"), goqu.COUNT(goqu.Star()).As("count")).
		Where(goqu.Or(
			goqu.And(from.Eq(userID), to.In(peers), schema.PersonalMessageTableDeletedByFromCol.IsNotTrue()),
			goqu.And(to.Eq(userID), from.In(peers), schema.PersonalMessageTableDeletedByToCol.IsNotTrue()),
		)).GroupBy(peer).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	for _, row := range rows {
		counts[row.UserID] = int32(row.Count) //nolint:gosec // Match GetDialogCount's conversion.
	}

	return counts, nil
}
