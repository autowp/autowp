package attrsamqp

import (
	"context"
	"encoding/json"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/util"
	"github.com/sirupsen/logrus"
)

type UpdateValuesMessage struct {
	ItemID int64  `json:"item_id"`
	Type   string `json:"type"`
}

type AttrsAMQP struct {
	repository *attrs.Repository
}

func NewAttrsAMQP(repository *attrs.Repository) *AttrsAMQP {
	return &AttrsAMQP{
		repository: repository,
	}
}

// ListenUpdateValues for incoming messages.
func (s *AttrsAMQP) ListenUpdateValues(
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

			var message UpdateValuesMessage

			err = json.Unmarshal(msg.Body, &message)
			if err != nil {
				logrus.Errorf("failed to parse json `%v`: %s", err, msg.Body)

				continue
			}

			switch message.Type {
			case "actual":
				logrus.Infof("UpdateActualValues(%d)", message.ItemID)

				err = s.repository.UpdateActualValues(ctx, message.ItemID)
				if err != nil {
					logrus.Error(err.Error())
				}
			case "inherited":
				logrus.Infof("UpdateInheritedValues(%d)", message.ItemID)

				err = s.repository.UpdateInheritedValues(ctx, message.ItemID)
				if err != nil {
					logrus.Error(err.Error())
				}
			default:
				logrus.Warnf("unknown UpdateValuesMessage.Type = `%s`", message.Type)
			}

		case <-quitChan:
			quit = true
		}
	}

	logrus.Info("Disconnecting RabbitMQ")

	return conn.Close()
}
