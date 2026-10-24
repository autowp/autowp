package goautowp

import (
	"context"
	"errors"
	"fmt"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
)

var errPublicRouteCycle = errors.New("cyclic public route")

func (s *ItemExtractor) prepareDesignRouteLookups(
	repository *items.Repository,
	rows []*items.Item,
	ids []int64,
	fields *ItemFields,
	lang string,
	result *itemBatchFields,
) []func(context.Context) error {
	var lookups []func(context.Context) error
	if fields.GetDesign() {
		lookups = append(lookups, func(ctx context.Context) error {
			designs, err := repository.DesignInfos(ctx, ids, lang)
			if err != nil {
				return err
			}

			result.designs = make(map[int64]*Design, len(designs))
			for id, design := range designs {
				result.designs[id] = &Design{Name: design.Name, Route: design.Route}
			}

			return nil
		})
	}

	if fields.GetIsCompilesItemOfDay() {
		lookups = append(lookups, func(ctx context.Context) error {
			itemOfDay, err := s.container.ItemOfDayRepository(ctx)
			if err != nil {
				return err
			}

			result.complies, err = itemOfDay.CompliesBatch(ctx, ids)

			return err
		})
	}

	if fields.GetPublicRoutes() {
		lookups = append(lookups, func(ctx context.Context) error {
			var err error

			result.publicRoutes, err = s.preloadPublicRoutes(ctx, repository, rows)

			return err
		})
	}

	return lookups
}

func (s *ItemExtractor) preloadPublicRoutes(
	ctx context.Context, repository *items.Repository, rows []*items.Item,
) (map[int64][]*PublicRoute, error) {
	result := make(map[int64][]*PublicRoute, len(rows))

	ids := make([]int64, 0, len(rows))
	for _, row := range rows {
		switch row.ItemTypeID { //nolint:exhaustive // Only these types have a direct public route.
		case schema.ItemTableItemTypeIDFactory, schema.ItemTableItemTypeIDCategory,
			schema.ItemTableItemTypeIDTwins, schema.ItemTableItemTypeIDBrand:
			routes, err := s.itemPublicRoutes(ctx, row)
			if err != nil {
				return nil, err
			}

			result[row.ID] = routes
		default:
			// The legacy zero-ID filter means all items rather than the literal ID.
			if row.ID == 0 {
				routes, err := s.itemPublicRoutes(ctx, row)
				if err != nil {
					return nil, err
				}

				result[row.ID] = routes
			} else {
				ids = append(ids, row.ID)
			}
		}
	}

	graph, err := repository.PublicRouteAncestors(ctx, ids)
	if err != nil {
		return nil, err
	}

	for _, id := range ids {
		if _, ok := result[id]; ok {
			continue
		}

		routes, err := publicRoutesFromGraph(ctx, graph, id, nil, make(map[int64]bool))
		if err != nil {
			return nil, err
		}

		result[id] = routes
	}

	return result, nil
}

func publicRoutesFromGraph(
	ctx context.Context, graph *items.PublicRouteGraph, id int64, path []string, active map[int64]bool,
) ([]*PublicRoute, error) {
	if err := ctx.Err(); err != nil {
		return nil, err
	}

	if active[id] {
		return nil, fmt.Errorf("%w at item %d", errPublicRouteCycle, id)
	}

	active[id] = true
	defer delete(active, id)

	routes := make([]*PublicRoute, 0)

	for _, parent := range graph.Parents[id] {
		if catname, ok := graph.Brands[parent.ParentID]; ok {
			routes = append(routes, &PublicRoute{Route: frontend.BrandItemPathRoute(catname, parent.Catname, path)})
		}

		next, err := publicRoutesFromGraph(
			ctx,
			graph,
			parent.ParentID,
			append([]string{parent.Catname}, path...),
			active,
		)
		if err != nil {
			return nil, err
		}

		routes = append(routes, next...)
	}

	return routes, nil
}
