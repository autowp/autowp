package achievements

import (
	"context"
	"database/sql"
	"errors"

	"github.com/autowp/goautowp/frontend"
	"github.com/autowp/goautowp/hosts"
	"github.com/autowp/goautowp/messaging"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/users"
	"github.com/doug-martin/goqu/v9"
)

const (
	achievementGrantedMessageID     = "pm/achievement-granted"
	achievementMessageProfileURLKey = "ProfileURL"

	topPicturesContributorLimit = 10
)

// Metric identifies one of the four persisted, ever-incrementing per-user counters in
// user_achievement_progress. Distinct from achievement `code`, which is per-tier — a
// metric spans an entire 5-rung series.
type Metric string

const (
	MetricPictureInspector Metric = "picture-inspector"
	MetricPictureBuster    Metric = "picture-buster"
	MetricSpecMaster       Metric = "spec-master"
	MetricCommentator      Metric = "commentator"
)

// tier is the shared shape for every 5-rung series (Picture Inspector, Picture Buster,
// Spec Master, Commentator), using the same Bronze/Silver/Gold/Platinum/Diamond ladder
// as most ranked-game tier systems. code is the achievement code for this specific rung —
// used both to look up the frontend icon/name and, in Progress, to report which code a
// user is working toward next.
type tier struct {
	threshold     int64
	achievementID int32
	code          string
}

var pictureInspectorTiers = []tier{
	{100, schema.AchievementIDPictureInspectorBronze, "picture-inspector-bronze"},
	{1000, schema.AchievementIDPictureInspectorSilver, "picture-inspector-silver"},
	{10000, schema.AchievementIDPictureInspectorGold, "picture-inspector-gold"},
	{100000, schema.AchievementIDPictureInspectorPlatinum, "picture-inspector-platinum"},
	{1000000, schema.AchievementIDPictureInspectorDiamond, "picture-inspector-diamond"},
}

var pictureBusterTiers = []tier{
	{100, schema.AchievementIDPictureBusterBronze, "picture-buster-bronze"},
	{1000, schema.AchievementIDPictureBusterSilver, "picture-buster-silver"},
	{10000, schema.AchievementIDPictureBusterGold, "picture-buster-gold"},
	{100000, schema.AchievementIDPictureBusterPlatinum, "picture-buster-platinum"},
	{1000000, schema.AchievementIDPictureBusterDiamond, "picture-buster-diamond"},
}

var specMasterTiers = []tier{
	{100, schema.AchievementIDSpecMasterBronze, "spec-master-bronze"},
	{1000, schema.AchievementIDSpecMasterSilver, "spec-master-silver"},
	{10000, schema.AchievementIDSpecMasterGold, "spec-master-gold"},
	{100000, schema.AchievementIDSpecMasterPlatinum, "spec-master-platinum"},
	{1000000, schema.AchievementIDSpecMasterDiamond, "spec-master-diamond"},
}

var commentatorTiers = []tier{
	{100, schema.AchievementIDCommentatorBronze, "commentator-bronze"},
	{1000, schema.AchievementIDCommentatorSilver, "commentator-silver"},
	{10000, schema.AchievementIDCommentatorGold, "commentator-gold"},
	{100000, schema.AchievementIDCommentatorPlatinum, "commentator-platinum"},
	{1000000, schema.AchievementIDCommentatorDiamond, "commentator-diamond"},
}

// tieredSeries maps each metric to its 5-rung ladder, so Progress can iterate all four
// generically instead of hand-rolling four near-identical blocks.
var tieredSeries = map[Metric][]tier{
	MetricPictureInspector: pictureInspectorTiers,
	MetricPictureBuster:    pictureBusterTiers,
	MetricSpecMaster:       specMasterTiers,
	MetricCommentator:      commentatorTiers,
}

type Repository struct {
	db                  *goqu.Database
	usersRepository     *users.Repository
	messagingRepository *messaging.Repository
	hostManager         *hosts.Manager
}

