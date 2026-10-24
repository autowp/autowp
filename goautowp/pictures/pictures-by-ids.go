package pictures

import (
	"context"
	"fmt"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// PicturesByIDs applies the original filters in addition to the requested IDs.
// ID and Limit are overridden as they are by individual Picture calls; an
// existing IDs filter remains an intersection rather than being overwritten.
func (s *Repository) PicturesByIDs(
	ctx context.Context, ids []int64, options *query.PictureListOptions, fields *PictureFields, order OrderBy,
) ([]*schema.PictureRow, error) {
	rows := make([]*schema.PictureRow, 0)
	if len(ids) == 0 {
		return rows, nil
	}

	options = options.Clone()
	if options == nil {
		options = &query.PictureListOptions{}
	}

	options.ID = 0
	options.Limit = 0

	selection, err := s.PictureSelect(options, fields, order)
	if err != nil {
		return nil, fmt.Errorf("PictureSelect(): %w", err)
	}

	err = selection.Where(goqu.T(query.PictureAlias).Col(schema.PictureTableIDColName).In(ids)).
		ScanStructsContext(ctx, &rows)

	return rows, err
}
