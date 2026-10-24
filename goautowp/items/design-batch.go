package items

import (
	"context"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

// DesignInfos preserves the direct-design lookup before the inherited fallback.
func (s *Repository) DesignInfos(ctx context.Context, ids []int64, lang string) (map[int64]*DesignInfo, error) {
	result := make(map[int64]*DesignInfo, len(ids))
	if len(ids) == 0 {
		return result, nil
	}

	nameColumn := NameOnlyColumn{DB: s.db}

	expr, err := nameColumn.SelectExpr(schema.ItemTableName, lang)
	if err != nil {
		return nil, err
	}

	selection := LeftJoinItemLanguageCache(s.db.Select(
		schema.ItemParentCacheTableItemIDCol,
		schema.ItemTableCatnameCol, expr.As("name"),
		schema.ItemParentTableCatnameCol.As("brand_item_catname"),
	).From(schema.ItemTable), schema.ItemTableName, lang).
		Join(schema.ItemParentTable, goqu.On(schema.ItemTableIDCol.Eq(schema.ItemParentTableParentIDCol))).
		Join(schema.ItemParentCacheTable, goqu.On(
			schema.ItemParentTableItemIDCol.Eq(schema.ItemParentCacheTableParentIDCol),
		)).
		Where(schema.ItemTableItemTypeIDCol.Eq(schema.ItemTableItemTypeIDBrand)).
		Distinct(schema.ItemParentCacheTableItemIDCol).
		Order(schema.ItemParentCacheTableItemIDCol.Asc(), schema.ItemParentCacheTableDiffCol.Asc())

	load := func(ids []int64, predicate goqu.Expression) error {
		var rows []struct {
			ItemID           int64  `db:"item_id"`
			Name             string `db:"name"`
			Catname          string `db:"catname"`
			BrandItemCatname string `db:"brand_item_catname"`
		}

		err := selection.Where(schema.ItemParentCacheTableItemIDCol.In(ids), predicate).ScanStructsContext(ctx, &rows)
		if err != nil {
			return err
		}

		for _, row := range rows {
			result[row.ItemID] = &DesignInfo{
				Name:  row.Name,
				Route: frontend.BrandItemRoute(row.Catname, row.BrandItemCatname),
			}
		}

		return nil
	}
	if err := load(ids, schema.ItemParentTableTypeCol.Eq(schema.ItemParentTypeDesign)); err != nil {
		return nil, err
	}

	missing := make([]int64, 0, len(ids))
	for _, id := range ids {
		if result[id] == nil {
			missing = append(missing, id)
		}
	}

	if len(missing) > 0 {
		if err := load(missing, schema.ItemParentCacheTableDesignCol.IsTrue()); err != nil {
			return nil, err
		}
	}

	return result, nil
}
