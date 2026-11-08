package config

import (
	"errors"
	"fmt"
	"net/url"
	"os"
)

var errMissingRabbitMQPassword = errors.New("rabbitmq password environment variable RABBITMQ_PASSWORD is not set")

func rabbitMQURL(uri string) (string, error) {
	parsed, err := url.Parse(uri)
	if err != nil {
		return "", fmt.Errorf("parse rabbitmq URL: %w", err)
	}

	if parsed.User == nil {
		return uri, nil
	}

	password, _ := parsed.User.Password()
	if password != "$RABBITMQ_PASSWORD" { //nolint:gosec // Configuration placeholder, not a password.
		return uri, nil
	}

	password, ok := os.LookupEnv("RABBITMQ_PASSWORD")
	if !ok {
		return "", errMissingRabbitMQPassword
	}

	parsed.User = url.UserPassword(parsed.User.Username(), password)

	return parsed.String(), nil
}
