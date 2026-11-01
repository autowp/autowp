package comments

import (
	"context"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/util"
	"github.com/doug-martin/goqu/v9/exp"
)

type OrderBy uint8

const (
	OrderByNone OrderBy = iota
	OrderByDateAsc
	OrderByDateDesc
	OrderByVoteAsc
	OrderByVoteDesc
)

func (s *Repository) Messages(ctx context.Context, request Request) ([]*schema.CommentMessageRow, *util.Pages, error) {
	paginator := s.Paginator(request)

	query, err := paginator.GetCurrentItems(ctx)
	if err != nil {
		return nil, nil, err
	}

	rows := make([]*schema.CommentMessageRow, 0)
	if err := query.ScanStructsContext(ctx, &rows); err != nil {
		return nil, nil, err
	}

	pages, err := paginator.GetPages(ctx)
	if err != nil {
		return nil, nil, err
	}

	return rows, pages, nil
}

func (s Request) order() []exp.OrderedExpression {
	switch s.OrderBy {
	case OrderByDateAsc:
		return []exp.OrderedExpression{schema.CommentMessageTableDatetimeCol.Asc()}
	case OrderByDateDesc:
		return []exp.OrderedExpression{schema.CommentMessageTableDatetimeCol.Desc()}
	case OrderByVoteAsc:
		return []exp.OrderedExpression{
			schema.CommentMessageTableVoteCol.Asc(),
			schema.CommentMessageTableDatetimeCol.Desc(),
		}
	case OrderByVoteDesc:
		return []exp.OrderedExpression{
			schema.CommentMessageTableVoteCol.Desc(),
			schema.CommentMessageTableDatetimeCol.Desc(),
		}
	case OrderByNone:
		return s.Order
	}

	return s.Order
}