func NewRepository(
	db *goqu.Database,
	usersRepository *users.Repository,
	messagingRepository *messaging.Repository,
	hostManager *hosts.Manager,
) *Repository {
	return &Repository{
		db:                  db,
		usersRepository:     usersRepository,
		messagingRepository: messagingRepository,
		hostManager:         hostManager,
	}
}

// Grant is the single idempotent, permanent grant primitive every call site funnels
// through. Deleted users never earn or hold an achievement row — checked here, once, so
// no individual call site (or the historical backfill migration) needs to remember this
// rule separately. Returns granted=true only the first time this (userID, achievementID)
// pair is inserted for a non-deleted user; false otherwise (already granted, or the user
// is deleted/missing). Sends the congratulation message only when newly granted.
func (s *Repository) Grant(ctx context.Context, userID int64, achievementID int32) (bool, error) {
	notDeleted := false

	user, err := s.usersRepository.User(
		ctx, &query.UserListOptions{ID: userID, Deleted: &notDeleted}, users.UserFields{}, users.OrderByNone,
	)
	if err != nil {
		if errors.Is(err, users.ErrUserNotFound) {
			return false, nil // deleted or nonexistent — never grant
		}

		return false, err
	}

	res, err := s.db.Insert(schema.UserAchievementTable).Rows(goqu.Record{
		schema.UserAchievementTableUserIDColName:        userID,
		schema.UserAchievementTableAchievementIDColName: achievementID,
		schema.UserAchievementTableCreatedAtColName:     goqu.Func("NOW"),
	}).OnConflict(goqu.DoNothing()).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	affected, err := res.RowsAffected()
	if err != nil {
		return false, err
	}

	granted := affected > 0
	if granted {
		if err := s.notifyGranted(ctx, user); err != nil {
			return granted, err
		}
	}

	return granted, nil
}

// GrantPictureAccepted runs once per picture that newly transitions to accepted status —
// call only when isFirstTimeAccepted is true, so re-accepting a picture never double-counts.
func (s *Repository) GrantPictureAccepted(ctx context.Context, ownerID sql.NullInt64, moderatorID int64) error {
	if ownerID.Valid {
		if _, err := s.Grant(ctx, ownerID.Int64, schema.AchievementIDPicturesContributor); err != nil {
			return err
		}
	}

	return s.incrementAndGrant(ctx, moderatorID, MetricPictureInspector, pictureInspectorTiers)
}

// GrantPictureQueuedForRemoval is called from pictures.Repository on every successful
// QueueRemove call, including the incidental one inside AcceptReplacePicture — broader
// in scope than what the migration-26 historical backfill can approximate from log_event
// (that can only identify the direct-queue action). Accepted trade-off, see
// openspec/changes/add-achievements/design.md.
func (s *Repository) GrantPictureQueuedForRemoval(ctx context.Context, moderatorID int64) error {
	return s.incrementAndGrant(ctx, moderatorID, MetricPictureBuster, pictureBusterTiers)
}

// GrantSpecValueSet is called from attrs.Repository once per successful SetUserValue
// call that actually wrote a new-or-changed value (gated by the caller, not here).
func (s *Repository) GrantSpecValueSet(ctx context.Context, userID int64) error {
	return s.incrementAndGrant(ctx, userID, MetricSpecMaster, specMasterTiers)
}

// GrantCommentPosted is called from comments.Repository once per successful Add call.
func (s *Repository) GrantCommentPosted(ctx context.Context, authorID int64) error {
	return s.incrementAndGrant(ctx, authorID, MetricCommentator, commentatorTiers)
}

// RecomputeTopPicturesContributors is the daily scheduled leaderboard check.
func (s *Repository) RecomputeTopPicturesContributors(ctx context.Context) (int, error) {
	falseRef, trueRef := false, true

	rows, _, err := s.usersRepository.Users(ctx, &query.UserListOptions{
		Deleted:     &falseRef,
		Limit:       topPicturesContributorLimit,
		HasPictures: &trueRef,
	}, users.UserFields{}, users.OrderByPicturesTotalDesc)
	if err != nil {
		return 0, err
	}

	grantedCount := 0

	for _, row := range rows {
		granted, err := s.Grant(ctx, row.ID, schema.AchievementIDTopPicturesContributor)
		if err != nil {
			return grantedCount, err
		}

		if granted {
			grantedCount++
		}
	}

	return grantedCount, nil
}

