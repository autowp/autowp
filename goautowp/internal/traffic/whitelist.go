package traffic

import (
	"context"
	"errors"
	"net"
	"strings"
	"time"

	"github.com/autowp/goautowp/internal/logging"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/util"
	"github.com/doug-martin/goqu/v9"
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
	SourceID    string `db:"source_id"   json:"source_id"`
}

type whitelistItemRow struct {
	IP          schema.NullInet `db:"ip"`
	Description string          `db:"description"`
	SourceID    string          `db:"source_id"`
}

func (row whitelistItemRow) item() *WhitelistItem {
	item := &WhitelistItem{Description: row.Description, SourceID: row.SourceID}
	if row.IP.IsValid() {
		item.IP = net.IP(row.IP.Addr().AsSlice())
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
)

// Only these independently approved DNS domains grant automatic exemptions.
// Editing the observational source registry cannot change whitelist policy.
var trustedCrawlerSources = []Source{
	{ID: "googlebot", DNSSuffixes: []string{"googlebot.com"}},
	{ID: "google", DNSSuffixes: []string{"google.com"}},
	{ID: "bingbot", DNSSuffixes: []string{"search.msn.com"}},
	{ID: "yandex", DNSSuffixes: []string{"yandex.ru", "yandex.net", "yandex.com"}},
}

func matchCrawler(ctx context.Context, resolver crawlerResolver, ip net.IP) (bool, string, error) {
	matched, id, err := matchSource(ctx, resolver, ip, trustedCrawlerSources)
	if !matched {
		return false, "", err
	}

	return true, id + " verified reverse/forward DNS", err
}

func matchSource(ctx context.Context, resolver crawlerResolver, ip net.IP, sources []Source) (bool, string, error) {
	if err := ctx.Err(); err != nil {
		return false, "", err
	}

	if ip == nil || len(sources) == 0 {
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
		longestSuffix := 0

		for _, source := range sources {
			for _, domain := range source.DNSSuffixes {
				if len(domain) > longestSuffix && strings.HasSuffix(host, "."+domain) {
					crawler = source.ID
					longestSuffix = len(domain)
				}
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
				return true, crawler, nil
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
	return s.add(ctx, ip, desc, nil)
}

// Get whitelist item.
func (s *Whitelist) Get(ctx context.Context, ip net.IP) (*WhitelistItem, error) {
	var row whitelistItemRow

	success, err := s.db.Select(schema.IPWhitelistTableIPCol, schema.IPWhitelistTableDescriptionCol,
		goqu.COALESCE(schema.IPWhitelistTableSourceIDCol, "").As("source_id")).
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
		SQLSelect: s.db.Select(schema.IPWhitelistTableIPCol, schema.IPWhitelistTableDescriptionCol,
			goqu.COALESCE(schema.IPWhitelistTableSourceIDCol, "").As("source_id")).
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

func (s *Whitelist) addVerified(ctx context.Context, ip net.IP, description string) error {
	for _, source := range trustedCrawlerSources {
		if description != source.ID+" verified reverse/forward DNS" {
			continue
		}

		known, err := s.recordSource(ctx, ip, source.ID)
		if err != nil {
			return err
		}

		if known {
			return s.add(ctx, ip, description, source.ID)
		}
	}

	return s.Add(ctx, ip, description)
}

func (s *Whitelist) recordSource(ctx context.Context, ip net.IP, sourceID string) (bool, error) {
	result, err := s.db.Insert(schema.IPSourceTable).
		Cols(schema.IPSourceTableIPColName, schema.IPSourceTableSourceIDColName).
		FromQuery(s.db.From(schema.TrafficSourceTable).Select(goqu.V(ip.String()), schema.TrafficSourceTableIDCol).
			Where(schema.TrafficSourceTableIDCol.Eq(sourceID))).
		OnConflict(goqu.DoUpdate(schema.IPSourceTableIPColName,
			goqu.Record{
				schema.IPSourceTableSourceIDColName: schema.Excluded(schema.IPSourceTableSourceIDColName),
			}),
		).
		Executor().
		ExecContext(ctx)
	if err != nil {
		return false, err
	}

	rows, err := result.RowsAffected()

	return rows > 0, err
}

func (s *Whitelist) add(ctx context.Context, ip net.IP, desc string, sourceID interface{}) error {
	_, err := s.db.Insert(schema.IPWhitelistTable).Rows(goqu.Record{
		schema.IPWhitelistTableIPColName:          ip.String(),
		schema.IPWhitelistTableDescriptionColName: desc,
		schema.IPWhitelistTableSourceIDColName:    sourceID,
	}).OnConflict(goqu.DoUpdate(schema.IPWhitelistTableIPColName, goqu.Record{
		schema.IPWhitelistTableDescriptionColName: schema.Excluded(
			schema.IPWhitelistTableDescriptionColName,
		),
		schema.IPWhitelistTableSourceIDColName: schema.Excluded(schema.IPWhitelistTableSourceIDColName),
	})).Executor().ExecContext(ctx)

	return err
}
