package pictures

import (
	"context"
	"database/sql"

	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

type BrandPictureCounts struct {
	Logotypes int `db:"logotypes"`
	Mixed     int `db:"mixed"`
	Other     int `db:"other"`
}

// BrandCounts counts distinct accepted pictures linked directly to the brand.
// A picture can belong to multiple groups through different links; a missing
// perspective belongs to Other, matching ExcludePerspectiveID in the list filter.
func (s *Repository) BrandCounts(ctx context.Context, brandID int64) (BrandPictureCounts, error) {
	var counts BrandPictureCounts

	options := &query.PictureListOptions{
		Status:      schema.PictureStatusAccepted,
		PictureItem: &query.PictureItemListOptions{ItemID: brandID},
	}

	selection, err := options.Select(s.db, query.PictureAlias)
	if err != nil {
		return counts, err
	}

	pictureIDCol := goqu.T(query.PictureAlias).Col(schema.PictureTableIDColName)
	perspectiveIDCol := goqu.T(options.PictureItemAlias(query.PictureAlias, 0)).
		Col(schema.PictureItemTablePerspectiveIDColName)

	success, err := selection.Select(
		goqu.L("COUNT(DISTINCT ?) FILTER (WHERE ?)", pictureIDCol,
			perspectiveIDCol.Eq(schema.PerspectiveLogo)).As("logotypes"),
		goqu.L("COUNT(DISTINCT ?) FILTER (WHERE ?)", pictureIDCol,
			perspectiveIDCol.Eq(schema.PerspectiveMixed)).As("mixed"),
		goqu.L("COUNT(DISTINCT ?) FILTER (WHERE ?)", pictureIDCol,
			goqu.Or(perspectiveIDCol.IsNull(),
				perspectiveIDCol.NotIn([]int32{schema.PerspectiveLogo, schema.PerspectiveMixed}))).As("other"),
	).ScanStructContext(ctx, &counts)
	if err != nil {
		return counts, err
	}

	if !success {
		return counts, sql.ErrNoRows
	}

	return counts, nil
}
