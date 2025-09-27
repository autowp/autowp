package ban

import (
	"context"
	"errors"
	"net"
	"strings"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/jackc/pgtype"
	"github.com/sirupsen/logrus"
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
	reason = strings.TrimSpace(reason)
	upTo := time.Now().Add(duration)

	ct, err := s.db.Insert(schema.IPBanTable).Rows(goqu.Record{
		schema.IPBanTableIPColName:       ip.String(),
		schema.IPBanTableUntilColName:    upTo,
		schema.IPBanTableByUserIDColName: byUserID,
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
		logrus.Infof("%v was banned. Reason: %s", ip.String(), reason)
	}

	return nil
}

// Remove IP from list of banned.
func (s *Repository) Remove(ctx context.Context, ip net.IP) error {
	logrus.Info(ip.String() + ": unban")
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
			schema.IPBanTableIPCol.Eq(ip.String()),
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

	st := struct {
		PgInet   pgtype.Inet `db:"ip"`
		Until    time.Time   `db:"until"`
		Reason   string      `db:"reason"`
		ByUserID int64       `db:"by_user_id"`
	}{}

	success, err := s.db.Select(schema.IPBanTableIPCol, schema.IPBanTableUntilCol, schema.IPBanTableReasonCol,
		schema.IPBanTableByUserIDCol).
		From(schema.IPBanTable).
		Where(schema.IPBanTableIPCol.Eq(ip.String()), schema.IPBanTableUntilCol.Gte(goqu.Func("NOW"))).
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
	item.ByUserID = st.ByUserID

	if st.PgInet.IPNet != nil {
		item.IP = st.PgInet.IPNet.IP
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
