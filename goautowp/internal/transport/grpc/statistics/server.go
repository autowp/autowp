package statistics

import (
	"context"
	"math"
	"time"

	"github.com/autowp/goautowp/api/pb"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/statistics"
	"golang.org/x/sync/errgroup"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
)

var colors = []string{
	"#FF0000",
	"#00FF00",
	"#0000FF",
	"#FFFF00",
	"#FF00FF",
	"#00FFFF",
	"#880000",
	"#008800",
	"#000088",
	"#888800",
	"#880088",
	"#008888",
}

const (
	thousands       = 1000
	tensOfThousands = 10 * thousands
)

func roundTo(value int32, to int32) int32 {
	if rest := value % to; rest > to/2 {
		value = value - rest + to
	} else {
		value -= rest
	}

	return value
}

type Server struct {
	pb.UnimplementedStatisticsServer

	repository  *statistics.Repository
	lastColor   int
	aboutConfig config.AboutConfig
}

func NewServer(
	repository *statistics.Repository,
	aboutConfig config.AboutConfig,
) *Server {
	return &Server{
		repository:  repository,
		aboutConfig: aboutConfig,
	}
}

func (s *Server) GetAboutData(
	ctx context.Context,
	_ *emptypb.Empty,
) (*pb.AboutDataResponse, error) {
	response := pb.AboutDataResponse{
		Developer:      s.aboutConfig.Developer,
		FrTranslator:   s.aboutConfig.FrTranslator,
		ZhTranslator:   s.aboutConfig.ZhTranslator,
		BeTranslator:   s.aboutConfig.BeTranslator,
		PtBrTranslator: s.aboutConfig.PtBrTranslator,
	}

	group, lookupCtx := errgroup.WithContext(ctx)

	group.Go(func() error {
		var err error

		response.TotalUsers, err = s.totalUsers(lookupCtx)

		return err
	})

	group.Go(func() error {
		var err error

		response.Contributors, err = s.repository.Contributors(lookupCtx)

		return err
	})

	group.Go(func() error {
		var err error

		response.TotalPictures, response.PicturesSize, err = s.picturesStat(lookupCtx)

		return err
	})

	group.Go(func() error {
		var err error

		response.TotalItems, err = s.totalItems(lookupCtx)

		return err
	})

	group.Go(func() error {
		var err error

		response.TotalComments, err = s.totalComments(lookupCtx)

		return err
	})

	if err := group.Wait(); err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	return &response, nil
}

func (s *Server) GetPulse(
	ctx context.Context,
	in *pb.PulseRequest,
) (*pb.PulseResponse, error) {
	var (
		now                          = time.Now()
		from, to                     time.Time
		subPeriodMonth, subPeriodDay int
		subPeriodTime                time.Duration
		format                       string
		grouping                     = statistics.PulseGroupHour
	)

	switch in.GetPeriod() { //nolint: exhaustive
	case pb.PulseRequest_YEAR:
		from = time.Now().AddDate(-1, 0, 0)
		from = time.Date(from.Year(), from.Month(), 1, 0, 0, 0, 0, from.Location())
		to = time.Date(now.Year(), now.Month()+1, 1, 0, 0, 0, 0, now.Location())
		subPeriodMonth = 1
		format = "2006-01"
		grouping = statistics.PulseGroupMonth

	case pb.PulseRequest_MONTH:
		from = time.Now().AddDate(0, -1, 0)
		from = time.Date(from.Year(), from.Month(), from.Day(), 0, 0, 0, 0, from.Location())
		to = time.Date(now.Year(), now.Month(), now.Day()+1, 0, 0, 0, 0, now.Location())
		subPeriodDay = 1
		format = "2006-01-02"
		grouping = statistics.PulseGroupDay

	default:
		from = time.Now().AddDate(0, 0, -1)
		from = time.Date(
			from.Year(),
			from.Month(),
			from.Day(),
			from.Hour(),
			0,
			0,
			0,
			from.Location(),
		)
		to = time.Date(now.Year(), now.Month(), now.Day(), now.Hour()+1, 0, 0, 0, now.Location())
		subPeriodTime = time.Hour
		format = "2006-01-02 15"
	}

	rows, err := s.repository.Pulse(
		ctx,
		from,
		to,
		grouping,
	)
	if err != nil {
		return nil, status.Error(codes.Internal, err.Error())
	}

	data := make(map[int64]map[string]float32)
	for _, row := range rows {
		_, ok := data[row.UserID]
		if !ok {
			data[row.UserID] = make(map[string]float32)
		}

		data[row.UserID][row.Date] = row.Value
	}

	grid := make([]*pb.PulseGrid, 0)
	legend := make([]*pb.PulseLegend, 0)

	for uid, dates := range data {
		line := make([]float32, 0)

		cDate := from
		for to.After(cDate) {
			dateStr := cDate.Format(format)

			line = append(line, dates[dateStr])

			cDate = cDate.AddDate(0, subPeriodMonth, subPeriodDay).Add(subPeriodTime)
		}

		color := s.randomColor()

		grid = append(grid, &pb.PulseGrid{
			Line:   line,
			Color:  color,
			UserId: uid,
		})

		legend = append(legend, &pb.PulseLegend{
			UserId: uid,
			Color:  color,
		})
	}

	labels := make([]string, 0)
	cDate := from

	for to.After(cDate) {
		labels = append(labels, cDate.Format(format))

		cDate = cDate.AddDate(0, subPeriodMonth, subPeriodDay).Add(subPeriodTime)
	}

	return &pb.PulseResponse{
		Grid:   grid,
		Legend: legend,
		Labels: labels,
	}, nil
}

func (s *Server) randomColor() string {
	idx := s.lastColor % len(colors)
	s.lastColor++

	return colors[idx]
}

func (s *Server) totalUsers(ctx context.Context) (int32, error) {
	result, err := s.repository.TotalUsers(ctx)
	if err != nil {
		return 0, err
	}

	return roundTo(int32(result), thousands), nil //nolint: gosec
}

func (s *Server) picturesStat(ctx context.Context) (int32, int32, error) {
	picsStat, err := s.repository.PictureStats(ctx)
	if err != nil {
		return 0, 0, err
	}

	return roundTo(picsStat.Count, tensOfThousands), int32(math.Round(picsStat.Size.Float64)), nil
}

func (s *Server) totalItems(ctx context.Context) (int32, error) {
	result, err := s.repository.TotalItems(ctx)
	if err != nil {
		return 0, err
	}

	return roundTo(int32(result), thousands), nil //nolint: gosec
}

func (s *Server) totalComments(ctx context.Context) (int32, error) {
	result, err := s.repository.TotalComments(ctx)
	if err != nil {
		return 0, err
	}

	return roundTo(int32(result), thousands), nil //nolint: gosec
}
