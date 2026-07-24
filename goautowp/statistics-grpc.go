package goautowp

import (
	"context"
	"database/sql"
	"errors"
	"math"
	"sync"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/sirupsen/logrus"
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
	thousands                           = 1000
	tensOfThousands                     = 10 * thousands
	numberOfTopUploadersToShowInAboutUs = 20
)

var errFailedToFetchRow = errors.New("failed to fetch row")

func roundTo(value int32, to int32) int32 {
	if rest := value % to; rest > to/2 {
		value = value - rest + to
	} else {
		value -= rest
	}

	return value
}

func unique(intSlice []string) []string {
	keys := make(map[string]bool)
	list := make([]string, 0, len(intSlice))

	for _, entry := range intSlice {
		if _, ok := keys[entry]; !ok {
			keys[entry] = true

			list = append(list, entry)
		}
	}

	return list
}

type StatisticsGRPCServer struct {
	UnimplementedStatisticsServer

	db          *goqu.Database
	lastColor   int
	aboutConfig config.AboutConfig
}

type scanRow struct {
	UserID int64   `db:"user_id"`
	Date   string  `db:"date"`
	Value  float32 `db:"value"`
}

type picturesStat struct {
	Count int32           `db:"count"`
	Size  sql.NullFloat64 `db:"size"`
}

func NewStatisticsGRPCServer(
	db *goqu.Database,
	aboutConfig config.AboutConfig,
) *StatisticsGRPCServer {
	return &StatisticsGRPCServer{
		db:          db,
		aboutConfig: aboutConfig,
	}
}

func (s *StatisticsGRPCServer) GetAboutData(
	ctx context.Context,
	_ *emptypb.Empty,
) (*AboutDataResponse, error) {
	response := AboutDataResponse{
		Developer:      s.aboutConfig.Developer,
		FrTranslator:   s.aboutConfig.FrTranslator,
		ZhTranslator:   s.aboutConfig.ZhTranslator,
		BeTranslator:   s.aboutConfig.BeTranslator,
		PtBrTranslator: s.aboutConfig.PtBrTranslator,
	}

	var wg sync.WaitGroup

	wg.Add(1)

	go func() {
		var err error

		response.TotalUsers, err = s.totalUsers(ctx)
		if err != nil {
			logrus.Error(err.Error())
		}

		wg.Done()
	}()

	wg.Add(1)

	go func() {
		var err error

		response.Contributors, err = s.contributors(ctx)
		if err != nil {
			logrus.Error(err.Error())
		}

		wg.Done()
	}()

	wg.Add(1)

	go func() {
		var err error

		response.TotalPictures, response.PicturesSize, err = s.picturesStat(ctx)
		if err != nil {
			logrus.Error(err.Error())
		}

		wg.Done()
	}()

	wg.Add(1)

	go func() {
		var err error

		response.TotalItems, err = s.totalItems(ctx)
		if err != nil {
			logrus.Error(err.Error())
		}

		wg.Done()
	}()

	wg.Add(1)

	go func() {
		var err error

		response.TotalComments, err = s.totalComments(ctx)
		if err != nil {
			logrus.Error(err.Error())
		}

		wg.Done()
	}()

	wg.Wait()

	return &response, nil
}