// RecomputeVeteranBadges is the daily scheduled check for the time-based Veteran
// achievement (10+ years since registration) — like the leaderboard, this can't be
// event-triggered since nothing "happens" at the 10-year mark.
func (s *Repository) RecomputeVeteranBadges(ctx context.Context) (int, error) {
	falseRef := false

	var userIDs []int64

	err := s.db.Select(schema.UserTableIDCol).From(schema.UserTable).Where(
		schema.UserTableDeletedCol.Eq(falseRef),
		schema.UserTableRegDateCol.IsNotNull(),
		schema.UserTableRegDateCol.Lte(goqu.L("NOW() - INTERVAL '10 years'")),
	).ScanValsContext(ctx, &userIDs)
	if err != nil {
		return 0, err
	}

	grantedCount := 0

	for _, userID := range userIDs {
		granted, err := s.Grant(ctx, userID, schema.AchievementIDVeteran)
		if err != nil {
			return grantedCount, err
		}

		if granted {
			grantedCount++
		}
	}

	return grantedCount, nil
}

// SeriesProgress reports how far a user has gotten toward the next tier they haven't
// earned yet in one of the four 5-rung series. Code is that next tier's achievement code
// (reusing the same code -> icon/name frontend mapping as earned badges, just for a badge
// not yet earned).
type SeriesProgress struct {
	Code      string
	Current   int64
	Threshold int64
}

// Progress computes, for each tiered series, the next not-yet-earned tier and how close
// userID is to it — one query against the persisted user_achievement_progress counters,
// not four live COUNTs against source tables. A series is omitted entirely if the user
// has no counter row at all (never done that action) — this is what keeps a random
// visitor's profile from showing "0/100 Bronze Picture Inspector"; a non-moderator has
// no `picture-inspector` row, so Picture Inspector/Picture Buster progress only ever
// appears for users who've actually moderated. Also omitted if every tier in the series is already earned
// (maxed out). "Top pictures contributor" and "Veteran" are intentionally not part of
// this (rank-relative / date-based, no clean incremental "current/threshold" to show).
func (s *Repository) Progress(
	ctx context.Context, userID int64, earnedCodes map[string]bool,
) ([]SeriesProgress, error) {
	var counters []schema.UserAchievementProgressRow

	err := s.db.Select(schema.UserAchievementProgressTableMetricCol, schema.UserAchievementProgressTableCountCol).
		From(schema.UserAchievementProgressTable).
		Where(schema.UserAchievementProgressTableUserIDCol.Eq(userID)).
		ScanStructsContext(ctx, &counters)
	if err != nil {
		return nil, err
	}

	var result []SeriesProgress

	for _, counter := range counters {
		tiers, ok := tieredSeries[Metric(counter.Metric)]
		if !ok {
			continue
		}

		for _, rung := range tiers {
			if earnedCodes[rung.code] {
				continue
			}

			if counter.Count < rung.threshold {
				result = append(
					result,
					SeriesProgress{Code: rung.code, Current: counter.Count, Threshold: rung.threshold},
				)
			}

			break // first not-yet-earned tier is "next"; stop scanning this series either way
		}
	}

	return result, nil
}

// UserAchievementsResult is what powers the public profile page — no auth; any visitor
// may request it for any user. Earned and Progress are both keyed by achievement code so
// the frontend can use one code -> icon/name lookup for both earned badges and in-progress
// ones.
type UserAchievementsResult struct {
	Earned   []schema.UserAchievementCodeRow
	Progress []SeriesProgress
}

