package maps

import (
	"context"
	"fmt"
	"strconv"
	"strings"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/frontend"
	"github.com/autowp/goautowp/internal/i18nbundle"
	"github.com/autowp/goautowp/internal/image/storage"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/maps"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/transport/grpc/imagemapper"
	"google.golang.org/genproto/googleapis/type/latlng"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

type Server struct {
	pb.UnimplementedMapServer

	repository   *maps.Repository
	imageStorage *storage.Storage
	i18n         *i18nbundle.I18n
}

func NewServer(repository *maps.Repository, imageStorage *storage.Storage, i18n *i18nbundle.I18n) *Server {
	return &Server{repository: repository, imageStorage: imageStorage, i18n: i18n}
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

func (s *Server) GetPoints(ctx context.Context, in *pb.MapGetPointsRequest) (*pb.MapPoints, error) {
	lngLo, latLo, lngHi, latHi, err := parseMapBounds(in.GetBounds())
	if err != nil {
		return nil, err
	}

	if in.GetPointsOnly() {
		rows, err := s.repository.ItemPoints(ctx, lngLo, latLo, lngHi, latHi)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		points := make([]*pb.MapPoint, 0, len(rows))
		for _, point := range rows {
			if point.Valid {
				points = append(
					points,
					&pb.MapPoint{Location: &latlng.LatLng{Latitude: point.Point.Y(), Longitude: point.Point.X()}},
				)
			}
		}

		return &pb.MapPoints{Points: points}, nil
	}

	rows, err := s.repository.ItemPointsWithContent(ctx, lngLo, latLo, lngHi, latHi, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	points, err := s.pointsWithContent(ctx, rows, in.GetLanguage())
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &pb.MapPoints{Points: points}, nil
}

func (s *Server) GetPicturePoints(
	ctx context.Context,
	in *pb.MapGetPicturePointsRequest,
) (*pb.MapPicturePoints, error) {
	lngLo, latLo, lngHi, latHi, err := parseMapBounds(in.GetBounds())
	if err != nil {
		return nil, err
	}

	if in.GetIndividual() {
		ids, err := s.repository.IndividualPictureIDs(ctx, lngLo, latLo, lngHi, latHi)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		points, err := s.singlePicturePoints(ctx, ids)
		if err != nil {
			return nil, status.Error(codes.Internal, err.Error())
		}

		return &pb.MapPicturePoints{Points: points}, nil
	}

	cells, err := s.repository.PictureCells(ctx, lngLo, latLo, lngHi, latHi)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	points := make([]*pb.MapPicturePoint, 0, len(cells))
	singletonIDs := make([]int, 0, len(cells))

	for _, cell := range cells {
		if cell.Count == 1 {
			singletonIDs = append(singletonIDs, int(cell.SampleID))

			continue
		}

		points = append(points, &pb.MapPicturePoint{
			Kind: &pb.MapPicturePoint_Cluster{
				Cluster: &pb.MapPictureCluster{
					Location: &latlng.LatLng{Latitude: cell.AvgLat, Longitude: cell.AvgLng},
					Count:    int32(cell.Count), //nolint: gosec
				},
			},
		})
	}

	singlePoints, err := s.singlePicturePoints(ctx, singletonIDs)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	points = append(points, singlePoints...)

	return &pb.MapPicturePoints{Points: points}, nil
}

func (s *Server) pointsWithContent(
	ctx context.Context,
	rows []maps.ItemPoint,
	lang string,
) ([]*pb.MapPoint, error) {
	nameFormatter := items.NewItemNameFormatter(s.i18n)

	mapPoints := make([]*pb.MapPoint, 0)

	for _, row := range rows {
		var beginYear int32
		if row.BeginYear.Valid {
			beginYear = row.BeginYear.Int32
		}

		var endYear int32
		if row.EndYear.Valid {
			endYear = row.EndYear.Int32
		}

		var todayRef *bool
		if row.Today.Valid {
			todayRef = &row.Today.Bool
		}

		nameText, err := nameFormatter.FormatText(items.ItemNameFormatterOptions{
			Name:      row.Name,
			BeginYear: beginYear,
			EndYear:   endYear,
			Today:     todayRef,
		}, lang)
		if err != nil {
			return nil, err
		}

		if row.Point.Valid {
			mapPoint := &pb.MapPoint{
				Id: fmt.Sprintf("factory%d", row.ID),
				Location: &latlng.LatLng{
					Latitude:  row.Point.Point.Y(),
					Longitude: row.Point.Point.X(),
				},
				Name: nameText,
			}

			switch row.ItemTypeID {
			case schema.ItemTableItemTypeIDFactory:
				mapPoint.Url = frontend.FactoryRoute(row.ID)
			case schema.ItemTableItemTypeIDMuseum:
				mapPoint.Url = frontend.MuseumRoute(row.ID)
			case schema.ItemTableItemTypeIDVehicle, schema.ItemTableItemTypeIDEngine,
				schema.ItemTableItemTypeIDCategory, schema.ItemTableItemTypeIDTwins,
				schema.ItemTableItemTypeIDBrand, schema.ItemTableItemTypeIDPerson, schema.ItemTableItemTypeIDCopyright:
			}

			imageID, err := s.repository.ItemImageID(ctx, row.ID)
			if err != nil {
				return nil, err
			}

			if imageID.Valid {
				image, err := s.imageStorage.FormattedImage(ctx, int(imageID.Int64), "picture-thumb-medium")
				if err != nil {
					return nil, err
				}

				mapPoint.Image = imagemapper.FromStorage(image)
			}

			mapPoints = append(mapPoints, mapPoint)
		}
	}

	return mapPoints, nil
}

// singlePicturePoints uses cached, batched thumbnails to avoid generating images on each viewport pan.
func (s *Server) singlePicturePoints(ctx context.Context, ids []int) ([]*pb.MapPicturePoint, error) {
	pictureRows, err := s.repository.Pictures(ctx, ids)
	if err != nil {
		return nil, err
	}

	imageIDs := make([]int, 0, len(pictureRows))
	for _, row := range pictureRows {
		if row.ImageID.Valid {
			imageIDs = append(imageIDs, int(row.ImageID.Int64))
		}
	}

	thumbs := make(map[int]storage.Image)

	if len(imageIDs) > 0 {
		var err error

		thumbs, err = s.imageStorage.FormattedImages(ctx, imageIDs, "picture-thumb-medium")
		if err != nil {
			return nil, err
		}
	}

	points := make([]*pb.MapPicturePoint, 0, len(pictureRows))

	for _, row := range pictureRows {
		if !row.Point.Valid {
			continue
		}

		picture := &pb.MapSinglePicture{
			Identity: row.Identity,
			Location: &latlng.LatLng{Latitude: row.Point.Point.Y(), Longitude: row.Point.Point.X()},
		}

		if row.ImageID.Valid {
			if thumb, ok := thumbs[int(row.ImageID.Int64)]; ok {
				picture.Thumb = imagemapper.FromStorage(&thumb)
			}
		}

		points = append(points, &pb.MapPicturePoint{
			Kind: &pb.MapPicturePoint_Picture{Picture: picture},
		})
	}

	return points, nil
}
