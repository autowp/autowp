package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"math"
	"strconv"
	"strings"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

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

type MapGRPCServer struct {
	UnimplementedMapServer

	db           *goqu.Database
	imageStorage *storage.Storage
	i18n         *i18nbundle.I18n
}

func NewMapGRPCServer(
	db *goqu.Database,
	imageStorage *storage.Storage,
	i18n *i18nbundle.I18n,
) *MapGRPCServer {
	return &MapGRPCServer{
		db:           db,
		imageStorage: imageStorage,
		i18n:         i18n,
	}
}

// parseMapBounds parses the "lngLo,latLo,lngHi,latHi" bounds format shared by GetPoints and
// GetPicturePoints (Leaflet's LatLngBounds.toBBoxString()).
func parseMapBounds(bounds string) (float64, float64, float64, float64, error) {
	const numberOfBounds = 4

	parts := strings.Split(bounds, ",")

	if len(parts) < numberOfBounds {
		return 0, 0, 0, 0, status.Error(codes.InvalidArgument, "Invalid bounds")
	}

	const bitSize64 = 64

	lngLo, err := strconv.ParseFloat(parts[0], bitSize64)
	if err != nil {
		return 0, 0, 0, 0, status.Error(codes.InvalidArgument, err.Error())
	}

	latLo, err := strconv.ParseFloat(parts[1], bitSize64)
	if err != nil {
		return 0, 0, 0, 0, status.Error(codes.InvalidArgument, err.Error())
	}

	lngHi, err := strconv.ParseFloat(parts[2], bitSize64)
	if err != nil {
		return 0, 0, 0, 0, status.Error(codes.InvalidArgument, err.Error())
	}

	latHi, err := strconv.ParseFloat(parts[3], bitSize64)
	if err != nil {
		return 0, 0, 0, 0, status.Error(codes.InvalidArgument, err.Error())
	}

	return lngLo, latLo, lngHi, latHi, nil
}

