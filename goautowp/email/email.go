package email

import (
	"strings"

	"github.com/autowp/goautowp/config"
	"github.com/sirupsen/logrus"
	"gopkg.in/gomail.v2"
)

// Sender Sender.
type Sender interface {
	Send(from string, to []string, subject, body, replyTo string) error
}

type SMTPSender struct {
	Config config.SMTPConfig
}

type MockSender struct {
	Body string
}

func (s *SMTPSender) Send(from string, to []string, subject, body, replyTo string) error {
	msg := gomail.NewMessage()
	msg.SetHeader("From", from)
	msg.SetHeader("To", to...)
	msg.SetHeader("Subject", subject)
	msg.SetBody("text/plain", body)
	msg.SetHeader("Reply-To", replyTo)

	d := gomail.NewDialer(s.Config.Hostname, s.Config.Port, s.Config.Username, s.Config.Password)

	return d.DialAndSend(msg)
}

func (s *MockSender) Send(from string, to []string, subject, body, _ string) error {
	logrus.Debugf("Subject: %s\nFrom: %s\nTo: %s\n%s", subject, from, strings.Join(to, ", "), body)
	s.Body = body

	return nil
}
