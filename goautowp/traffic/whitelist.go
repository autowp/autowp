package traffic

import (
	"context"
	"errors"
	"net"
	"strings"
	"time"

	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/jackc/pgtype"
)

var ErrWhitelistItemNotFound = errors.New("whitelist item not found")

const whitelistItemsPerPage = 50

// Whitelist Main Object.
type Whitelist struct {
	db *goqu.Database
}

// WhitelistItem WhitelistItem.
type WhitelistItem struct {
	IP          net.IP `db:"ip"          json:"ip"`
	Description string `db:"description" json:"description"`
}

type whitelistItemRow struct {
	IP          pgtype.Inet `db:"ip"`
	Description string      `db:"description"`
}

func (row whitelistItemRow) item() *WhitelistItem {
	item := &WhitelistItem{Description: row.Description}
	if row.IP.IPNet != nil {
		item.IP = row.IP.IPNet.IP
	}

	return item
}

// NewWhitelist constructor.
func NewWhitelist(db *goqu.Database) (*Whitelist, error) {
	return &Whitelist{
		db: db,
	}, nil
}

// MatchAuto MatchAuto.
func (s *Whitelist) MatchAuto(ctx context.Context, ip net.IP) (bool, string) {
	match, desc, _ := matchCrawler(ctx, net.DefaultResolver, ip)

	return match, desc
}

type crawlerResolver interface {
	LookupAddr(ctx context.Context, addr string) ([]string, error)
	LookupIPAddr(ctx context.Context, host string) ([]net.IPAddr, error)
}

// A PTR alone is controlled by the address owner. The crawler's own DNS must
// resolve the approved name back to that address before granting an exemption.
const (
	crawlerLookupTimeout = 3 * time.Second
	crawlerYandex        = "yandex"
)

func matchCrawler(ctx context.Context, resolver crawlerResolver, ip net.IP) (bool, string, error) {
	if err := ctx.Err(); err != nil {
		return false, "", err
	}

	if ip == nil {
		return false, "", nil
	}

	lookupCtx, cancel := context.WithTimeout(ctx, crawlerLookupTimeout)
	defer cancel()

	hosts, err := resolver.LookupAddr(lookupCtx, ip.String())
	if err != nil {
		return false, "", crawlerLookupError(ctx, ip, err)
	}

	for _, host := range hosts {
		host = strings.ToLower(strings.TrimSuffix(host, "."))
		crawler := ""

		for domain, name := range map[string]string{
			"googlebot.com": "googlebot", "google.com": "google",
			"search.msn.com": "bingbot", "yandex.ru": crawlerYandex, "yandex.net": crawlerYandex, "yandex.com": crawlerYandex,
		} {
			if strings.HasSuffix(host, "."+domain) {
				crawler = name

				break
			}
		}

		if crawler == "" {
			continue
		}

		addresses, err := resolver.LookupIPAddr(lookupCtx, host)
		if err != nil {
			return false, "", crawlerLookupError(ctx, ip, err)
		}

		for _, address := range addresses {
			if address.IP.Equal(ip) {
				return true, crawler + " verified reverse/forward DNS", nil
			}
		}
	}

	return false, "", nil
}

// A failed DNS check must not grant an exemption or stop checks for other IPs.
// The lookup's own timeout is recoverable; cancellation of the caller is not.
func crawlerLookupError(ctx context.Context, ip net.IP, err error) error {
	if contextErr := ctx.Err(); contextErr != nil {
		return contextErr
	}

	var dnsErr *net.DNSError
	if !errors.As(err, &dnsErr) {
		return err
	}

	if !dnsErr.IsNotFound {
		logging.Warnf("Skipping crawler verification for IP %s: %v", ip, err)
	}

	return nil
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
	var row whitelistItemRow

	success, err := s.db.Select(schema.IPWhitelistTableIPCol, schema.IPWhitelistTableDescriptionCol).
		From(schema.IPWhitelistTable).
		Where(schema.IPWhitelistTableIPCol.Eq(ip.String())).
		Executor().
		ScanStructContext(ctx, &row)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, ErrWhitelistItemNotFound
	}

	return row.item(), nil
}

// List whitelist items.
func (s *Whitelist) List(ctx context.Context, page int32) ([]*WhitelistItem, *util.Pages, error) {
	var rows []whitelistItemRow

	paginator := util.Paginator{
		SQLSelect: s.db.Select(schema.IPWhitelistTableIPCol, schema.IPWhitelistTableDescriptionCol).
			From(schema.IPWhitelistTable).
			Order(schema.IPWhitelistTableIPCol.Asc()),
		CurrentPageNumber: page,
		ItemCountPerPage:  whitelistItemsPerPage,
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	if pages.TotalItemCount == 0 {
		pages.Current = 1

		return []*WhitelistItem{}, pages, nil
	}

	sqlSelect, err := paginator.GetCurrentItems(ctx)
	if err != nil {
		return nil, nil, err
	}

	err = sqlSelect.ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, nil, err
	}

	result := make([]*WhitelistItem, 0, len(rows))
	for _, row := range rows {
		result = append(result, row.item())
	}

	return result, pages, nil
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

// IPSet provides a snapshot for request monitoring without a database lookup per request.
func (s *Whitelist) IPSet(ctx context.Context) (map[string]struct{}, error) {
	var addresses []string
	if err := s.db.Select(goqu.Func("HOST", schema.IPWhitelistTableIPCol)).
		From(schema.IPWhitelistTable).ScanValsContext(ctx, &addresses); err != nil {
		return nil, err
	}

	result := make(map[string]struct{}, len(addresses))
	for _, address := range addresses {
		result[net.ParseIP(address).String()] = struct{}{}
	}

	return result, nil
}
