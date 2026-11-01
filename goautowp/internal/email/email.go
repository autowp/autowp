package email

import (
	"fmt"
	"strings"

	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/logging"
	"github.com/wneessen/go-mail"
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
	msg := mail.NewMsg()
	if err := msg.From(from); err != nil {
		return fmt.Errorf("set email sender: %w", err)
	}

	if err := msg.To(to...); err != nil {
		return fmt.Errorf("set email recipients: %w", err)
	}

	if replyTo != "" {
		if err := msg.ReplyTo(replyTo); err != nil {
			return fmt.Errorf("set email reply-to: %w", err)
		}
	}

	msg.Subject(subject)
	msg.SetBodyString(mail.TypeTextPlain, body)

	options := []mail.Option{mail.WithPort(s.Config.Port)}
	if s.Config.SSL {
		options = append(options, mail.WithSSL())
	}

	if s.Config.Username != "" {
		options = append(options,
			mail.WithSMTPAuth(mail.SMTPAuthAutoDiscover),
			mail.WithUsername(s.Config.Username),
			mail.WithPassword(s.Config.Password),
		)
	}

	client, err := mail.NewClient(s.Config.Hostname, options...)
	if err != nil {
		return fmt.Errorf("create SMTP client: %w", err)
	}

	if err = client.DialAndSend(msg); err != nil {
		return fmt.Errorf("send email: %w", err)
	}

	return nil
}

func (s *MockSender) Send(from string, to []string, subject, body, _ string) error {
	logging.Debugf("Subject: %s\nFrom: %s\nTo: %s\n%s", subject, from, strings.Join(to, ", "), body)
	s.Body = body

	return nil
}
