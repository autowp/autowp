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

	AchievementIDPicturesContributor      = 1
	AchievementIDTopPicturesContributor   = 2
	AchievementIDPictureInspectorBronze   = 3
	AchievementIDPictureInspectorSilver   = 4
	AchievementIDPictureInspectorGold     = 5
	AchievementIDPictureInspectorPlatinum = 6
	AchievementIDPictureInspectorDiamond  = 7
	AchievementIDPictureBusterBronze      = 8
	AchievementIDPictureBusterSilver      = 9
	AchievementIDPictureBusterGold        = 10
	AchievementIDPictureBusterPlatinum    = 11
	AchievementIDPictureBusterDiamond     = 12
	AchievementIDSpecMasterBronze         = 13
	AchievementIDSpecMasterSilver         = 14
	AchievementIDSpecMasterGold           = 15
	AchievementIDSpecMasterPlatinum       = 16
	AchievementIDSpecMasterDiamond        = 17
	AchievementIDCommentatorBronze        = 18
	AchievementIDCommentatorSilver        = 19
	AchievementIDCommentatorGold          = 20
	AchievementIDCommentatorPlatinum      = 21
	AchievementIDCommentatorDiamond       = 22
	AchievementIDVeteran                  = 23
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
