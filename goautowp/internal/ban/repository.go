package ban

import (
	"context"
	"database/sql"
	"errors"
	"net"
	"net/netip"
	"strings"
	"time"

	"github.com/autowp/goautowp/internal/logging"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
)

var (
	ErrBanItemNotFound         = errors.New("ban item not found")
	errDatabaseConnectionIsNil = errors.New("database connection is nil")
)

// Item Item.
type Item struct {
	IP       net.IP    `json:"ip"`
	Until    time.Time `json:"up_to"`
	ByUserID int64     `json:"by_user_id"`
	Reason   string    `json:"reason"`
	Network  bool      `json:"network"`
}

// Repository Main Object.
type Repository struct {
	db *goqu.Database
}

// NewRepository constructor.
func NewRepository(db *goqu.Database) (*Repository, error) {
	if db == nil {
		return nil, errDatabaseConnectionIsNil
	}

	s := &Repository{
		db: db,
	}

	return s, nil
}

// Add IP to list of banned.
func (s *Repository) Add(
	ctx context.Context,
	ip net.IP,
	duration time.Duration,
	byUserID int64,
	reason string,
) error {
	return s.add(ctx, ip.String(), duration, byUserID, reason)
}

// AddNetwork blocks an IPv4 /24 or IPv6 /64, including addresses not seen yet.
func (s *Repository) AddNetwork(ctx context.Context, prefix netip.Prefix, duration time.Duration, reason string) error {
	if !validNetwork(prefix) {
		return errInvalidNetwork
	}

	return s.add(ctx, prefix.Masked().String(), duration, 0, reason)
}

// Remove IP from list of banned.
func (s *Repository) Remove(ctx context.Context, ip net.IP) error {
	logging.Info(ip.String() + ": unban")
	_, err := s.db.Delete(schema.IPBanTable).
		Where(schema.IPBanTableIPCol.Eq(ip.String())).
		Executor().
		ExecContext(ctx)

	return err
}

// Exists ban list already contains IP.
func (s *Repository) Exists(ctx context.Context, ip net.IP) (bool, error) {
	var exists bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.IPBanTable).
		Where(
			s.matchAddress(ip),
			schema.IPBanTableUntilCol.Gte(goqu.Func("NOW")),
		).
		ScanValContext(ctx, &exists)
	if err != nil {
		return false, err
	}

	return success && exists, nil
}

// Get ban info.
func (s *Repository) Get(ctx context.Context, ip net.IP) (*Item, error) {
	var item Item

	st := schema.IPBanRow{}

	success, err := s.db.Select(schema.IPBanTableIPCol, schema.IPBanTableUntilCol, schema.IPBanTableReasonCol,
		schema.IPBanTableByUserIDCol).
		From(schema.IPBanTable).
		Where(s.matchAddress(ip), schema.IPBanTableUntilCol.Gte(goqu.Func("NOW"))).
		Order(schema.IPBanTableUntilCol.Desc()).
		Limit(1).
		Executor().
		ScanStructContext(ctx, &st)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, ErrBanItemNotFound
	}

	item.Until = st.Until
	item.Reason = st.Reason

	if st.ByUserID.Valid {
		item.ByUserID = st.ByUserID.Int64
	}

	if st.PgInet.IsValid() {
		item.IP = net.IP(st.PgInet.Addr().AsSlice())
		item.Network = st.PgInet.Bits() < st.PgInet.Addr().BitLen()
	}

	return &item, nil
}

// GC Garbage Collect.
func (s *Repository) GC(ctx context.Context) (int64, error) {
	ct, err := s.db.Delete(schema.IPBanTable).
		Where(schema.IPBanTableUntilCol.Lt(goqu.Func("NOW"))).
		Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	affected, err := ct.RowsAffected()
	if err != nil {
		return 0, err
	}

	return affected, nil
}

// Clear removes all collected data.
func (s *Repository) Clear(ctx context.Context) error {
	_, err := s.db.Delete(schema.IPBanTable).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) add(
	ctx context.Context,
	address string,
	duration time.Duration,
	byUserID int64,
	reason string,
) error {
	reason = strings.TrimSpace(reason)
	upTo := time.Now().Add(duration)

	ct, err := s.db.Insert(schema.IPBanTable).Rows(goqu.Record{
		schema.IPBanTableIPColName:       address,
		schema.IPBanTableUntilColName:    upTo,
		schema.IPBanTableByUserIDColName: sql.NullInt64{Int64: byUserID, Valid: byUserID > 0},
		schema.IPBanTableReasonColName:   reason,
	}).OnConflict(goqu.DoUpdate(schema.IPBanTableIPColName, goqu.Record{
		schema.IPBanTableUntilColName:    schema.Excluded(schema.IPBanTableUntilColName),
		schema.IPBanTableByUserIDColName: schema.Excluded(schema.IPBanTableByUserIDColName),
		schema.IPBanTableReasonColName:   schema.Excluded(schema.IPBanTableReasonColName),
	})).Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	affected, err := ct.RowsAffected()
	if err != nil {
		return err
	}

	if affected == 1 {
		logging.Infof("%s was banned. Reason: %s", address, reason)
	}

	return nil
}
