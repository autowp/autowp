package traffic

import (
	"context"
	"encoding/json"
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

// Monitoring Main Object.
var errMonitoringChannelClosed = errors.New("monitoring AMQP delivery channel closed")

type Monitoring struct {
	db *goqu.Database
}

// MonitoringInputMessage InputMessage.
type MonitoringInputMessage struct {
	IP        net.IP    `json:"ip"`
	Timestamp time.Time `json:"timestamp"`
}

// ListOfTopItem ListOfTopItem.
type ListOfTopItem struct {
	IP    net.IP `json:"ip"`
	Count int    `json:"count"`
}

// NewMonitoring constructor.
func NewMonitoring(db *goqu.Database) (*Monitoring, error) {
	s := &Monitoring{
		db: db,
	}

	return s, nil
}

// Listen for incoming messages.
func (s *Monitoring) Listen(
	ctx context.Context,
	url string,
	queue string,
	quitChan chan bool,
) error {
	conn, err := util.ConnectRabbitMQ(url)
	if err != nil {
		return err
	}

	defer util.Close(conn)

	ch, err := conn.Channel()
	if err != nil {
		return err
	}
	defer util.Close(ch)

	inQ, err := ch.QueueDeclare(
		queue, // name
		false, // durable
		false, // delete when unused
		false, // exclusive
		false, // no-wait
		nil,   // arguments
	)
	if err != nil {
		return err
	}

	msgs, err := ch.Consume(
		inQ.Name, // queue
		"",       // consumer
		true,     // auto-ack
		false,    // exclusive
		false,    // no-local
		false,    // no-wait
		nil,      // args
	)
	if err != nil {
		return err
	}

	quit := false
	for !quit {
		select {
		case <-ctx.Done():
			return ctx.Err()
		case msg, ok := <-msgs:
			if !ok {
				return errMonitoringChannelClosed
			}

			if msg.ContentType != "application/json" {
				logging.Errorf("unexpected mime `%s`", msg.ContentType)

				continue
			}

			var message MonitoringInputMessage

			err = json.Unmarshal(msg.Body, &message)
			if err != nil {
				logging.Errorf("failed to parse json `%v`: %s", err, msg.Body)

				continue
			}

			err = s.Add(ctx, message.IP, message.Timestamp)
			if err != nil {
				logging.Error(err.Error())
			}

		case <-quitChan:
			quit = true
		}
	}

	logging.Info("Disconnecting RabbitMQ")

	return nil
}

// Add item to Monitoring.
func (s *Monitoring) Add(ctx context.Context, ip net.IP, timestamp time.Time) error {
	return s.AddCounts(ctx, []MonitoringCount{{IP: ip.String(), Timestamp: timestamp, Count: 1}})
}

// MonitoringCount is an aggregated minute of requests from one address.
type MonitoringCount struct {
	IP        string
	Timestamp time.Time
	Count     int64
}

// AddCounts persists a batch using the same counters as the legacy AMQP consumer.
func (s *Monitoring) AddCounts(ctx context.Context, counts []MonitoringCount) error {
	if len(counts) == 0 {
		return nil
	}

	// Filter against the current whitelist inside the INSERT, including batches queued
	// before an address was whitelisted and messages from the legacy AMQP consumer.
	const fieldsPerCount = 3

	rows := make([]string, 0, len(counts))

	values := make([]interface{}, 0, len(counts)*fieldsPerCount)
	for _, count := range counts {
		rows = append(rows, "(?::inet, ?::timestamptz, ?::integer)")
		values = append(values, count.IP, count.Timestamp, count.Count)
	}

	incoming := goqu.T("incoming")
	seenAt := incoming.Col("seen_at")
	selectCounts := s.db.From(goqu.L(
		"(VALUES "+strings.Join(rows, ",")+") AS incoming(ip, seen_at, count)", values...,
	)).Select(
		incoming.Col(schema.IPMonitoringTableIPColName),
		goqu.Cast(seenAt, "date"),
		goqu.L("EXTRACT(HOUR FROM ?)", seenAt),
		goqu.L("FLOOR(EXTRACT(MINUTE FROM ?)/10)", seenAt),
		goqu.L("EXTRACT(MINUTE FROM ?)", seenAt),
		incoming.Col(schema.IPMonitoringTableCountColName),
	).Where(goqu.L("NOT EXISTS ?", s.db.From(schema.IPWhitelistTable).
		Select(goqu.V(1)).Where(schema.IPWhitelistTableIPCol.Eq(incoming.Col(schema.IPMonitoringTableIPColName)))))

	_, err := s.db.Insert(schema.IPMonitoringTable).Cols(
		schema.IPMonitoringTableIPColName,
		schema.IPMonitoringTableDayDateColName,
		schema.IPMonitoringTableHourColName,
		schema.IPMonitoringTableTenminuteColName,
		schema.IPMonitoringTableMinuteColName,
		schema.IPMonitoringTableCountColName,
	).FromQuery(selectCounts).OnConflict(goqu.DoUpdate(
		"ip,day_date,hour,tenminute,minute",
		goqu.Record{schema.IPMonitoringTableCountColName: goqu.L("ip_monitoring.count + EXCLUDED.count")},
	)).Executor().ExecContext(ctx)

	return err
}

// GC Garbage Collect.
func (s *Monitoring) GC(ctx context.Context) (int64, error) {
	ct, err := s.db.Delete(schema.IPMonitoringTable).
		Where(schema.IPMonitoringTableDayDateCol.Lt(goqu.L("CURRENT_DATE"))).
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
func (s *Monitoring) Clear(ctx context.Context) error {
	_, err := s.db.Delete(schema.IPMonitoringTable).Executor().ExecContext(ctx)

	return err
}

// ClearIP removes all data collected for IP.
func (s *Monitoring) ClearIP(ctx context.Context, ip net.IP) error {
	logging.Info(ip.String() + ": clear monitoring")
	_, err := s.db.Delete(schema.IPMonitoringTable).
		Where(schema.IPMonitoringTableIPCol.Eq(ip.String())).
		Executor().ExecContext(ctx)

	return err
}

// ListOfTop ListOfTop.
func (s *Monitoring) ListOfTop(ctx context.Context, limit uint) ([]ListOfTopItem, error) {
	var rows []struct {
		IP    pgtype.Inet `db:"ip"`
		Count int         `db:"c"`
	}

	err := s.db.Select(schema.IPMonitoringTableIPCol, goqu.SUM(schema.IPMonitoringTableCountCol).As("c")).
		From(schema.IPMonitoringTable).
		Where(schema.IPMonitoringTableDayDateCol.Eq(goqu.L("CURRENT_DATE"))).
		GroupBy(schema.IPMonitoringTableIPCol).
		Order(goqu.I("c").Desc()).
		Limit(limit).
		ScanStructsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	result := make([]ListOfTopItem, 0, len(rows))

	for _, row := range rows {
		item := ListOfTopItem{
			Count: row.Count,
		}

		if row.IP.IPNet != nil {
			item.IP = row.IP.IPNet.IP
		}

		result = append(result, item)
	}

	return result, nil
}

// ListByBanProfile ListByBanProfile.
func (s *Monitoring) ListByBanProfile(
	ctx context.Context,
	profile AutobanProfile,
) ([]net.IP, error) {
	group := append([]interface{}{schema.IPMonitoringTableIPCol}, profile.Group...)

	const numberOfRecordsToScanForAutoban = 1000

	var rows []pgtype.Inet

	err := s.db.Select(schema.IPMonitoringTableIPCol).
		From(schema.IPMonitoringTable).
		Where(
			schema.IPMonitoringTableDayDateCol.Eq(goqu.L("CURRENT_DATE")),
			goqu.L("NOT EXISTS ?", s.db.From(schema.IPWhitelistTable).Select(goqu.V(1)).
				Where(schema.IPWhitelistTableIPCol.Eq(schema.IPMonitoringTableIPCol))),
		).
		GroupBy(group...).
		Having(goqu.SUM(schema.IPMonitoringTableCountCol).Gt(profile.Limit)).
		Limit(numberOfRecordsToScanForAutoban).
		ScanValsContext(ctx, &rows)
	if err != nil {
		return nil, err
	}

	result := make([]net.IP, 0, len(rows))

	for _, row := range rows {
		if row.IPNet != nil {
			result = append(result, row.IPNet.IP)
		}
	}

	return result, nil
}

// ExistsIP ban list already contains IP.
func (s *Monitoring) ExistsIP(ctx context.Context, ip net.IP) (bool, error) {
	var exists bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.IPMonitoringTable).
		Where(schema.IPMonitoringTableIPCol.Eq(ip.String())).
		Limit(1).
		ScanValContext(ctx, &exists)
	if err != nil {
		return false, err
	}

	return success && exists, nil
}
