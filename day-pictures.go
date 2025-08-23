package goautowp

import (
	"context"
	"database/sql"
	"fmt"
	"time"

	"cloud.google.com/go/civil"
	"github.com/autowp/goautowp/pictures"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

type DayPictures struct {
	currentDate        civil.Date
	listOptions        *query.PictureListOptions
	timezone           *time.Location
	minDate            time.Time
	dbTimezone         *time.Location
	dbDateTimeFormat   string
	prevDate           civil.Date
	nextDate           civil.Date
	picturesRepository *pictures.Repository
	column             string
}

func NewDayPictures(
	picturesRepository *pictures.Repository,
	column string,
	timezone *time.Location,
	listOptions *query.PictureListOptions,
	currentDate civil.Date,
) (*DayPictures, error) {
	return &DayPictures{
		timezone:           timezone,
		listOptions:        listOptions,
		dbTimezone:         time.UTC,
		dbDateTimeFormat:   time.DateTime,
		currentDate:        currentDate,
		picturesRepository: picturesRepository,
		column:             column,
	}, nil
}

func (s *DayPictures) HaveCurrentDate() bool {
	return !s.currentDate.IsZero()
}

func (s *DayPictures) SetCurrentDateToLastIfEmptyDate(ctx context.Context) error {
	haveCurrentDayPictures, err := s.haveCurrentDayPictures(ctx)
	if err != nil {
		return err
	}

	if haveCurrentDayPictures {
		return nil
	}

	listOptions := *s.listOptions
	listOptions.Timezone = s.timezone

	orderBy := pictures.OrderByAddDateStrictDesc
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

func (s *DayPictures) PrevDate(ctx context.Context) (civil.Date, error) {
	err := s.calcPrevDate(ctx)
	if err != nil {
		return s.prevDate, fmt.Errorf("calcPrevDate(): %w", err)
	}

	return s.prevDate, err
}

func (s *DayPictures) NextDate(ctx context.Context) (civil.Date, error) {
	err := s.calcNextDate(ctx)
	if err != nil {
		return s.nextDate, fmt.Errorf("calcNextDate(): %w", err)
	}

	return s.nextDate, nil
}

func (s *DayPictures) CurrentDate() civil.Date {
	return s.currentDate
}

func (s *DayPictures) PrevDateCount(ctx context.Context) (int32, error) {
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

func (s *DayPictures) CurrentDateCount(ctx context.Context) (int32, error) {
	count, err := s.dateCount(ctx, s.currentDate)
	if err != nil {
		return 0, fmt.Errorf("dateCount(%s): %w", s.currentDate.String(), err)
	}

	return count, nil
}

func (s *DayPictures) NextDateCount(ctx context.Context) (int32, error) {
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

func (s *DayPictures) haveCurrentDayPictures(ctx context.Context) (bool, error) {
	if s.currentDate.IsZero() {
		return false, nil
	}

	total, err := s.CurrentDateCount(ctx)
	if err != nil {
		return false, err
	}

	return total > 0, nil
}

func (s *DayPictures) startOfDay(date time.Time) time.Time {
	return time.Date(date.Year(), date.Month(), date.Day(), 0, 0, 0, 0, date.Location())
}

func (s *DayPictures) reset() {
	s.nextDate = civil.Date{}
	s.prevDate = civil.Date{}
}

func (s *DayPictures) calcDate(
	ctx context.Context, listOptions *query.PictureListOptions, orderBy pictures.OrderBy,
) (civil.Date, error) {
	sqSelect, err := s.picturesRepository.PictureSelect(listOptions, nil, orderBy)
	if err != nil {
		return civil.Date{}, err
	}

	var date sql.NullTime

	success, err := sqSelect.Select(goqu.T(query.PictureAlias).Col(s.column)).
		Limit(1).
		ScanValContext(ctx, &date)
	if err != nil {
		return civil.Date{}, err
	}

	if success && date.Valid {
		return civil.DateOf(date.Time.In(s.timezone)), nil
	}

	return civil.Date{}, nil
}

func (s *DayPictures) calcPrevDate(ctx context.Context) error {
	if s.currentDate.IsZero() {
		return nil
	}

	if !s.prevDate.IsZero() {
		return nil
	}

	listOptions := *s.listOptions
	listOptions.Timezone = s.timezone
	orderBy := pictures.OrderByAddDateStrictDesc

	startOfDay := s.currentDate.In(s.timezone)
	minDate := s.startOfDay(s.minDate)

	if s.column == schema.PictureTableAcceptDatetimeColName {
		listOptions.AcceptDateLt = &startOfDay
		orderBy = pictures.OrderByAcceptDatetimeStrictDesc

		if !s.minDate.IsZero() {
			listOptions.AcceptDateGte = &minDate
		}
	} else {
		listOptions.AddDateLt = &startOfDay

		if !s.minDate.IsZero() {
			listOptions.AddDateGte = &minDate
		}
	}

	var err error

	s.prevDate, err = s.calcDate(ctx, &listOptions, orderBy)

	return err
}

func (s *DayPictures) calcNextDate(ctx context.Context) error {
	if s.currentDate.IsZero() {
		return nil
	}

	if !s.nextDate.IsZero() {
		return nil
	}

	listOptions := *s.listOptions
	listOptions.Timezone = s.timezone
	startOfNextDay := s.currentDate.AddDays(1).In(s.timezone)
	orderBy := pictures.OrderByAddDateStrictAsc

	if s.column == schema.PictureTableAcceptDatetimeColName {
		listOptions.AcceptDateGte = &startOfNextDay
		orderBy = pictures.OrderByAcceptDatetimeStrictAsc
	} else {
		listOptions.AddDateGte = &startOfNextDay
	}

	var err error

	s.nextDate, err = s.calcDate(ctx, &listOptions, orderBy)

	return err
}

func (s *DayPictures) dateCount(ctx context.Context, date civil.Date) (int32, error) {
	if date.IsZero() {
		return 0, nil
	}

	listOptions := *s.listOptions
	listOptions.Timezone = s.timezone

	if s.column == schema.PictureTableAcceptDatetimeColName {
		listOptions.AcceptDate = &date
	} else {
		listOptions.AddDate = &date
	}

	res, err := s.picturesRepository.Count(ctx, &listOptions)

	return int32(res), err //nolint: gosec
}
