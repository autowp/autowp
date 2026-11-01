package itemmapper

import (
	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/schema"
)

func LinkToProto(row *schema.LinkRow) *pb.ItemLink {
	return &pb.ItemLink{
		Id:     row.ID,
		Name:   row.Name,
		Type:   row.Type,
		Url:    row.URL,
		ItemId: row.ItemID,
	}
}

func LinksToProto(rows []*schema.LinkRow) []*pb.ItemLink {
	res := make([]*pb.ItemLink, 0, len(rows))

	for _, row := range rows {
		res = append(res, LinkToProto(row))
	}

	return res
}