func (s *Repository) UserAchievements(ctx context.Context, userID int64) (*UserAchievementsResult, error) {
	var earned []schema.UserAchievementCodeRow

	err := s.db.Select(schema.AchievementTableCodeCol, schema.UserAchievementTableCreatedAtCol).
		From(schema.UserAchievementTable).
		Join(schema.AchievementTable, goqu.On(schema.UserAchievementTableAchievementIDCol.Eq(schema.AchievementTableIDCol))).
		Where(schema.UserAchievementTableUserIDCol.Eq(userID)).
		Order(schema.AchievementTableIDCol.Asc()).
		ScanStructsContext(ctx, &earned)
	if err != nil {
		return nil, err
	}

	earnedCodes := make(map[string]bool, len(earned))
	for _, row := range earned {
		earnedCodes[row.Code] = true
	}

	progress, err := s.Progress(ctx, userID, earnedCodes)
	if err != nil {
		return nil, err
	}

	return &UserAchievementsResult{Earned: earned, Progress: progress}, nil
}

// AchievementCounts powers the /achievements catalog page's "N users have earned this"
// display — no auth, public. LEFT JOIN so an achievement nobody has earned yet still
// appears with count 0 rather than being omitted.
func (s *Repository) AchievementCounts(ctx context.Context) ([]schema.AchievementCountRow, error) {
	var rows []schema.AchievementCountRow

	err := s.db.Select(
		schema.AchievementTableCodeCol,
		goqu.COUNT(schema.UserAchievementTableUserIDCol).As("count"),
	).
		From(schema.AchievementTable).
		LeftJoin(schema.UserAchievementTable, goqu.On(
			schema.AchievementTableIDCol.Eq(schema.UserAchievementTableAchievementIDCol),
		)).
		GroupBy(schema.AchievementTableIDCol).
		Order(schema.AchievementTableIDCol.Asc()).
		ScanStructsContext(ctx, &rows)

	return rows, err
}

// notifyGranted takes the already-fetched user (Grant just looked it up to check
// Deleted) rather than re-querying.
func (s *Repository) notifyGranted(ctx context.Context, user *schema.UsersRow) error {
	uri, err := s.hostManager.URIByLanguage(user.Language)
	if err != nil {
		return err
	}

	profileURL := frontend.UserURL(uri, user.ID, user.Identity) + "#achievements"

	return s.messagingRepository.CreateMessageFromTemplate(
		ctx, 0, user.ID, achievementGrantedMessageID,
		map[string]interface{}{achievementMessageProfileURLKey: profileURL},
		user.Language,
	)
}

// incrementAndGrant atomically bumps the persisted user_achievement_progress counter for
// (userID, metric) by 1 and Grants every tier the new total has reached. Chosen over a
// live COUNT(*) against each series' source table on every action: it's O(1) instead of
// a table/log scan per action, it doesn't depend on the source row's later state (e.g.
// picture.status moving on from 'removing'), and it durably persists per-user progress
// for a future rating/leaderboard feature to query directly. The counter only ever
// increases — never recomputed from source tables after the migration-26 backfill seed.
func (s *Repository) incrementAndGrant(ctx context.Context, userID int64, metric Metric, tiers []tier) error {
	var count int64

	_, err := s.db.Insert(schema.UserAchievementProgressTable).Rows(goqu.Record{
		schema.UserAchievementProgressTableUserIDColName:    userID,
		schema.UserAchievementProgressTableMetricColName:    string(metric),
		schema.UserAchievementProgressTableCountColName:     1,
		schema.UserAchievementProgressTableUpdatedAtColName: goqu.Func("NOW"),
	}).OnConflict(goqu.DoUpdate(
		schema.UserAchievementProgressTableUserIDColName+","+schema.UserAchievementProgressTableMetricColName,
		goqu.Record{
			schema.UserAchievementProgressTableCountColName: goqu.L(
				"? + 1", schema.UserAchievementProgressTableCountCol,
			),
			schema.UserAchievementProgressTableUpdatedAtColName: goqu.Func("NOW"),
		},
	)).Returning(schema.UserAchievementProgressTableCountCol).Executor().ScanValContext(ctx, &count)
	if err != nil {
		return err
	}

	for _, rung := range tiers {
		if count < rung.threshold {
			break // ascending; below one rung means below every higher rung too
		}

		if _, err := s.Grant(ctx, userID, rung.achievementID); err != nil {
			return err
		}
	}

	return nil
}
