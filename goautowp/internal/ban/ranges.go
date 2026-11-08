package ban

import (
	"context"
	"fmt"
	"net/netip"
	"time"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
)

type RangeBan struct {
	Network  string    `db:"network"`
	Until    time.Time `db:"until"`
	Reason   string    `db:"reason"`
	ByUserID int64     `db:"by_user_id"`
}

func (s *Repository) ActiveRanges(ctx context.Context) ([]RangeBan, error) {
	rows := make([]RangeBan, 0)

	err := s.db.From(schema.IPBanTable).Select(
		goqu.Cast(schema.IPBanTableIPCol, "text").As("network"), schema.IPBanTableUntilCol,
		schema.IPBanTableReasonCol, goqu.COALESCE(schema.IPBanTableByUserIDCol, 0).As(schema.IPBanTableByUserIDColName),
	).Where(schema.IPBanTableUntilCol.Gte(goqu.Func("NOW"))).
		Order(schema.IPBanTableUntilCol.Desc(), schema.IPBanTableIPCol.Asc()).ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, fmt.Errorf("read active ban ranges: %w", err)
	}

	return rows, nil
}

func (s *Repository) AddManualNetwork(
	ctx context.Context,
	prefix netip.Prefix,
	duration time.Duration,
	userID int64,
) error {
	if !validNetwork(prefix) {
		return errInvalidNetwork
	}

	return s.add(ctx, prefix.Masked().String(), duration, userID, "manual network ban")
}

func (s *Repository) RemoveNetwork(ctx context.Context, prefix netip.Prefix) error {
	if !validNetwork(prefix) {
		return errInvalidNetwork
	}

	_, err := s.db.Delete(schema.IPBanTable).
		Where(schema.IPBanTableIPCol.Eq(prefix.Masked().String())).
		Executor().
		ExecContext(ctx)
	if err != nil {
		return fmt.Errorf("remove network ban: %w", err)
	}

	return nil
}
