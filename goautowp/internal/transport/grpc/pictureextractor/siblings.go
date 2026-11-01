package pictureextractor

import (
	"context"
	"database/sql"
	"maps"
	"slices"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
)

func (s *Extractor) preloadSiblings(
	ctx context.Context,
	repository *pictures.Repository,
	ids []int64,
	fields *pb.PictureFields,
	lang string,
	userCtx authn.User,
) (map[int64]*pb.PictureSiblings, error) {
	neighbors, err := repository.PictureSiblingIDs(ctx, ids)
	if err != nil {
		return nil, err
	}

	needed := make(map[int64]struct{})

	for _, row := range neighbors {
		for _, id := range []sql.NullInt64{row.Prev, row.Next, row.PrevNew, row.NextNew} {
			if id.Valid {
				needed[id.Int64] = struct{}{}
			}
		}
	}

	result := make(map[int64]*pb.PictureSiblings, len(ids))

	byID := make(map[int64]*pb.Picture, len(needed))
	if len(needed) > 0 {
		rows, _, err := repository.Pictures(ctx, &query.PictureListOptions{IDs: slices.Collect(maps.Keys(needed))},
			picturemapper.FieldsFromProto(fields), pictures.OrderByNone, false)
		if err != nil {
			return nil, err
		}

		extracted, err := s.ExtractRows(ctx, rows, fields, lang, userCtx)
		if err != nil {
			return nil, err
		}

		for _, row := range extracted {
			byID[row.GetId()] = row
		}
	}

	lookup := func(id sql.NullInt64) *pb.Picture {
		if !id.Valid {
			return nil
		}

		return byID[id.Int64]
	}
	for _, id := range ids {
		row := neighbors[id]
		result[id] = &pb.PictureSiblings{
			Prev:    lookup(row.Prev),
			Next:    lookup(row.Next),
			PrevNew: lookup(row.PrevNew),
			NextNew: lookup(row.NextNew),
		}
	}

	return result, nil
}
