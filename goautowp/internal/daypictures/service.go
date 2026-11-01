package daypictures

import (
	"context"
	"database/sql"
	"fmt"
	"time"

	"cloud.google.com/go/civil"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
)

type Service struct {
	currentDate        civil.Date
	listOptions        *query.PictureListOptions
	timezone           *time.Location
	minDate            time.Time
	dbTimezone         *time.Location
	prevDate           civil.Date
	nextDate           civil.Date
	picturesRepository *pictures.Repository
	column             string
}

func New(
	picturesRepository *pictures.Repository,
	column string,
	timezone *time.Location,
	listOptions *query.PictureListOptions,
	currentDate civil.Date,
) (*Service, error) {
	return &Service{
		timezone:           timezone,
		listOptions:        listOptions,
		dbTimezone:         time.UTC,
		currentDate:        currentDate,
		picturesRepository: picturesRepository,
		column:             column,
	}, nil
}

func (s *Service) HaveCurrentDate() bool {
	return !s.currentDate.IsZero()
}

func (s *Service) SetCurrentDateToLastIfEmptyDate(ctx context.Context) error {
	haveCurrentDayPictures, err := s.haveCurrentDayPictures(ctx)
	if err != nil {
		return err
	}

	if haveCurrentDayPictures {
		return nil
	}

	listOptions := *s.listOptions
	listOptions.Timezone = s.timezone

	orderBy := pictures.OrderByCreatedAtStrictDesc
	if s.column == schema.PictureTableAcceptDatetimeColName {
		orderBy = pictures.OrderByAcceptDatetimeStrictDesc
	}

	lastDate, err := s.calcDate(ctx, &listOptions, orderBy)
	if err != nil {
		return fmt.Errorf("calcDate(): %w", err)
	}

	s.currentDate = lastDate

	s.reset()

	return nil
}

func (s *Service) PrevDate(ctx context.Context) (civil.Date, error) {
	err := s.calcPrevDate(ctx)
	if err != nil {
		return s.prevDate, fmt.Errorf("calcPrevDate(): %w", err)
	}

	return s.prevDate, err
}

func (s *Service) NextDate(ctx context.Context) (civil.Date, error) {
	err := s.calcNextDate(ctx)
	if err != nil {
		return s.nextDate, fmt.Errorf("calcNextDate(): %w", err)
	}

	return s.nextDate, nil
}

func (s *Service) CurrentDate() civil.Date {
	return s.currentDate
}

func (s *Service) PrevDateCount(ctx context.Context) (int32, error) {
	err := s.calcPrevDate(ctx)
	if err != nil {
		return 0, fmt.Errorf("calcPrevDate(): %w", err)
	}

	count, err := s.dateCount(ctx, s.prevDate)
	if err != nil {
		return 0, fmt.Errorf("dateCount(%s): %w", s.prevDate.String(), err)
	}

	return count, nil
}

func (s *Service) CurrentDateCount(ctx context.Context) (int32, error) {
	count, err := s.dateCount(ctx, s.currentDate)
	if err != nil {
		return 0, fmt.Errorf("dateCount(%s): %w", s.currentDate.String(), err)
	}

	return count, nil
}

func (s *Service) NextDateCount(ctx context.Context) (int32, error) {
	err := s.calcNextDate(ctx)
	if err != nil {
		return 0, fmt.Errorf("calcNextDate(): %w", err)
	}

	count, err := s.dateCount(ctx, s.nextDate)
	if err != nil {
		return 0, fmt.Errorf("dateCount(%s): %w", s.nextDate.String(), err)
	}

	return count, nil
}

func (s *Service) haveCurrentDayPictures(ctx context.Context) (bool, error) {
	if s.currentDate.IsZero() {
		return false, nil
	}

	total, err := s.CurrentDateCount(ctx)
	if err != nil {
		return false, err
	}

	return total > 0, nil
}

func (s *Service) startOfDay(date time.Time) time.Time {
	return time.Date(date.Year(), date.Month(), date.Day(), 0, 0, 0, 0, date.Location())
}

func (s *Service) reset() {
	s.nextDate = civil.Date{}
	s.prevDate = civil.Date{}
}

func (s *Service) calcDate(
	ctx context.Context, listOptions *query.PictureListOptions, orderBy pictures.OrderBy,
) (civil.Date, error) {
	sqSelect, err := s.picturesRepository.PictureSelect(listOptions, nil, orderBy)
	if err != nil {
		return civil.Date{}, err
	}

	sqSelect = sqSelect.Select(goqu.T(query.PictureAlias).Col(s.column)).
		Where(goqu.T(query.PictureAlias).Col(s.column).IsNotNull()).
		Limit(1)

	var date sql.NullTime

	success, err := sqSelect.ScanValContext(ctx, &date)
	if err != nil {
		return civil.Date{}, err
	}

	if success && date.Valid {
		return civil.DateOf(date.Time.In(s.timezone)), nil
	}

	return civil.Date{}, nil
}

func (s *Service) calcPrevDate(ctx context.Context) error {
	if s.currentDate.IsZero() {
		return nil
	}

	if !s.prevDate.IsZero() {
		return nil
	}

	listOptions := *s.listOptions
	listOptions.Timezone = s.timezone
	orderBy := pictures.OrderByCreatedAtStrictDesc

	startOfDay := s.currentDate.In(s.timezone)
	minDate := s.startOfDay(s.minDate)

	if s.column == schema.PictureTableAcceptDatetimeColName {
		listOptions.AcceptDateLt = &startOfDay
		orderBy = pictures.OrderByAcceptDatetimeStrictDesc

		if !s.minDate.IsZero() {
			listOptions.AcceptDateGte = &minDate
		}
	} else {
		listOptions.CreatedAtLt = &startOfDay

		if !s.minDate.IsZero() {
			listOptions.CreatedAtGte = &minDate
		}
	}

	var err error

	s.prevDate, err = s.calcDate(ctx, &listOptions, orderBy)

	return err
}

func (s *Service) calcNextDate(ctx context.Context) error {
	if s.currentDate.IsZero() {
		return nil
	}

	if !s.nextDate.IsZero() {
		return nil
	}

	listOptions := *s.listOptions
	listOptions.Timezone = s.timezone
	startOfNextDay := s.currentDate.AddDays(1).In(s.timezone)
	orderBy := pictures.OrderByCreatedAtStrictAsc

	if s.column == schema.PictureTableAcceptDatetimeColName {
		listOptions.AcceptDateGte = &startOfNextDay
		orderBy = pictures.OrderByAcceptDatetimeStrictAsc
	} else {
		listOptions.CreatedAtGte = &startOfNextDay
	}

	var err error

	s.nextDate, err = s.calcDate(ctx, &listOptions, orderBy)

	return err
}

func (s *Service) dateCount(ctx context.Context, date civil.Date) (int32, error) {
	if date.IsZero() {
		return 0, nil
	}

	listOptions := *s.listOptions
	listOptions.Timezone = s.timezone

	if s.column == schema.PictureTableAcceptDatetimeColName {
		listOptions.AcceptDate = &date
	} else {
		listOptions.CreatedAt = &date
	}

	res, err := s.picturesRepository.Count(ctx, &listOptions)

	return int32(res), err //nolint: gosec
}