func (s *StatisticsGRPCServer) GetPulse(
	ctx context.Context,
	in *PulseRequest,
) (*PulseResponse, error) {
	var (
		now                          = time.Now()
		from, to                     time.Time
		subPeriodMonth, subPeriodDay int
		subPeriodTime                time.Duration
		format, dateExpr             string
	)

	switch in.GetPeriod() { //nolint: exhaustive
	case PulseRequest_YEAR:
		from = time.Now().AddDate(-1, 0, 0)
		from = time.Date(from.Year(), from.Month(), 1, 0, 0, 0, 0, from.Location())
		to = time.Date(now.Year(), now.Month()+1, 1, 0, 0, 0, 0, now.Location())
		subPeriodMonth = 1
		format = "2006-01"
		dateExpr = "YYYY-MM"

	case PulseRequest_MONTH:
		from = time.Now().AddDate(0, -1, 0)
		from = time.Date(from.Year(), from.Month(), from.Day(), 0, 0, 0, 0, from.Location())
		to = time.Date(now.Year(), now.Month(), now.Day()+1, 0, 0, 0, 0, now.Location())
		subPeriodDay = 1
		format = "2006-01-02"
		dateExpr = "YYYY-MM-DD"

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
		dateExpr = "YYYY-MM-DD HH24"
	}

	var rows []scanRow

	const dateAlias = "date"

	err := s.db.Select(
		schema.LogEventTableUserIDCol.As("user_id"),
		goqu.Func("TO_CHAR", schema.LogEventTableCreatedAtCol, dateExpr).As(dateAlias),
		goqu.COUNT(goqu.Star()).As("value"),
	).From(schema.LogEventTable).
		Where(
			schema.LogEventTableCreatedAtCol.Gte(from),
			schema.LogEventTableCreatedAtCol.Lt(to),
		).
		GroupBy(schema.LogEventTableUserIDCol, goqu.C(dateAlias)).ScanStructsContext(ctx, &rows)
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

	grid := make([]*PulseGrid, 0)
	legend := make([]*PulseLegend, 0)

	for uid, dates := range data {
		line := make([]float32, 0)

		cDate := from
		for to.After(cDate) {
			dateStr := cDate.Format(format)

			line = append(line, dates[dateStr])

			cDate = cDate.AddDate(0, subPeriodMonth, subPeriodDay).Add(subPeriodTime)
		}

		color := s.randomColor()

		grid = append(grid, &PulseGrid{
			Line:   line,
			Color:  color,
			UserId: uid,
		})

		legend = append(legend, &PulseLegend{
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

	return &PulseResponse{
		Grid:   grid,
		Legend: legend,
		Labels: labels,
	}, nil
}

func (s *StatisticsGRPCServer) randomColor() string {
	idx := s.lastColor % len(colors)
	s.lastColor++

	return colors[idx]
}

func (s *StatisticsGRPCServer) totalUsers(ctx context.Context) (int32, error) {
	result, err := s.db.From(schema.UserTable).
		Where(schema.UserTableDeletedCol.IsFalse()).
		CountContext(ctx)
	if err != nil {
		return 0, err
	}

	return roundTo(int32(result), thousands), nil //nolint: gosec
}

func (s *StatisticsGRPCServer) contributors(ctx context.Context) ([]string, error) {
	var contributors []string //nolint: prealloc

	err := s.db.Select(schema.UserTableIDCol).
		From(schema.UserTable).
		Where(
			schema.UserTableDeletedCol.IsFalse(),
			schema.UserTableGreenCol.IsTrue(),
			goqu.Or(
				schema.UserTableIdentityCol.IsNull(),
				schema.UserTableIdentityCol.Neq("autowp"),
			),
			schema.UserTableLastOnlineCol.Gt(goqu.L("CURRENT_DATE - INTERVAL '6 MONTH'")),
		).
		ScanValsContext(ctx, &contributors)
	if err != nil {
		return nil, err
	}

	var picturesUsers []string

	err = s.db.Select(schema.UserTableIDCol).
		From(schema.UserTable).
		Where(schema.UserTableDeletedCol.IsFalse()).
		Order(schema.UserTablePicturesTotalCol.Desc()).
		Limit(numberOfTopUploadersToShowInAboutUs).
		ScanValsContext(ctx, &picturesUsers)
	if err != nil {
		return nil, err
	}

	return unique(append(contributors, picturesUsers...)), nil
}

func (s *StatisticsGRPCServer) picturesStat(ctx context.Context) (int32, int32, error) {
	var picsStat picturesStat

	success, err := s.db.Select(
		goqu.COUNT(goqu.Star()).As("count"),
		goqu.L("SUM(filesize) / 1024 / 1024").As("size"),
	).
		From(schema.PictureTable).
		ScanStructContext(ctx, &picsStat)
	if err != nil {
		return 0, 0, err
	}

	if !success {
		return 0, 0, errFailedToFetchRow
	}

	return roundTo(picsStat.Count, tensOfThousands), int32(math.Round(picsStat.Size.Float64)), nil
}

func (s *StatisticsGRPCServer) totalItems(ctx context.Context) (int32, error) {
	result, err := s.db.From(schema.ItemTable).CountContext(ctx)
	if err != nil {
		return 0, err
	}

	return roundTo(int32(result), thousands), nil //nolint: gosec
}

func (s *StatisticsGRPCServer) totalComments(ctx context.Context) (int32, error) {
	result, err := s.db.From(schema.CommentMessageTable).
		Where(schema.CommentMessageTableDeletedCol.IsFalse()).
		CountContext(ctx)
	if err != nil {
		return 0, err
	}

	return roundTo(int32(result), thousands), nil //nolint: gosec
}
