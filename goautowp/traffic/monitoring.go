package traffic

import (
	"context"
	"encoding/json"
	"net"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/jackc/pgtype"
	"github.com/sirupsen/logrus"
)

// Monitoring Main Object.
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
		logrus.Error(err)

		return err
	}

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
		case msg := <-msgs:
			if msg.ContentType != "application/json" {
				logrus.Errorf("unexpected mime `%s`", msg.ContentType)

				continue
			}

			var message MonitoringInputMessage

			err = json.Unmarshal(msg.Body, &message)
			if err != nil {
				logrus.Errorf("failed to parse json `%v`: %s", err, msg.Body)

				continue
			}

			err = s.Add(ctx, message.IP, message.Timestamp)
			if err != nil {
				logrus.Error(err.Error())
			}

		case <-quitChan:
			quit = true
		}
	}

	logrus.Info("Disconnecting RabbitMQ")

	return conn.Close()
}

// Add item to Monitoring.
func (s *Monitoring) Add(ctx context.Context, ip net.IP, timestamp time.Time) error {
	_, err := s.db.Insert(schema.IPMonitoringTable).Rows(
		goqu.Record{
			schema.IPMonitoringTableDayDateColName: timestamp,
			schema.IPMonitoringTableHourColName: goqu.Func(
				"EXTRACT",
				goqu.L("HOUR FROM ?::timestamptz", timestamp),
			),
			schema.IPMonitoringTableTenminuteColName: goqu.L(
				"FLOOR(EXTRACT(MINUTE FROM ?::timestamptz)/10)",
				timestamp,
			),
			schema.IPMonitoringTableMinuteColName: goqu.Func(
				"EXTRACT",
				goqu.L("MINUTE FROM ?::timestamptz", timestamp),
			),
			schema.IPMonitoringTableIPColName:    ip.String(),
			schema.IPMonitoringTableCountColName: 1,
		}).
		OnConflict(
			goqu.DoUpdate(
				"ip,day_date,hour,tenminute,minute",
				goqu.C(schema.IPMonitoringTableCountColName).Set(
					goqu.L(schema.IPMonitoringTableName+"."+schema.IPMonitoringTableCountColName+"+1"),
				),
			),
		).Executor().ExecContext(ctx)

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
	logrus.Info(ip.String() + ": clear monitoring")
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
		Where(schema.IPMonitoringTableDayDateCol.Eq(goqu.L("CURRENT_DATE"))).
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
