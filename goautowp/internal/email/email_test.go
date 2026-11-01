package email_test

import (
	"bytes"
	"crypto/tls"
	"encoding/base64"
	"encoding/pem"
	"errors"
	"fmt"
	"io"
	"mime"
	"mime/quotedprintable"
	"net"
	"net/http"
	"net/http/httptest"
	"net/mail"
	"net/textproto"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/email"
	"github.com/stretchr/testify/require"
)

var errUnexpectedSMTPCommand = errors.New("unexpected SMTP command")

//nolint:paralleltest // SSL_CERT_FILE configures process-wide certificate trust and requires serial tests.
func TestSMTPSenderDelivery(t *testing.T) {
	tlsConfig := smtpTLSConfig(t)

	tests := []struct {
		name         string
		ssl          bool
		authenticate bool
	}{
		{name: "STARTTLS"},
		{name: "STARTTLS with authentication", authenticate: true},
		{name: "implicit TLS", ssl: true},
		{name: "implicit TLS with authentication", ssl: true, authenticate: true},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			cfg, received := startSMTPServer(t, smtpServerOptions{
				tlsConfig: tlsConfig, authenticate: tt.authenticate, implicitTLS: tt.ssl,
			})
			sender := email.SMTPSender{Config: cfg}
			subject := "Обратная связь"
			body := "Сообщение с сайта\r\n.Строка с точкой\r\n"

			replyTo := ""
			if tt.authenticate {
				replyTo = "Автор <author@example.com>"
			}

			err := sender.Send(
				"Робот <robot@example.com>",
				[]string{"First <first@example.com>", "second@example.com"},
				subject,
				body,
				replyTo,
			)
			require.NoError(t, err)

			result := <-received
			require.NoError(t, result.err)
			require.True(t, result.tls)
			require.Equal(t, "<robot@example.com>", result.from)
			require.Equal(t, []string{"<first@example.com>", "<second@example.com>"}, result.to)

			if tt.authenticate {
				// RFC 2195's CRAM-MD5 example verifies both the username and password.
				require.Equal(t, "tim b913a602c7eda7a495b4e6e7334d3890", result.auth)
			} else {
				require.Empty(t, result.auth)
			}

			message, err := mail.ReadMessage(bytes.NewReader(result.data))
			require.NoError(t, err)

			decoder := mime.WordDecoder{}
			decodedSubject, err := decoder.DecodeHeader(message.Header.Get("Subject"))
			require.NoError(t, err)
			require.Equal(t, subject, decodedSubject)

			from, err := message.Header.AddressList("From")
			require.NoError(t, err)
			require.Equal(t, []*mail.Address{{Name: "Робот", Address: "robot@example.com"}}, from)

			if replyTo == "" {
				require.Empty(t, message.Header.Get("Reply-To"))
			} else {
				addresses, err := message.Header.AddressList("Reply-To")
				require.NoError(t, err)
				require.Equal(t, []*mail.Address{{Name: "Автор", Address: "author@example.com"}}, addresses)
			}

			contentType, params, err := mime.ParseMediaType(message.Header.Get("Content-Type"))
			require.NoError(t, err)
			require.Equal(t, "text/plain", contentType)
			require.Equal(t, "utf-8", strings.ToLower(params["charset"]))
			require.Equal(t, "quoted-printable", message.Header.Get("Content-Transfer-Encoding"))
			decodedBody, err := io.ReadAll(quotedprintable.NewReader(message.Body))
			require.NoError(t, err)
			require.Equal(
				t,
				strings.ReplaceAll(body, "\r\n", "\n"),
				strings.ReplaceAll(string(decodedBody), "\r\n", "\n"),
			)
		})
	}
}

func TestSMTPSenderRejectsInvalidAddresses(t *testing.T) {
	t.Parallel()

	for _, field := range []string{"sender", "recipients", "reply-to"} {
		t.Run(field, func(t *testing.T) {
			t.Parallel()

			from, to, replyTo := "robot@example.com", "recipient@example.com", "author@example.com"

			switch field {
			case "sender":
				from = "invalid"
			case "recipients":
				to = "invalid"
			case "reply-to":
				replyTo = "invalid"
			}

			sender := email.SMTPSender{}
			err := sender.Send(from, []string{to}, "Subject", "Body", replyTo)
			require.ErrorContains(t, err, "set email "+field)
		})
	}
}

//nolint:paralleltest // SSL_CERT_FILE configures process-wide certificate trust and requires serial tests.
func TestSMTPSenderReportsRejectedRecipient(t *testing.T) {
	cfg, received := startSMTPServer(t, smtpServerOptions{tlsConfig: smtpTLSConfig(t), rejectRecipient: true})
	sender := email.SMTPSender{Config: cfg}
	err := sender.Send("robot@example.com", []string{"recipient@example.com"}, "Subject", "Body", "")
	require.ErrorContains(t, err, "recipient rejected")

	result := <-received
	require.NoError(t, result.err)
	require.Empty(t, result.data)
}

