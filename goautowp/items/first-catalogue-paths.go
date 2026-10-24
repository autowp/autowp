package items

import (
	"context"
	"database/sql"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
)

type cataloguePathNode struct {
	ID         int64                      `db:"id"`
	Catname    sql.NullString             `db:"catname"`
	ItemTypeID schema.ItemTableItemTypeID `db:"item_type_id"`
}

// FirstCataloguePaths matches CataloguePaths with BreakOnFirst, loading the shared
// ancestor graph by levels instead of recursively querying it for every item.
func (s *Repository) FirstCataloguePaths(
	ctx context.Context, ids []int64,
) (map[int64]*CataloguePathResult, error) {
	nodes := make(map[int64]cataloguePathNode)
	parents := make(map[int64][]*ItemParent)
	seen := make(map[int64]bool)

	frontier := make([]int64, 0, len(ids))
	for _, id := range ids {
		if id <= 0 {
			return nil, errUnexpectedID
		}

		if !seen[id] {
			seen[id] = true
			frontier = append(frontier, id)
		}
	}

	for len(frontier) > 0 {
		var rows []cataloguePathNode

		err := s.db.From(schema.ItemTable).
			Select(schema.ItemTableIDCol, schema.ItemTableCatnameCol, schema.ItemTableItemTypeIDCol).
			Where(schema.ItemTableIDCol.In(frontier)).ScanStructsContext(ctx, &rows)
		if err != nil {
			return nil, err
		}

		expandable := make([]int64, 0, len(rows))
		for _, row := range rows {
			nodes[row.ID] = row
			if row.ItemTypeID != schema.ItemTableItemTypeIDBrand &&
				row.ItemTypeID != schema.ItemTableItemTypeIDCategory &&
				row.ItemTypeID != schema.ItemTableItemTypeIDPerson {
				expandable = append(expandable, row.ID)
			}
		}

		frontier = frontier[:0]

		if len(expandable) == 0 {
			break
		}

		edges, _, err := s.ItemParents(ctx, &query.ItemParentListOptions{ItemIDs: expandable},
			ItemParentFields{}, ItemParentOrderByStockFirst)
		if err != nil {
			return nil, err
		}

		for _, edge := range edges {
			parents[edge.ItemID] = append(parents[edge.ItemID], edge)
			if !seen[edge.ParentID] {
				seen[edge.ParentID] = true
				frontier = append(frontier, edge.ParentID)
			}
		}
	}

	builder := firstCataloguePathBuilder{
		nodes: nodes, parents: parents, paths: make(map[int64]*CataloguePathResult), active: make(map[int64]bool),
	}

	result := make(map[int64]*CataloguePathResult, len(ids))
	for _, id := range ids {
		result[id] = builder.path(id)
	}

	return result, nil
}

type firstCataloguePathBuilder struct {
	nodes   map[int64]cataloguePathNode
	parents map[int64][]*ItemParent
	paths   map[int64]*CataloguePathResult
	active  map[int64]bool
}

func (s *firstCataloguePathBuilder) path(id int64) *CataloguePathResult {
	if path, ok := s.paths[id]; ok {
		return path
	}

	if s.active[id] {
		return nil
	}

	s.active[id] = true
	defer delete(s.active, id)

	path := s.build(id)
	s.paths[id] = path

	return path
}

func (s *firstCataloguePathBuilder) build(id int64) *CataloguePathResult {
	row, ok := s.nodes[id]
	if !ok {
		return nil
	}

	switch row.ItemTypeID {
	case schema.ItemTableItemTypeIDBrand:
		return &CataloguePathResult{
			Type:         CataloguePathResultTypeBrand,
			BrandCatname: util.NullStringToString(row.Catname), Path: []string{}, Stock: true,
		}
	case schema.ItemTableItemTypeIDCategory:
		return &CataloguePathResult{
			Type:            CataloguePathResultTypeCategory,
			CategoryCatname: util.NullStringToString(row.Catname),
		}
	case schema.ItemTableItemTypeIDPerson:
		return &CataloguePathResult{Type: CataloguePathResultTypePerson, ID: id}
	case schema.ItemTableItemTypeIDVehicle, schema.ItemTableItemTypeIDEngine, schema.ItemTableItemTypeIDTwins,
		schema.ItemTableItemTypeIDFactory, schema.ItemTableItemTypeIDMuseum, schema.ItemTableItemTypeIDCopyright:
	}

	for _, edge := range s.parents[id] {
		parent := s.path(edge.ParentID)
		if parent == nil {
			continue
		}

		switch parent.Type {
		case CataloguePathResultTypeBrand:
			return &CataloguePathResult{
				Type:         CataloguePathResultTypeBrandItem,
				BrandCatname: parent.BrandCatname, CarCatname: edge.Catname, Path: []string{},
				Stock: edge.Type == schema.ItemParentTypeDefault,
			}
		case CataloguePathResultTypeBrandItem:
			return &CataloguePathResult{
				Type:         parent.Type,
				BrandCatname: parent.BrandCatname, CarCatname: parent.CarCatname,
				Path:  append(append([]string{}, parent.Path...), edge.Catname),
				Stock: parent.Stock && edge.Type == schema.ItemParentTypeDefault,
			}
		default:
		}
	}

	return nil
}
