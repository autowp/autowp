package goautowp

import (
	"context"
	"database/sql"
	"maps"
	"slices"

	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
)

func (s *PictureExtractor) preloadSiblings(
	ctx context.Context,
	repository *pictures.Repository,
	ids []int64,
	fields *PictureFields,
	lang string,
	userCtx UserContext,
) (map[int64]*PictureSiblings, error) {
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

	result := make(map[int64]*PictureSiblings, len(ids))

	byID := make(map[int64]*Picture, len(needed))
	if len(needed) > 0 {
		rows, _, err := repository.Pictures(ctx, &query.PictureListOptions{IDs: slices.Collect(maps.Keys(needed))},
			convertPictureFields(fields), pictures.OrderByNone, false)
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

	lookup := func(id sql.NullInt64) *Picture {
		if !id.Valid {
			return nil
		}

		return byID[id.Int64]
	}
	for _, id := range ids {
		row := neighbors[id]
		result[id] = &PictureSiblings{
			Prev:    lookup(row.Prev),
			Next:    lookup(row.Next),
			PrevNew: lookup(row.PrevNew),
			NextNew: lookup(row.NextNew),
		}
	}

	return result, nil
}
