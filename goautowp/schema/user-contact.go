package schema

import "github.com/doug-martin/goqu/v9"

// UserContactPlatform identifies an external platform a user may link a profile on.
// Mirrors the spec.proto UserContactPlatform enum and the server-side usercontacts.Platforms
// registry.
type UserContactPlatform int16

const (
	UserContactPlatformDrive2     UserContactPlatform = 1
	UserContactPlatformDzen       UserContactPlatform = 2
	UserContactPlatformYouTube    UserContactPlatform = 3
	UserContactPlatformTelegram   UserContactPlatform = 4
	UserContactPlatformX          UserContactPlatform = 5
	UserContactPlatformTikTok     UserContactPlatform = 6
	UserContactPlatformReddit     UserContactPlatform = 7
	UserContactPlatformFlickr     UserContactPlatform = 8
	UserContactPlatform500px      UserContactPlatform = 9
	UserContactPlatformBehance    UserContactPlatform = 10
	UserContactPlatformVSCO       UserContactPlatform = 11
	UserContactPlatformArtStation UserContactPlatform = 12
	UserContactPlatformDeviantArt UserContactPlatform = 13
	UserContactPlatformLinkedIn   UserContactPlatform = 14
	UserContactPlatformGitHub     UserContactPlatform = 15
	UserContactPlatformVK         UserContactPlatform = 16
)

const (
	UserContactTableName            = "user_contact"
	UserContactTableUserIDColName   = "user_id"
	UserContactTablePlatformColName = "platform"
	UserContactTableUsernameColName = "username"
	UserContactUsernameMaxLen       = 64
)

var (
	UserContactTable            = goqu.T(UserContactTableName)
	UserContactTableUserIDCol   = UserContactTable.Col(UserContactTableUserIDColName)
	UserContactTablePlatformCol = UserContactTable.Col(UserContactTablePlatformColName)
)

// UserContactRow is one row of user_contact.
type UserContactRow struct {
	UserID   int64               `db:"user_id"`
	Platform UserContactPlatform `db:"platform"`
	Username string              `db:"username"`
}
