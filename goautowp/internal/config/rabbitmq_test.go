package config

import (
	"net/url"
	"os"
	"testing"

	"github.com/stretchr/testify/require"
)

//nolint:paralleltest // Subtests modify process-wide environment variables.
func TestRabbitMQURL(t *testing.T) {
	t.Run("keeps a literal connection string", func(t *testing.T) {
		uri := "amqp://guest:guest@localhost:5672/" //nolint:gosec // Synthetic test credentials.
		actual, err := rabbitMQURL(uri)
		require.NoError(t, err)
		require.Equal(t, uri, actual)
	})

	t.Run("substitutes and escapes the secret password", func(t *testing.T) {
		password := "secret@:/?#%+ space\n" //nolint:gosec // Synthetic password for escaping checks.
		t.Setenv("RABBITMQ_PASSWORD", password)

		actual, err := rabbitMQURL(
			"amqps://admin%40%2Fspace%20user:$RABBITMQ_PASSWORD@broker:5671/vhost?heartbeat=30",
		)
		require.NoError(t, err)

		parsed, err := url.Parse(actual)
		require.NoError(t, err)
		require.Equal(t, "admin@/space user", parsed.User.Username())
		decoded, present := parsed.User.Password()
		require.True(t, present)
		require.Equal(t, password, decoded)
		require.Equal(t, "amqps", parsed.Scheme)
		require.Equal(t, "broker:5671", parsed.Host)
		require.Equal(t, "/vhost", parsed.Path)
		require.Equal(t, "heartbeat=30", parsed.RawQuery)
	})

	t.Run("missing secret fails", func(t *testing.T) {
		t.Setenv("RABBITMQ_PASSWORD", "")
		require.NoError(t, os.Unsetenv("RABBITMQ_PASSWORD"))

		_, err := rabbitMQURL("amqp://admin:$RABBITMQ_PASSWORD@broker:5672/")
		require.ErrorIs(t, err, errMissingRabbitMQPassword)
	})
}
