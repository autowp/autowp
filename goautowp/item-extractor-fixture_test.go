package goautowp

import "github.com/autowp/goautowp/internal/transport/grpc/itemextractor"

type itemExtractorFixture struct {
	*itemextractor.Extractor

	container *Container
}

func newItemExtractorFixture(container *Container) *itemExtractorFixture {
	return &itemExtractorFixture{Extractor: container.ItemExtractor(), container: container}
}
