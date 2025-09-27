package itemofday

import (
	"context"
	"database/sql"
	"errors"
	"time"

	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/sirupsen/logrus"
)

var errItemIDMustBeDefined = errors.New("itemID must be defined")

const (
	defaultMinPictures      = 3
	YoomoneyLabelDateFormat = time.DateOnly
)

type Repository struct {
	db          *goqu.Database
	loc         *time.Location
	minPictures int
}

type NextDate struct {
	Date time.Time
	Free bool
}

type CandidateRecord struct {
	ItemID int64 `db:"id"`
	Count  int64 `db:"p_count"`
}

func NewRepository(db *goqu.Database) *Repository {
	return &Repository{
		db:          db,
		loc:         time.UTC,
		minPictures: defaultMinPictures,
	}
}

func (s *Repository) SetMinPictures(value int) {
	s.minPictures = value
}

func (s *Repository) NextDates(ctx context.Context) ([]NextDate, error) {
	now := time.Now()
	now = time.Date(now.Year(), now.Month(), now.Day(), now.Hour(), now.Minute(), 0, 0, s.loc)

	result := make([]NextDate, 0)

	for range 10 {
		found := false

		_, err := s.db.Select(goqu.L("1")).From(schema.OfDayTable).Where(
			schema.OfDayTableDayDateCol.Eq(now.Format(time.DateOnly)),
			schema.OfDayTableItemIDCol.IsNotNull(),
		).ScanValContext(ctx, &found)
		if err != nil {
			return nil, err
		}

		result = append(result, NextDate{
			Date: now,
			Free: !found,
		})

		now = now.AddDate(0, 0, 1)
	}

	return result, nil
}

func (s *Repository) IsAvailableDate(ctx context.Context, date time.Time) (bool, error) {
	dateStr := date.Format(time.DateOnly)

	nextDates, err := s.NextDates(ctx)
	if err != nil {
		return false, err
	}

	for _, nextDate := range nextDates {
		if nextDate.Date.Format(time.DateOnly) == dateStr {
			return true, nil
		}
	}

	return false, nil
}

func (s *Repository) Pick(ctx context.Context) (bool, error) {
	itemID, err := s.candidate(ctx)
	if err != nil {
		return false, err
	}

	if itemID <= 0 {
		logrus.Warning("ItemOfDay: candidate not found")

		return false, nil
	}

	logrus.Infof("ItemOfDay: candidate is `%d`", itemID)

	return s.SetItemOfDay(ctx, time.Now(), itemID, 0)
}

func (s *Repository) CandidateQuery() *goqu.SelectDataset {
	const picturesCountAlias = "p_count"

	sqSelect := s.db.Select(
		schema.ItemTableIDCol,
		goqu.COUNT(goqu.DISTINCT(schema.PictureTableIDCol)).As(picturesCountAlias),
	).
		From(schema.ItemTable).
		Join(schema.ItemParentCacheTable, goqu.On(schema.ItemTableIDCol.Eq(schema.ItemParentCacheTableParentIDCol))).
		Join(schema.PictureItemTable, goqu.On(schema.ItemParentCacheTableItemIDCol.Eq(schema.PictureItemTableItemIDCol))).
		Join(schema.PictureTable, goqu.On(schema.PictureItemTablePictureIDCol.Eq(schema.PictureTableIDCol))).
		Where(
			schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted),
			schema.ItemTableIDCol.NotIn(
				s.db.Select(schema.OfDayTableItemIDCol).
					From(schema.OfDayTable).
					Where(schema.OfDayTableItemIDCol.IsNotNull()),
			),
		).
		GroupBy(schema.ItemTableIDCol).
		Having(goqu.C(picturesCountAlias).Gte(s.minPictures))

	return sqSelect
}

func (s *Repository) IsComplies(ctx context.Context, itemID int64) (bool, error) {
	if itemID == 0 {
		return false, errItemIDMustBeDefined
	}

	sqSelect := s.CandidateQuery().Where(schema.ItemTableIDCol.Eq(itemID))

	rec := CandidateRecord{}

	success, err := sqSelect.Executor().ScanStructContext(ctx, &rec)
	if err != nil {
		return false, err
	}

	if !success {
		return false, nil
	}

	return rec.ItemID != 0, nil
}

func (s *Repository) SetItemOfDay(
	ctx context.Context,
	dateTime time.Time,
	itemID int64,
	userID int64,
) (bool, error) {
	isComplies, err := s.IsComplies(ctx, itemID)
	if err != nil {
		return false, err
	}

	if !isComplies {
		return false, nil
	}

	dateStr := dateTime.Format(time.DateOnly)
	dateExpr := schema.OfDayTableDayDateCol.Eq(dateStr)

	sqSelect := s.db.Select(schema.OfDayTableItemIDCol).From(schema.OfDayTable).Where(dateExpr)

	var exists int64

	success, err := sqSelect.ScanValContext(ctx, &exists)
	if err != nil {
		return false, err
	}

	if success && exists > 0 {
		return false, nil
	}

	userIDVal := sql.NullInt64{
		Int64: userID,
		Valid: userID > 0,
	}

	if success {
		_, err = s.db.Update(schema.OfDayTable).Set(
			goqu.Record{
				schema.OfDayTableItemIDColName: itemID,
				schema.OfDayTableUserIDColName: userIDVal,
			},
		).
			Where(dateExpr).Executor().ExecContext(ctx)
	} else {
		_, err = s.db.Insert(schema.OfDayTable).Rows(
			goqu.Record{
				schema.OfDayTableItemIDColName:  itemID,
				schema.OfDayTableUserIDColName:  userIDVal,
				schema.OfDayTableDayDateColName: dateStr,
			},
		).Executor().ExecContext(ctx)
	}

	if err != nil {
		return false, err
	}

	return true, nil
}

func (s *Repository) Current(ctx context.Context) (*schema.OfDayRow, error) {
	var st schema.OfDayRow

	success, err := s.db.Select(schema.OfDayTableItemIDCol, schema.OfDayTableUserIDCol).
		From(schema.OfDayTable).
		Where(schema.OfDayTableDayDateCol.Lte(goqu.Func("CURDATE"))).
		Order(schema.OfDayTableDayDateCol.Desc()).
		Limit(1).
		ScanStructContext(ctx, &st)
	if err != nil {
		return nil, err
	}

	if !success {
		return nil, sql.ErrNoRows
	}

	return &st, nil
}

func (s *Repository) candidate(ctx context.Context) (int64, error) {
	sqSelect := s.CandidateQuery().
		Where(goqu.L(
			schema.ItemTableName + ".begin_year AND " + schema.ItemTableName + ".end_year OR " +
				schema.ItemTableName + ".begin_model_year AND " + schema.ItemTableName + ".end_model_year",
		)).
		Order(goqu.Func("RAND").Desc()).
		Limit(1)

	rec := CandidateRecord{}

	success, err := sqSelect.Executor().ScanStructContext(ctx, &rec)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, nil
	}

	return rec.ItemID, nil
}
