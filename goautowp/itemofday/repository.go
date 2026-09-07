package itemofday

import (
	"context"
	"database/sql"
	"errors"
	"math/rand"
	"time"

	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
)

var errItemIDMustBeDefined = errors.New("itemID must be defined")

const (
	defaultMinPictures      = 3
	YoomoneyLabelDateFormat = time.DateOnly

	picturesCountAlias = "p_count"
	completeCountAlias = "p_complete_count"

	// The weighted-random pick in candidate() favors items whose accepted pictures have both an
	// author and a non-unknown licence ("fully documented"): weight ramps linearly from 1 (no
	// fully-documented pictures) to completeCountMaxWeight at completeCountCap such pictures, then
	// stops growing - a section with e.g. 40 fully-documented pictures gets no extra pull beyond
	// the cap over one with 10, so it can't come to dominate the daily pick. The 1->7 span over
	// [0,10] is chosen so a section with 5 fully-documented pictures - the point actually asked
	// for - lands at 4x the baseline weight.
	completeCountCap       = 10
	completeCountMaxWeight = 7.0
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

type weightedCandidate struct {
	ItemID        int64 `db:"id"`
	CompleteCount int64 `db:"p_complete_count"`
}

// completenessWeight is the weighted-random pick's relative pull for an item whose accepted
// pictures include completeCount "fully documented" ones (both a credited author and a
// non-unknown licence): a linear ramp from 1 (none) to completeCountMaxWeight at
// completeCountCap such pictures, capped beyond that so a section that happens to have e.g. 40
// fully-documented pictures gets no extra pull over one with 10 - it can't come to dominate the
// daily pick. The 1->7 span over [0,10] is chosen so 5 fully-documented pictures lands at 4x the
// baseline weight.
func completenessWeight(completeCount int64) float64 {
	if completeCount > completeCountCap {
		completeCount = completeCountCap
	}

	return 1 + (completeCountMaxWeight-1)*float64(completeCount)/float64(completeCountCap)
}

// pickWeighted draws one candidate with probability proportional to weight(candidate) - the
// standard cumulative-weight method: a single uniform draw over the total weight, then walk the
// running sum until it's exceeded. A plain uniform pick is the same thing with every weight equal.
func pickWeighted[T any](candidates []T, weight func(T) float64, rng *rand.Rand) (T, bool) {
	var zero T

	total := 0.0
	for _, c := range candidates {
		total += weight(c)
	}

	if total <= 0 {
		return zero, false
	}

	threshold := rng.Float64() * total

	sum := 0.0
	for _, c := range candidates {
		sum += weight(c)
		if threshold < sum {
			return c, true
		}
	}

	// Floating-point rounding can leave threshold a hair below total after the loop above -
	// the last candidate is the correct pick either way.
	return candidates[len(candidates)-1], true
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
		logging.Warning("ItemOfDay: candidate not found")

		return false, nil
	}

	logging.Infof("ItemOfDay: candidate is `%d`", itemID)

	return s.SetItemOfDay(ctx, time.Now(), itemID, 0)
}

func (s *Repository) CandidateQuery() *goqu.SelectDataset {
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
		Having(goqu.COUNT(goqu.DISTINCT(schema.PictureTableIDCol)).Gte(s.minPictures))

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
		Where(schema.OfDayTableDayDateCol.Lte(goqu.L("CURRENT_DATE"))).
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
	// A picture counts as "fully documented" once it has both a credited author (a picture_item
	// row of type Author - independent of the plain item/picture join in CandidateQuery, which
	// only ever matches the picture's content link) and a non-unknown licence.
	authorExists := s.db.Select(goqu.L("1")).
		From(schema.PictureItemTable).
		Where(
			schema.PictureItemTablePictureIDCol.Eq(schema.PictureTableIDCol),
			schema.PictureItemTableTypeCol.Eq(schema.PictureItemTypeAuthor),
		)

	// Replace CandidateQuery's projection rather than appending to it: weightedCandidate only
	// needs id and the complete-count, and goqu's ScanStructs errors on any returned column
	// (p_count) that has no matching struct field. The GROUP BY / HAVING from CandidateQuery
	// stay - HAVING refers to the aggregate expression itself, not the dropped alias.
	sqSelect := s.CandidateQuery().
		Where(goqu.Or(
			goqu.And(
				schema.ItemTableBeginYearCol.Gt(0),
				schema.ItemTableEndYearCol.Gt(0),
			),
			goqu.And(
				schema.ItemTableBeginModelYearCol.Gt(0),
				schema.ItemTableEndModelYearCol.Gt(0),
			),
		)).
		Select(
			schema.ItemTableIDCol,
			goqu.L(
				"COUNT(DISTINCT ?) FILTER (WHERE ? != ? AND EXISTS ?)",
				schema.PictureTableIDCol, schema.PictureTableLicenseIDCol, schema.PictureLicenseUnknown, authorExists,
			).As(completeCountAlias),
		)

	// The full candidate list is small (once-a-day job, over the count of catalogue items with
	// >= minPictures accepted pictures) - picking the weighted draw in Go is far simpler and more
	// testable than encoding it as a single SQL ORDER BY expression, and there's no latency budget
	// here worth trading that away for.
	var candidates []weightedCandidate

	err := sqSelect.Executor().ScanStructsContext(ctx, &candidates)
	if err != nil {
		return 0, err
	}

	chosen, found := pickWeighted(candidates, func(c weightedCandidate) float64 {
		return completenessWeight(c.CompleteCount)
	}, rand.New(rand.NewSource(time.Now().UnixNano()))) //nolint:gosec
	if !found {
		return 0, nil
	}

	return chosen.ItemID, nil
}
