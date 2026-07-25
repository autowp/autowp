package schema

import (
	"time"

	"github.com/doug-martin/goqu/v9"
)

const (
	AchievementTableName         = "achievement"
	AchievementTableIDColName    = "id"
	AchievementTableCodeColName  = "code"
	AchievementTableLabelColName = "label"

	AchievementIDPicturesContributor     = 1
	AchievementIDTopPicturesContributor  = 2
	AchievementIDInspectorRookie         = 3
	AchievementIDInspectorPracticing     = 4
	AchievementIDInspectorRegular        = 5
	AchievementIDInspectorExpert         = 6
	AchievementIDInspectorGod            = 7
	AchievementIDPictureBusterRookie     = 8
	AchievementIDPictureBusterPracticing = 9
	AchievementIDPictureBusterRegular    = 10
	AchievementIDPictureBusterExpert     = 11
	AchievementIDPictureBusterGod        = 12
	AchievementIDSpecMasterRookie        = 13
	AchievementIDSpecMasterPracticing    = 14
	AchievementIDSpecMasterRegular       = 15
	AchievementIDSpecMasterExpert        = 16
	AchievementIDSpecMasterGod           = 17
	AchievementIDCommentatorRookie       = 18
	AchievementIDCommentatorPracticing   = 19
	AchievementIDCommentatorRegular      = 20
	AchievementIDCommentatorExpert       = 21
	AchievementIDCommentatorGod          = 22
	AchievementIDVeteran                 = 23
)

var (
	AchievementTable         = goqu.T(AchievementTableName)
	AchievementTableIDCol    = AchievementTable.Col(AchievementTableIDColName)
	AchievementTableCodeCol  = AchievementTable.Col(AchievementTableCodeColName)
	AchievementTableLabelCol = AchievementTable.Col(AchievementTableLabelColName)
)

type AchievementRow struct {
	ID    int32  `db:"id"`
	Code  string `db:"code"`
	Label string `db:"label"`
}

const (
	UserAchievementTableName                 = "user_achievement"
	UserAchievementTableUserIDColName        = "user_id"
	UserAchievementTableAchievementIDColName = "achievement_id"
	UserAchievementTableCreatedAtColName     = "created_at"
)

var (
	UserAchievementTable                 = goqu.T(UserAchievementTableName)
	UserAchievementTableUserIDCol        = UserAchievementTable.Col(UserAchievementTableUserIDColName)
	UserAchievementTableAchievementIDCol = UserAchievementTable.Col(UserAchievementTableAchievementIDColName)
	UserAchievementTableCreatedAtCol     = UserAchievementTable.Col(UserAchievementTableCreatedAtColName)
)

type UserAchievementRow struct {
	UserID        int64     `db:"user_id"`
	AchievementID int32     `db:"achievement_id"`
	CreatedAt     time.Time `db:"created_at"`
}

// UserAchievementCodeRow is the joined shape used by the public profile query.
type UserAchievementCodeRow struct {
	Code      string    `db:"code"`
	CreatedAt time.Time `db:"created_at"`
}

// AchievementCountRow is the joined shape used by the /achievements catalog page's
// per-achievement earned-count query.
type AchievementCountRow struct {
	Code  string `db:"code"`
	Count int64  `db:"count"`
}

const (
	UserAchievementProgressTableName             = "user_achievement_progress"
	UserAchievementProgressTableUserIDColName    = "user_id"
	UserAchievementProgressTableMetricColName    = "metric"
	UserAchievementProgressTableCountColName     = "count"
	UserAchievementProgressTableUpdatedAtColName = "updated_at"
)

var (
	UserAchievementProgressTable          = goqu.T(UserAchievementProgressTableName)
	UserAchievementProgressTableUserIDCol = UserAchievementProgressTable.Col(UserAchievementProgressTableUserIDColName)
	UserAchievementProgressTableMetricCol = UserAchievementProgressTable.Col(UserAchievementProgressTableMetricColName)
	UserAchievementProgressTableCountCol  = UserAchievementProgressTable.Col(UserAchievementProgressTableCountColName)
)

// UserAchievementProgressRow is the persisted, ever-incrementing per-user/per-metric
// counter that both powers profile-page progress display and, later, rating/leaderboard
// lists per metric (see achievements.Repository.incrementAndGrant).
type UserAchievementProgressRow struct {
	UserID    int64     `db:"user_id"`
	Metric    string    `db:"metric"`
	Count     int64     `db:"count"`
	UpdatedAt time.Time `db:"updated_at"`
}
