package storage

import (
	"errors"

	"github.com/autowp/goautowp/config"
)

var errUnknownNamingStrategy = errors.New("unknown naming strategy")

type Dir struct {
	bucket         string
	namingStrategy NamingStrategy
}

func NewDir(bucket string, config config.ImageStorageNamingStrategyConfig) (*Dir, error) {
	var strategy NamingStrategy

	switch config.Strategy {
	case NamingStrategyTypeSerial:
		strategy = NamingStrategySerial{
			deep: config.Options.Deep,
		}
	case NamingStrategyTypePattern:
		strategy = NamingStrategyPattern{}
	}

	if strategy == nil {
		return nil, errUnknownNamingStrategy
	}

	return &Dir{
		bucket:         bucket,
		namingStrategy: strategy,
	}, nil
}

func (d *Dir) Bucket() string {
	return d.bucket
}

func (d *Dir) NamingStrategy() NamingStrategy { //nolint:ireturn
	return d.namingStrategy
}
