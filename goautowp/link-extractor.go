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

func (s *LinkExtractor) ExtractRow(row *schema.LinkRow) *ItemLink {
	return &ItemLink{
		Id:     row.ID,
		Name:   row.Name,
		Type:   row.Type,
		Url:    row.URL,
		ItemId: row.ItemID,
	}
}

func (s *LinkExtractor) ExtractRows(rows []*schema.LinkRow) []*ItemLink {
	res := make([]*ItemLink, 0, len(rows))

	for _, row := range rows {
		res = append(res, s.ExtractRow(row))
	}

	return res
}