func (s *MapGRPCServer) GetPoints(
	ctx context.Context,
	in *MapGetPointsRequest,
) (*MapPoints, error) {
	lngLo, latLo, lngHi, latHi, err := parseMapBounds(in.GetBounds())
	if err != nil {
		return nil, err
	}

	pointsOnly := in.GetPointsOnly()

	// Stored points always use canonical [-180, 180] longitude, but the incoming bounds may not -
	// besides spanning close to or more than 360deg at low zoom, Leaflet also allows continuous
	// horizontal panning past +-180deg into repeated "world copies" (e.g. bounds of (300, 700)
	// instead of (-60, 60) for the same visible strip) - so normalize into one or two canonical
	// ranges first.
	//
	// Compared via ST_Intersects against the point cast to geometry, not against the geography
	// column directly: geography compares along the shortest great-circle arc, so an edge wider
	// than 180deg of longitude - which a normalized range can still be, e.g. [-170, 180] - gets
	// silently interpreted as wrapping the short way around the globe, matching only a narrow
	// sliver near the antimeridian instead of the intended wide view. The item_point_point_geom
	// GIST index (see migrations/29_item_point_geometry_index.up.sql) covers this cast, so this
	// stays index-accelerated rather than falling back to a sequential scan.
	rangeExprs := make([]goqu.Expression, 0, 2)

	for _, lngRange := range normalizedLongitudeRanges(lngLo, lngHi) {
		rangeExprs = append(rangeExprs, goqu.Func(
			"ST_Intersects",
			goqu.L("?::geometry", schema.ItemPointTablePointCol),
			goqu.Func("ST_MakeEnvelope", lngRange[0], latLo, lngRange[1], latHi, schema.SRID),
		))
	}

	sqSelect := s.db.Select(schema.ItemPointTablePointCol).
		From(schema.ItemPointTable).
		Where(goqu.Or(rangeExprs...))

	if pointsOnly {
		rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
		if err != nil && !errors.Is(err, sql.ErrNoRows) {
			return nil, status.Error(codes.Internal, err.Error())
		}

		defer util.Close(rows)

		mapPoints := make([]*MapPoint, 0)

		for rows.Next() {
			var point schema.NullPoint

			err = rows.Scan(&point)
			if err != nil {
				return nil, status.Error(codes.InvalidArgument, err.Error())
			}

			if point.Valid {
				mapPoints = append(mapPoints, &MapPoint{
					Location: &latlng.LatLng{
						Latitude:  point.Point.Y(),
						Longitude: point.Point.X(),
					},
				})
			}
		}

		if err = rows.Err(); err != nil {
			return nil, err
		}

		return &MapPoints{
			Points: mapPoints,
		}, nil
	}

	mapPoints, err := s.pointsWithContent(ctx, sqSelect, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &MapPoints{
		Points: mapPoints,
	}, nil
}

// pictureGridSize is the number of grid cells per axis GetPicturePoints buckets the viewport into.
// Cells shrink as the viewport shrinks (i.e. as the user zooms in), so a cell with exactly one
// picture renders as an individual pin and a cell with more renders as a count cluster - adaptively,
// with no zoom-level threshold to tune.
const pictureGridSize = 16

// individualPictureLimit caps the number of pictures GetPicturePoints returns when the caller asks
// to skip grid clustering (in.GetIndividual()) - intended for re-resolving a cluster against an
// already tight bounding box, not for browsing the whole map, so the cap only guards against an
// unexpectedly dense bbox rather than needing to be large.
const individualPictureLimit = 100

func (s *MapGRPCServer) GetPicturePoints(
	ctx context.Context,
	in *MapGetPicturePointsRequest,
) (*MapPicturePoints, error) {
	lngLo, latLo, lngHi, latHi, err := parseMapBounds(in.GetBounds())
	if err != nil {
		return nil, err
	}

	if in.GetIndividual() {
		ids, err := s.individualPictureIDsInBounds(ctx, lngLo, latLo, lngHi, latHi)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		points, err := s.singlePicturePoints(ctx, ids)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		return &MapPicturePoints{Points: points}, nil
	}

	cellLat := (latHi - latLo) / pictureGridSize
	if cellLat <= 0 {
		return &MapPicturePoints{}, nil
	}

	var cells []pictureCell

	for _, lngRange := range normalizedLongitudeRanges(lngLo, lngHi) {
		cellLng := (lngRange[1] - lngRange[0]) / pictureGridSize
		if cellLng <= 0 {
			continue
		}

		rangeCells, err := s.pictureCellsInRange(ctx, lngRange[0], latLo, lngRange[1], latHi, cellLng, cellLat)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		cells = append(cells, rangeCells...)
	}

	points := make([]*MapPicturePoint, 0, len(cells))
	singletonIDs := make([]int, 0, len(cells))

	for _, cell := range cells {
		if cell.count == 1 {
			singletonIDs = append(singletonIDs, int(cell.sampleID))

			continue
		}

		points = append(points, &MapPicturePoint{
			Kind: &MapPicturePoint_Cluster{
				Cluster: &MapPictureCluster{
					Location: &latlng.LatLng{Latitude: cell.avgLat, Longitude: cell.avgLng},
					Count:    int32(cell.count), //nolint: gosec
				},
			},
		})
	}

	singlePoints, err := s.singlePicturePoints(ctx, singletonIDs)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	points = append(points, singlePoints...)

	return &MapPicturePoints{Points: points}, nil
}

func (s *MapGRPCServer) individualPictureIDsInBounds(
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

func (s *MapGRPCServer) pictureIDsInRange(
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

func (s *MapGRPCServer) pointsWithContent(
	ctx context.Context,
	sqSelect *goqu.SelectDataset,
	lang string,
) ([]*MapPoint, error) {
	// Prefer the item's item_language name for the requested lang (falling back to the legacy
	// item.name column only when the item has no item_language rows at all), same as every other
	// name-display path in the app - see items.NameOnlyColumn.
	nameExpr, err := (items.NameOnlyColumn{DB: s.db}).SelectExpr(schema.ItemTableName, lang)
	if err != nil {
		return nil, err
	}

	rows, err := sqSelect.
		SelectAppend(
			schema.ItemTableIDCol,
			nameExpr,
			schema.ItemTableBeginYearCol,
			schema.ItemTableEndYearCol,
			schema.ItemTableItemTypeIDCol,
			schema.ItemTableTodayCol,
		).
		Join(schema.ItemTable, goqu.On(schema.ItemPointTableItemIDCol.Eq(schema.ItemTableIDCol))).
		Executor().QueryContext(ctx) //nolint:sqlclosecheck

	if err != nil && !errors.Is(err, sql.ErrNoRows) {
		return nil, err
	}

	defer util.Close(rows)

	nameFormatter := items.NewItemNameFormatter(s.i18n)

	mapPoints := make([]*MapPoint, 0)

	for rows.Next() {
		var (
			point             schema.NullPoint
			id                int64
			name              string
			nullableBeginYear sql.NullInt32
			nullableEndYear   sql.NullInt32
			itemTypeID        schema.ItemTableItemTypeID
			today             sql.NullBool
		)

		err = rows.Scan(&point, &id, &name, &nullableBeginYear, &nullableEndYear, &itemTypeID, &today)
		if err != nil {
			return nil, err
		}

		var beginYear int32
		if nullableBeginYear.Valid {
			beginYear = nullableBeginYear.Int32
		}

		var endYear int32
		if nullableEndYear.Valid {
			endYear = nullableEndYear.Int32
		}

		var todayRef *bool
		if today.Valid {
			todayRef = &today.Bool
		}

		nameText, err := nameFormatter.FormatText(items.ItemNameFormatterOptions{
			Name:      name,
			BeginYear: beginYear,
			EndYear:   endYear,
			Today:     todayRef,
		}, lang)
		if err != nil {
			return nil, err
		}

		if point.Valid {
			mapPoint := &MapPoint{
				Id: fmt.Sprintf("factory%d", id),
				Location: &latlng.LatLng{
					Latitude:  point.Point.Y(),
					Longitude: point.Point.X(),
				},
				Name: nameText,
			}

			switch itemTypeID {
			case schema.ItemTableItemTypeIDFactory:
				mapPoint.Url = frontend.FactoryRoute(id)
			case schema.ItemTableItemTypeIDMuseum:
				mapPoint.Url = frontend.MuseumRoute(id)
			case schema.ItemTableItemTypeIDVehicle, schema.ItemTableItemTypeIDEngine,
				schema.ItemTableItemTypeIDCategory, schema.ItemTableItemTypeIDTwins,
				schema.ItemTableItemTypeIDBrand, schema.ItemTableItemTypeIDPerson, schema.ItemTableItemTypeIDCopyright:
			}

			var imageID sql.NullInt64

			success, err := s.db.Select(schema.PictureTableImageIDCol).
				From(schema.PictureTable).
				Join(schema.PictureItemTable, goqu.On(schema.PictureTableIDCol.Eq(schema.PictureItemTablePictureIDCol))).
				Where(
					schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted),
					schema.PictureItemTableItemIDCol.Eq(id),
				).
				ScanValContext(ctx, &imageID)
			if err != nil {
				return nil, err
			}

			if success && imageID.Valid {
				image, err := s.imageStorage.FormattedImage(ctx, int(imageID.Int64), "picture-thumb-medium")
				if err != nil {
					return nil, err
				}

				mapPoint.Image = APIImageToGRPC(image)
			}

			mapPoints = append(mapPoints, mapPoint)
		}
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	return mapPoints, nil
}

type pictureCell struct {
	count    int64
	avgLng   float64
	avgLat   float64
	sampleID int64
}

func (s *MapGRPCServer) pictureCellsInRange(
	ctx context.Context,
	lngLo, latLo, lngHi, latHi, cellLng, cellLat float64,
) ([]pictureCell, error) {
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

	var cells []pictureCell

	for rows.Next() {
		var cell pictureCell

		err = rows.Scan(&cell.count, &cell.avgLng, &cell.avgLat, &cell.sampleID)
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

// singlePicturePoints looks up identity/thumbnail for grid cells that contained exactly one
// picture. Thumbnails are looked up with FormattedImages (cache-only, batched) rather than the
// generate-on-demand FormattedImage used elsewhere in this file for factory thumbnails: this
// endpoint can touch far more pictures per request, and reuses picture-thumb-medium specifically
// because it's already generated for the large majority of pictures (used throughout picture
// listings app-wide) - a picture whose thumbnail isn't cached yet is shown without one rather than
// triggering fresh generation from a map viewport pan.
func (s *MapGRPCServer) singlePicturePoints(ctx context.Context, ids []int) ([]*MapPicturePoint, error) {
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

	type pictureRow struct {
		identity string
		point    schema.NullPoint
		imageID  sql.NullInt64
	}

	var pictureRows []pictureRow

	imageIDs := make([]int, 0, len(ids))

	for rows.Next() {
		var row pictureRow

		err = rows.Scan(&row.identity, &row.point, &row.imageID)
		if err != nil {
			return nil, err
		}

		pictureRows = append(pictureRows, row)

		if row.imageID.Valid {
			imageIDs = append(imageIDs, int(row.imageID.Int64))
		}
	}

	if err = rows.Err(); err != nil {
		return nil, err
	}

	thumbs := make(map[int]storage.Image)

	if len(imageIDs) > 0 {
		thumbs, err = s.imageStorage.FormattedImages(ctx, imageIDs, "picture-thumb-medium")
		if err != nil {
			return nil, err
		}
	}

	points := make([]*MapPicturePoint, 0, len(pictureRows))

	for _, row := range pictureRows {
		if !row.point.Valid {
			continue
		}

		picture := &MapSinglePicture{
			Identity: row.identity,
			Location: &latlng.LatLng{Latitude: row.point.Point.Y(), Longitude: row.point.Point.X()},
		}

		if row.imageID.Valid {
			if thumb, ok := thumbs[int(row.imageID.Int64)]; ok {
				picture.Thumb = APIImageToGRPC(&thumb)
			}
		}

		points = append(points, &MapPicturePoint{
			Kind: &MapPicturePoint_Picture{Picture: picture},
		})
	}

	return points, nil
}
