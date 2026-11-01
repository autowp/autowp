package maps

import (
	"context"
	"database/sql"
	"errors"
	"math"

	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/util"
	"github.com/doug-martin/goqu/v9"
)

type Repository struct{ db *goqu.Database }

func NewRepository(db *goqu.Database) *Repository { return &Repository{db: db} }

const (
	minLongitude = -180.0
	maxLongitude = 180.0
	worldWidth   = 360.0
)

// normalizedLongitudeRanges splits a longitude range that may extend beyond +-180deg - Leaflet
// allows continuous horizontal panning across repeated "world copies", so bounds like (300, 700)
// are just as valid as (-60, 60) for the same visible strip - into one or two ranges within the
// canonical [-180, 180] space that stored points actually use. A range spanning the full world or
// more collapses to a single canonical [-180, 180] range; otherwise at most one antimeridian split
// is needed, since a single map viewport's width is always less than 360deg.
func normalizedLongitudeRanges(lngLo, lngHi float64) [][2]float64 {
	if lngHi-lngLo >= worldWidth {
		return [][2]float64{{minLongitude, maxLongitude}}
	}

	normLo := math.Mod(lngLo+maxLongitude, worldWidth)
	if normLo < 0 {
		normLo += worldWidth
	}

	normLo -= maxLongitude
	normHi := normLo + (lngHi - lngLo)

	if normHi <= maxLongitude {
		return [][2]float64{{normLo, normHi}}
	}

	return [][2]float64{
		{normLo, maxLongitude},
		{minLongitude, normHi - worldWidth},
	}
}

// PictureGridSize controls the number of viewport cells per axis.
// Individual requests are capped to bound responses in dense areas.
const (
	PictureGridSize        = 16
	individualPictureLimit = 100
)

func (s *Repository) ItemPoints(ctx context.Context, lngLo, latLo, lngHi, latHi float64) ([]schema.NullPoint, error) {
	rows, err := s.itemPointsSelect(lngLo, latLo, lngHi, latHi).Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil {
		return nil, err
	}
	defer util.Close(rows)

	points := make([]schema.NullPoint, 0)

	for rows.Next() {
		var point schema.NullPoint
		if err := rows.Scan(&point); err != nil {
			return nil, err
		}

		points = append(points, point)
	}

	return points, rows.Err()
}

func (s *Repository) ItemPointsWithContent(
	ctx context.Context,
	lngLo, latLo, lngHi, latHi float64,
	lang string,
) ([]ItemPoint, error) {
	sqSelect := s.itemPointsSelect(lngLo, latLo, lngHi, latHi)
	// Prefer the item's item_language name for the requested lang (falling back to the legacy
	// item.name column only when the item has no item_language rows at all), same as every other
	// name-display path in the app - see items.NameOnlyColumn.
	nameExpr, err := (items.NameOnlyColumn{DB: s.db}).SelectExpr(schema.ItemTableName, lang)
	if err != nil {
		return nil, err
	}

	rows, err := items.LeftJoinItemLanguageCache(
		sqSelect.
			SelectAppend(
				schema.ItemTableIDCol,
				nameExpr,
				schema.ItemTableBeginYearCol,
				schema.ItemTableEndYearCol,
				schema.ItemTableItemTypeIDCol,
				schema.ItemTableTodayCol,
			).
			Join(schema.ItemTable, goqu.On(schema.ItemPointTableItemIDCol.Eq(schema.ItemTableIDCol))),
		schema.ItemTableName,
		lang,
	).
		Executor().QueryContext(ctx) //nolint:sqlclosecheck

	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return nil, err
	}

	defer util.Close(rows)

	result := make([]ItemPoint, 0)

	for rows.Next() {
		var row ItemPoint
		if err := rows.Scan(
			&row.Point,
			&row.ID,
			&row.Name,
			&row.BeginYear,
			&row.EndYear,
			&row.ItemTypeID,
			&row.Today,
		); err != nil {
			return nil, err
		}

		result = append(result, row)
	}

	return result, rows.Err()
}

func (s *Repository) ItemImageID(ctx context.Context, id int64) (sql.NullInt64, error) {
	var imageID sql.NullInt64

	_, err := s.db.Select(schema.PictureTableImageIDCol).
		From(schema.PictureTable).
		Join(schema.PictureItemTable, goqu.On(schema.PictureTableIDCol.Eq(schema.PictureItemTablePictureIDCol))).
		Where(
			schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted),
			schema.PictureItemTableItemIDCol.Eq(id),
		).
		ScanValContext(ctx, &imageID)
	if err != nil {
		return imageID, err
	}

	return imageID, nil
}

func (s *Repository) IndividualPictureIDs(
	ctx context.Context,
	lngLo, latLo, lngHi, latHi float64,
) ([]int, error) {
	var ids []int

	for _, lngRange := range normalizedLongitudeRanges(lngLo, lngHi) {
		rangeIDs, err := s.pictureIDsInRange(
			ctx, lngRange[0], latLo, lngRange[1], latHi, individualPictureLimit-len(ids),
		)
		if err != nil {
			return nil, err
		}

		ids = append(ids, rangeIDs...)

		if len(ids) >= individualPictureLimit {
			break
		}
	}

	return ids, nil
}

