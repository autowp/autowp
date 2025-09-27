package goautowp

import (
	"github.com/autowp/goautowp/schema"
)

type LinkExtractor struct {
	container *Container
}

func NewLinkExtractor(container *Container) *LinkExtractor {
	return &LinkExtractor{container: container}
}

func (s *LinkExtractor) ExtractRow(row *schema.LinkRow) *APIItemLink {
	return &APIItemLink{
		Id:     row.ID,
		Name:   row.Name,
		Type:   row.Type,
		Url:    row.URL,
		ItemId: row.ItemID,
	}
}

func (s *LinkExtractor) ExtractRows(rows []*schema.LinkRow) []*APIItemLink {
	res := make([]*APIItemLink, 0, len(rows))

	for _, row := range rows {
		res = append(res, s.ExtractRow(row))
	}

	return res
}
