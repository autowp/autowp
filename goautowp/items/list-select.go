package items

import (
	"context"
	"fmt"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
)

func (s *Repository) listSelect(
	ctx context.Context, options *query.ItemListOptions, fields *ItemFields, orderBy OrderBy, pagination bool,
) (*goqu.SelectDataset, *util.Pages, error) {
	var err error

	alias := query.ItemAlias
	aliasTable := goqu.T(alias)

	err = s.isFieldsValid(options, fields, orderBy)
	if err != nil {
		return nil, nil, err
	}

	if options.SortByName && (fields == nil || !fields.NameOnly) {
		return nil, nil, fmt.Errorf("%w: NameOnly for SortByName", errFieldsIsRequired)
	}

	if canAggregateDescendantPictures(options, fields, orderBy) {
		options = options.Clone()
		options.AggregateDescendantPictures = true
	}

	sqSelect, err := options.Select(s.db, alias)
	if err != nil {
		return nil, nil, err
	}

	sqSelect = sqSelect.GroupBy(aliasTable.Col(schema.ItemTableIDColName))

	// Must come after GroupBy above: goqu's GroupBy() replaces the clause rather than appending,
	// so an earlier GroupByAppend here (from joinItemLanguageCache) would be wiped out by it.
	sqSelect = s.joinItemLanguageCache(sqSelect, alias, fields, orderBy, options.Language)

	var pages *util.Pages

	outAlias := query.ItemAlias

	if options.Limit > 0 {
		wrappedOrderBy, err := s.wrappedOrderBy(query.ItemAlias, orderBy, options.Language)
		if err != nil {
			return nil, nil, err
		}

		if len(wrappedOrderBy) > 0 {
			sqSelect = sqSelect.Order(wrappedOrderBy...)
		}

		paginator := util.Paginator{
			SQLSelect:         sqSelect,
			ItemCountPerPage:  int32(options.Limit), //nolint: gosec
			CurrentPageNumber: int32(options.Page),  //nolint: gosec
		}

		if pagination {
			pages, err = paginator.GetPages(ctx)
			if err != nil {
				return nil, nil, err
			}
		}

		sqSelect, err = paginator.GetCurrentItems(ctx)
		if err != nil {
			return nil, nil, err
		}

		// implements deferred join pattern
		wrappedAlias := "wrapped"
		wrappedIDCol := goqu.T(wrappedAlias).Col(schema.ItemTableIDColName)
		wrappedColumns := s.wrappedSelectColumns(orderBy)
		wrappedColumnsExpr := make([]interface{}, 0, len(wrappedColumns))

		for _, mapItem := range toSortedColumns(wrappedColumns) {
			expr, err := mapItem.col.SelectExpr(query.ItemAlias, options.Language)
			if err != nil {
				return nil, nil, err
			}

			wrappedColumnsExpr = append(wrappedColumnsExpr, expr.As(mapItem.key))
		}

		wrapperColumns := s.columnsByFields(fields, options.AggregateDescendantPictures)
		wrapperColumnsExpr := make([]interface{}, 0, len(wrapperColumns))
		groupByColumnsExpr := make([]interface{}, 0, len(wrapperColumns))

		for _, mapItem := range toSortedColumns(wrapperColumns) {
			var expr AliaseableExpression

			_, isWrapped := wrappedColumns[mapItem.key]
			if isWrapped && mapItem.key != schema.ItemTableIDColName {
				expr = goqu.T(wrappedAlias).Col(mapItem.key)
			} else {
				expr, err = mapItem.col.SelectExpr(schema.ItemTableName, options.Language)
				if err != nil {
					return nil, nil, err
				}
			}

			groupByExpr := mapItem.col.GroupByExpr()

			if groupByExpr != nil {
				groupByColumnsExpr = append(groupByColumnsExpr, groupByExpr)
			}

			wrapperColumnsExpr = append(wrapperColumnsExpr, expr.As(mapItem.key))
		}

		options.Alias = schema.ItemTableName

		wrappedSqSelect := sqSelect

		sqSelect, err = options.Select(s.db, options.Alias)
		if err != nil {
			return nil, nil, err
		}

		sqSelect = sqSelect.Select(wrapperColumnsExpr...).
			From(schema.ItemTable).
			Join(wrappedSqSelect.Select(wrappedColumnsExpr...).As(wrappedAlias), goqu.On(
				schema.ItemTableIDCol.Eq(wrappedIDCol),
			)).
			GroupBy(schema.ItemTableIDCol).
			GroupByAppend(groupByColumnsExpr...)

		// Must come after the chain above: goqu's From()/GroupBy() replace their clause rather
		// than appending, so an earlier join/GroupByAppend here (from joinItemLanguageCache)
		// would be wiped out by them.
		sqSelect = s.joinItemLanguageCache(sqSelect, options.Alias, fields, orderBy, options.Language)

		wrapperOrderBy, groupBy, err := s.wrapperOrderBy(schema.ItemTableName, wrappedAlias, orderBy, options.Language)
		if err != nil {
			return nil, nil, err
		}

		if len(wrapperOrderBy) > 0 {
			sqSelect = sqSelect.Order(wrapperOrderBy...)
			if len(groupBy) > 0 {
				sqSelect = sqSelect.GroupByAppend(groupBy...)
			}
		}

		outAlias = schema.ItemTableName
	} else {
		orderByExpr, err := s.orderBy(query.ItemAlias, orderBy, options.Language)
		if err != nil {
			return nil, nil, err
		}

		if len(orderByExpr) > 0 {
			sqSelect = sqSelect.Order(orderByExpr...)
		}

		columns := s.columnsByFields(fields, options.AggregateDescendantPictures)

		res := make([]interface{}, 0, len(columns))

		for _, mapItem := range toSortedColumns(columns) {
			expr, err := mapItem.col.SelectExpr(query.ItemAlias, options.Language)
			if err != nil {
				return nil, nil, err
			}

			res = append(res, expr.As(mapItem.key))

			groupByExpr := mapItem.col.GroupByExpr()
			if groupByExpr != nil {
				sqSelect = sqSelect.GroupByAppend(groupByExpr)
			}
		}

		sqSelect = sqSelect.Select(res...)
	}

	outTable := goqu.T(outAlias)

	if fields != nil && (fields.NameText || fields.NameHTML) {
		sqSelect = sqSelect.LeftJoin(
			schema.SpecTable,
			goqu.On(outTable.Col(schema.ItemTableSpecIDColName).Eq(schema.SpecTableIDCol)),
		)
	}

	return sqSelect, pages, nil
}