func (s *Repository) PictureCells(
	ctx context.Context,
	lngLo, latLo, lngHi, latHi float64,
) ([]PictureCell, error) {
	cellLat := (latHi - latLo) / PictureGridSize
	if cellLat <= 0 {
		return nil, nil
	}

	var cells []PictureCell

	for _, lngRange := range normalizedLongitudeRanges(lngLo, lngHi) {
		cellLng := (lngRange[1] - lngRange[0]) / PictureGridSize
		if cellLng <= 0 {
			continue
		}

		rangeCells, err := s.pictureCellsInRange(ctx, lngRange[0], latLo, lngRange[1], latHi, cellLng, cellLat)
		if err != nil {
			return nil, err
		}

		cells = append(cells, rangeCells...)
	}

	return cells, nil
}

func (s *Repository) Pictures(ctx context.Context, ids []int) ([]Picture, error) {
	if len(ids) == 0 {
		return nil, nil
	}

	rows, err := s.db.Select(
		schema.PictureTableIdentityCol,
		schema.PictureTablePointCol,
		schema.PictureTableImageIDCol,
	).
		From(schema.PictureTable).
		Where(schema.PictureTableIDCol.In(ids)).
		Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return nil, err
	}

	defer util.Close(rows)

	var result []Picture

	for rows.Next() {
		var row Picture
		if err := rows.Scan(&row.Identity, &row.Point, &row.ImageID); err != nil {
			return nil, err
		}

		result = append(result, row)
	}

	return result, rows.Err()
}

func (s *Repository) itemPointsSelect(lngLo, latLo, lngHi, latHi float64) *goqu.SelectDataset {
	// Cast geography to geometry so viewports wider than 180 degrees retain their intended area.
	// The item_point_point_geom index covers this cast.
	rangeExprs := make([]goqu.Expression, 0, 2)

	for _, lngRange := range normalizedLongitudeRanges(lngLo, lngHi) {
		rangeExprs = append(rangeExprs, goqu.Func(
			"ST_Intersects",
			goqu.L("?::geometry", schema.ItemPointTablePointCol),
			goqu.Func("ST_MakeEnvelope", lngRange[0], latLo, lngRange[1], latHi, schema.SRID),
		))
	}

	return s.db.Select(schema.ItemPointTablePointCol).
		From(schema.ItemPointTable).
		Where(goqu.Or(rangeExprs...))
}

func (s *Repository) pictureIDsInRange(
	ctx context.Context,
	lngLo, latLo, lngHi, latHi float64,
	limit int,
) ([]int, error) {
	if limit <= 0 {
		return nil, nil
	}

	rows, err := s.db.Select(schema.PictureTableIDCol).
		From(schema.PictureTable).
		Where(
			schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted),
			goqu.Func(
				"ST_Intersects",
				goqu.L("?::geometry", schema.PictureTablePointCol),
				goqu.Func("ST_MakeEnvelope", lngLo, latLo, lngHi, latHi, schema.SRID),
			),
		).
		Limit(uint(limit)).
		Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return nil, err
	}

	defer util.Close(rows)

	var ids []int

	for rows.Next() {
		var id int

		err = rows.Scan(&id)
		if err != nil {
			return nil, err
		}

		ids = append(ids, id)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return ids, nil
}

func (s *Repository) pictureCellsInRange(
	ctx context.Context,
	lngLo, latLo, lngHi, latHi, cellLng, cellLat float64,
) ([]PictureCell, error) {
	rows, err := s.db.Select(
		goqu.COUNT(goqu.Star()),
		goqu.AVG(goqu.L("ST_X(?::geometry)", schema.PictureTablePointCol)),
		goqu.AVG(goqu.L("ST_Y(?::geometry)", schema.PictureTablePointCol)),
		goqu.MIN(schema.PictureTableIDCol),
	).
		From(schema.PictureTable).
		Where(
			schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted),
			goqu.Func(
				"ST_Intersects",
				goqu.L("?::geometry", schema.PictureTablePointCol),
				goqu.Func("ST_MakeEnvelope", lngLo, latLo, lngHi, latHi, schema.SRID),
			),
		).
		GroupBy(
			goqu.L("FLOOR((ST_X(?::geometry) - ?) / ?)", schema.PictureTablePointCol, lngLo, cellLng),
			goqu.L("FLOOR((ST_Y(?::geometry) - ?) / ?)", schema.PictureTablePointCol, latLo, cellLat),
		).
		Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return nil, err
	}

	defer util.Close(rows)

	var cells []PictureCell

	for rows.Next() {
		var cell PictureCell

		err = rows.Scan(&cell.Count, &cell.AvgLng, &cell.AvgLat, &cell.SampleID)
		if err != nil {
			return nil, err
		}

		cells = append(cells, cell)
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return cells, nil
}
