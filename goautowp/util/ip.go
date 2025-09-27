package util

import (
	"database/sql/driver"
	"errors"
	"fmt"
	"net"

	"github.com/doug-martin/goqu/v9"
)

var (
	errUnsupportedIPType = errors.New("unsupported type for IP")
	errIPParseFailed     = errors.New("failed to parse IP")
)

type IP net.IP

func (n IP) ToIP() net.IP {
	return net.IP(n)
}

// Scan implements the [Scanner] interface.
func (ip *IP) Scan(src interface{}) error {
	switch value := src.(type) {
	case []byte:
		parsedIP := net.IP(value)
		if parsedIP == nil {
			return fmt.Errorf("%w from bytes: %s", errIPParseFailed, value)
		}

		*ip = IP(parsedIP)
	case string:
		parsedIP := net.ParseIP(value)
		if parsedIP == nil {
			return fmt.Errorf("%w from string: %s", errIPParseFailed, value)
		}

		*ip = IP(parsedIP)
	case nil:
		*ip = nil // Handle NULL values
	default:
		return fmt.Errorf("%w: %T", errUnsupportedIPType, value)
	}

	return nil
}

// Value implements the [driver.Valuer] interface.
func (n IP) Value() (driver.Value, error) {
	if n == nil {
		return nil, nil //nolint: nilnil
	}

	return goqu.Func("INET6_ATON", n.ToIP().String()), nil
}
