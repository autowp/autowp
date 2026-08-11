package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"strconv"
	"strings"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/i18nbundle"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/items"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/twpayne/go-geom"
	"github.com/twpayne/go-geom/encoding/wkt"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

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

func (s *MapGRPCServer) GetPoints(
	ctx context.Context,
	in *MapGetPointsRequest,
) (*MapPoints, error) {
	const numberOfBounds = 4

	bounds := strings.Split(in.GetBounds(), ",")

	if len(bounds) < numberOfBounds {
		return nil, status.Error(codes.InvalidArgument, "Invalid bounds")
	}

	const bitSize64 = 64

	lngLo, err := strconv.ParseFloat(bounds[0], bitSize64)
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	latLo, err := strconv.ParseFloat(bounds[1], bitSize64)
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	lngHi, err := strconv.ParseFloat(bounds[2], bitSize64)
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	latHi, err := strconv.ParseFloat(bounds[3], bitSize64)
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	pointsOnly := in.GetPointsOnly()

	polygon, err := geom.NewPolygon(geom.XY).SetCoords([][]geom.Coord{
		{{lngLo, latLo}, {lngLo, latHi}, {lngHi, latHi}, {lngHi, latLo}, {lngLo, latLo}},
	})
	if err != nil {
		return nil, status.Error(codes.InvalidArgument, err.Error())
	}

	polygon.SetSRID(schema.SRID)

	wktPolygon, err := wkt.Marshal(polygon)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	sqSelect := s.db.Select(schema.ItemPointTablePointCol).
		From(schema.ItemPointTable).
		Where(goqu.Func("ST_Intersects", goqu.Func("ST_GeogFromText", wktPolygon), schema.ItemPointTablePointCol))

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

func (s *MapGRPCServer) pointsWithContent(
	ctx context.Context,
	sqSelect *goqu.SelectDataset,
	lang string,
) ([]*MapPoint, error) {
	rows, err := sqSelect.
		SelectAppend(
			schema.ItemTableIDCol,
			schema.ItemTableNameCol,
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
