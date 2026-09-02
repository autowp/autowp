package traffic

import (
	"context"
	"encoding/hex"
	"errors"
	"net"
	"strings"

	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

var ErrWhitelistItemNotFound = errors.New("whitelist item not found")

// Whitelist Main Object.
type Whitelist struct {
	db *goqu.Database
}

// WhitelistItem WhitelistItem.
type WhitelistItem struct {
	IP          net.IP `db:"ip"          json:"ip"`
	Description string `db:"description" json:"description"`
}

// NewWhitelist constructor.
func NewWhitelist(db *goqu.Database) (*Whitelist, error) {
	return &Whitelist{
		db: db,
	}, nil
}

// MatchAuto MatchAuto.
func (s *Whitelist) MatchAuto(ctx context.Context, ip net.IP) (bool, string) {
	ipText := ip.String()
	ipWithDashes := strings.ReplaceAll(ipText, ".", "-")

	msnHost := "msnbot-" + ipWithDashes + ".search.msn.com."
	yandexComHost := ipWithDashes + ".spider.yandex.com."
	googlebotHost := "crawl-" + ipWithDashes + ".googlebot.com."

	isIPv6 := len(ip) == net.IPv6len
	ip16 := ip.To16()

	yandexComIPv6Host := hex.EncodeToString(
		ip16[12:14],
	) + "-" + hex.EncodeToString(
		ip16[14:16],
	) + ".spider.yandex.com."

	hosts, err := net.DefaultResolver.LookupAddr(ctx, ipText)
	if err != nil {
		return false, ""
	}

	for _, host := range hosts {
		logging.Info(host + " ")

		if host == msnHost {
			return true, "msnbot autodetect"
		}

		if host == yandexComHost {
			return true, "yandex.com autodetect"
		}

		if host == googlebotHost {
			return true, "googlebot autodetect"
		}

		if isIPv6 && host == yandexComIPv6Host {
			return true, "yandex.com ipv6 autodetect"
		}
	}

	return false, ""
}

// Add IP to whitelist.
func (s *Whitelist) Add(ctx context.Context, ip net.IP, desc string) error {
	_, err := s.db.Insert(schema.IPWhitelistTable).Rows(goqu.Record{
		schema.IPWhitelistTableIPColName:          ip.String(),
		schema.IPWhitelistTableDescriptionColName: desc,
	}).OnConflict(goqu.DoUpdate(schema.IPWhitelistTableIPColName, goqu.Record{
		schema.IPWhitelistTableDescriptionColName: schema.Excluded(
			schema.IPWhitelistTableDescriptionColName,
		),
	})).Executor().ExecContext(ctx)

	return err
}

// Get whitelist item.
func (s *Whitelist) Get(ctx context.Context, ip net.IP) (*WhitelistItem, error) {
	var item WhitelistItem

	success, err := s.db.Select(schema.IPWhitelistTableIPCol, schema.IPWhitelistTableDescriptionCol).
		From(schema.IPWhitelistTable).
		Where(schema.IPWhitelistTableIPCol.Eq(ip.String())).
		Executor().
		ScanStructContext(ctx, &item)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, ErrWhitelistItemNotFound
	}

	return &item, nil
}

// List whitelist items.
func (s *Whitelist) List(ctx context.Context) ([]*WhitelistItem, error) {
	result := make([]*WhitelistItem, 0)

	err := s.db.Select(schema.IPWhitelistTableIPCol, schema.IPWhitelistTableDescriptionCol).
		From(schema.IPWhitelistTable).ScanStructsContext(ctx, &result)

	return result, err
}

// Exists whitelist already contains IP.
func (s *Whitelist) Exists(ctx context.Context, ip net.IP) (bool, error) {
	var exists bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.IPWhitelistTable).
		Where(schema.IPWhitelistTableIPCol.Eq(ip.String())).
		ScanValContext(ctx, &exists)
	if err != nil {
		return false, err
	}

	return success && exists, nil
}

// Remove IP from whitelist.
func (s *Whitelist) Remove(ctx context.Context, ip net.IP) error {
	_, err := s.db.Delete(schema.IPWhitelistTable).
		Where(schema.IPWhitelistTableIPCol.Eq(ip.String())).
		Executor().ExecContext(ctx)

	return err
}
