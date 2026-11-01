package util

import (
	"context"
	"math"

	"github.com/doug-martin/goqu/v9"
)

const DefaultItemCountPerPage = 10

type Paginator struct {
	SQLSelect           *goqu.SelectDataset
	pageCount           int32
	pageCountCalculated bool
	ItemCountPerPage    int32
	CurrentPageNumber   int32
	itemCount           int32
	itemCountCalculated bool
}

type Pages struct {
	PageCount        int32
	ItemCountPerPage int32
	Current          int32
	TotalItemCount   int32
}

func (s *Paginator) Count(ctx context.Context) (int32, error) {
	var err error
	if !s.pageCountCalculated {
		s.pageCount, err = s.calculatePageCount(ctx)
		if err != nil {
			return 0, err
		}

		s.pageCountCalculated = true
	}

	return s.pageCount, nil
}

func (s *Paginator) GetPages(ctx context.Context) (*Pages, error) {
	pageCount, err := s.Count(ctx)
	if err != nil {
		return nil, err
	}

	currentPageNumber, err := s.getCurrentPageNumber(ctx)
	if err != nil {
		return nil, err
	}

	totalItemCount, err := s.GetTotalItemCount(ctx)
	if err != nil {
		return nil, err
	}

	pages := Pages{
		PageCount:        pageCount,
		ItemCountPerPage: s.ItemCountPerPage,
		Current:          currentPageNumber,
		TotalItemCount:   totalItemCount,
	}

	return &pages, nil
}

func (s *Paginator) GetItemsByPage(
	ctx context.Context,
	pageNumber int32,
) (*goqu.SelectDataset, error) {
	var err error

	pageNumber, err = s.normalizePageNumber(ctx, pageNumber)
	if err != nil {
		return nil, err
	}

	offset := (pageNumber - 1) * s.ItemCountPerPage
	ds := *s.SQLSelect

	return ds.Offset(uint(offset)).Limit(uint(s.ItemCountPerPage)), nil //nolint:gosec
}

func (s *Paginator) GetCurrentItems(ctx context.Context) (*goqu.SelectDataset, error) {
	pageNumber, err := s.getCurrentPageNumber(ctx)
	if err != nil {
		return nil, err
	}

	return s.GetItemsByPage(ctx, pageNumber)
}

func (s *Paginator) GetTotalItemCount(ctx context.Context) (int32, error) {
	var err error
	if !s.itemCountCalculated {
		s.itemCount, err = s.calculateCount(ctx)
		if err != nil {
			return 0, err
		}

		s.itemCountCalculated = true
	}

	return s.itemCount, nil
}

func (s *Paginator) calculatePageCount(ctx context.Context) (int32, error) {
	count, err := s.GetTotalItemCount(ctx)
	if err != nil {
		return 0, err
	}

	if s.ItemCountPerPage <= 0 {
		return 0, nil
	}

	return int32(math.Ceil(float64(count) / float64(s.ItemCountPerPage))), nil
}

func (s *Paginator) calculateCount(ctx context.Context) (int32, error) {
	clauses := s.SQLSelect.GetClauses()
	groupBy := clauses.GroupBy()

	var (
		res int64
		err error
	)

	if groupBy == nil || groupBy.IsEmpty() {
		res, err = s.SQLSelect.ClearOrder().
			ClearOffset().
			ClearLimit().
			GroupBy().
			ClearSelect().
			CountContext(ctx)
		if err != nil {
			return 0, err
		}
	} else {
		countQuery := s.SQLSelect.ClearOrder().
			ClearOffset().
			ClearLimit().
			GroupBy().
			ClearSelect().
			Select(goqu.COUNT(goqu.DISTINCT(groupBy.Columns())))

		_, err = countQuery.ScanValContext(ctx, &res)
		if err != nil {
			return 0, err
		}
	}

	return int32(res), nil //nolint: gosec
}

func (s *Paginator) getCurrentPageNumber(ctx context.Context) (int32, error) {
	return s.normalizePageNumber(ctx, s.CurrentPageNumber)
}

func (s *Paginator) normalizePageNumber(ctx context.Context, pageNumber int32) (int32, error) {
	if pageNumber < 1 {
		pageNumber = 1
	}

	pageCount, err := s.Count(ctx)
	if err != nil {
		return 0, err
	}

	if pageCount > 0 && pageNumber > pageCount {
		pageNumber = pageCount
	}

	return pageNumber, nil
}