func TestSMTPSenderRequiresTLS(t *testing.T) {
	t.Parallel()

	cfg, received := startSMTPServer(t, smtpServerOptions{})
	sender := email.SMTPSender{Config: cfg}
	err := sender.Send("robot@example.com", []string{"recipient@example.com"}, "Subject", "Body", "")
	require.ErrorContains(t, err, "does not support STARTTLS")

	result := <-received
	require.NoError(t, result.err)
	require.Empty(t, result.from)
	require.Empty(t, result.data)
}

func smtpTLSConfig(t *testing.T) *tls.Config {
	t.Helper()

	server := httptest.NewTLSServer(http.NotFoundHandler())
	defer server.Close()

	tlsConfig := server.TLS.Clone()
	certificate := pem.EncodeToMemory(&pem.Block{
		Type: "CERTIFICATE", Bytes: tlsConfig.Certificates[0].Certificate[0],
	})
	path := filepath.Join(t.TempDir(), "smtp-ca.pem")
	require.NoError(t, os.WriteFile(path, certificate, 0o600))
	t.Setenv("SSL_CERT_FILE", path)

	return tlsConfig
}

type smtpResult struct {
	from string
	to   []string
	auth string
	data []byte
	err  error
	tls  bool
}

type smtpServerOptions struct {
	tlsConfig       *tls.Config
	authenticate    bool
	rejectRecipient bool
	implicitTLS     bool
}

func startSMTPServer(t *testing.T, options smtpServerOptions) (config.SMTPConfig, <-chan smtpResult) {
	t.Helper()

	listenConfig := net.ListenConfig{}
	listener, err := listenConfig.Listen(t.Context(), "tcp", "127.0.0.1:0")
	require.NoError(t, err)
	host, portString, err := net.SplitHostPort(listener.Addr().String())
	require.NoError(t, err)
	port, err := strconv.Atoi(portString)
	require.NoError(t, err)

	received := make(chan smtpResult, 1)

	stopped := make(chan struct{})
	go func() {
		defer close(stopped)

		connection, err := listener.Accept()
		if err != nil {
			received <- smtpResult{err: err}

			return
		}

		result := serveSMTP(connection, options)

		result.err = errors.Join(result.err, connection.Close())
		received <- result
	}()

	t.Cleanup(func() {
		require.NoError(t, listener.Close())
		<-stopped
	})

	cfg := config.SMTPConfig{Hostname: host, Port: port, SSL: options.implicitTLS}
	if options.authenticate {
		cfg.Username = "tim"
		cfg.Password = "tanstaaftanstaaf"
	}

	return cfg, received
}

func serveSMTP(connection net.Conn, options smtpServerOptions) smtpResult {
	result := smtpResult{to: []string{}}

	result.err = connection.SetDeadline(time.Now().Add(5 * time.Second))
	if result.err != nil {
		return result
	}

	if options.implicitTLS {
		connection = tls.Server(connection, options.tlsConfig)
		result.tls = true
	}

	protocol := textproto.NewConn(connection)

	result.err = protocol.PrintfLine("220 localhost ESMTP")
	for result.err == nil {
		var line string

		line, result.err = protocol.ReadLine()
		if result.err != nil {
			break
		}

		response := "250 OK"

		switch {
		case strings.HasPrefix(line, "EHLO "):
			if options.tlsConfig != nil && !result.tls {
				response = "250-localhost\r\n250 STARTTLS"
			} else if options.authenticate {
				response = "250-localhost\r\n250 AUTH CRAM-MD5"
			}

			if options.tlsConfig == nil {
				result.err = protocol.PrintfLine("%s", response)

				return result
			}
		case line == "STARTTLS":
			result.err = protocol.PrintfLine("220 ready for TLS")
			protocol = textproto.NewConn(tls.Server(connection, options.tlsConfig))
			result.tls = true

			continue
		case line == "AUTH CRAM-MD5":
			challenge := base64.StdEncoding.EncodeToString([]byte("<1896.697170952@postoffice.reston.mci.net>"))

			result.err = protocol.PrintfLine("334 %s", challenge)
			if result.err != nil {
				return result
			}

			line, result.err = protocol.ReadLine()
			if result.err != nil {
				return result
			}

			var decoded []byte

			decoded, result.err = base64.StdEncoding.DecodeString(line)
			result.auth = string(decoded)
			response = "235 authenticated"
		case strings.HasPrefix(line, "MAIL FROM:"):
			result.from = strings.TrimPrefix(line, "MAIL FROM:")
		case strings.HasPrefix(line, "RCPT TO:"):
			result.to = append(result.to, strings.TrimPrefix(line, "RCPT TO:"))

			if options.rejectRecipient {
				response = "550 recipient rejected"
			}
		case line == "DATA":
			result.err = protocol.PrintfLine("354 send message")
			if result.err != nil {
				return result
			}

			result.data, result.err = protocol.ReadDotBytes()
		case line == "RSET", line == "NOOP":
		case line == "QUIT":
			result.err = protocol.PrintfLine("221 bye")

			return result
		default:
			result.err = fmt.Errorf("%w: %q", errUnexpectedSMTPCommand, line)

			return result
		}

		if result.err == nil {
			result.err = protocol.PrintfLine("%s", response)
		}
	}

	return result
}
