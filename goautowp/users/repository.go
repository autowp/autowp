package users

import (
	"context"
	"database/sql"
	"errors"
	"fmt"
	"io"
	"math"
	"net"
	"strings"
	"time"

	"github.com/Nerzal/gocloak/v13"
	"github.com/Nerzal/gocloak/v13/pkg/jwx"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/sampler"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	_ "github.com/doug-martin/goqu/v9/dialect/postgres" // enable postgres dialect
	"github.com/google/uuid"
	"github.com/jackc/pgtype"
	_ "github.com/lib/pq" // enable postgres driver
	"github.com/sirupsen/logrus"
	"gopkg.in/gographics/imagick.v3/imagick"
)

const (
	lastOnlineUpdateThreshold = 5 * time.Second
	UserPhotoMaxFileSize      = 4194304
	UserPhotoMinWidth         = 50
	UserPhotoMinHeight        = 50
)

const deleteUnusedBatchSize = 100

var (
	ErrUserNotFound     = errors.New("user not found")
	ErrFormatNotFound   = errors.New("format not found")
	ErrLanguageNotFound = errors.New("language is not defined")
)

type Claims struct {
	jwx.Claims

	Audience       interface{}    `json:"aud,omitempty"`
	Locale         string         `json:"locale,omitempty"`
	ResourceAccess ResourceAccess `json:"resource_access,omitempty"`
}

type ResourceAccess struct {
	Autowp AutowpResourceAccess `json:"autowp,omitempty"`
}

type AutowpResourceAccess struct {
	Roles []string `json:"roles,omitempty"`
}

const KeycloakExternalAccountID = "keycloak"

// TermsVersion is the current revision of the Terms of Service. Bump it whenever the Terms change
// materially: a user whose stored terms_version is below this (or NULL) is asked to accept again
// by the in-app gate. Keep in sync with the "Last updated" date in the frontend terms.component.
const TermsVersion = 1

// CreateUserOptions CreateUserOptions.
type CreateUserOptions struct {
	UserName        string `json:"user_name"`
	FirstName       string `json:"first_name"`
	LastName        string `json:"last_name"`
	Email           string `json:"email"`
	Timezone        string `json:"timezone"`
	Language        string `json:"language"`
	Password        string `json:"password"`
	PasswordConfirm string `json:"password_confirm"`
	Captcha         string `json:"captcha"`
}

type UserFields struct {
	Email         bool
	Timezone      bool
	Language      bool
	VotesPerDay   bool
	VotesLeft     bool
	RegDate       bool
	LastOnline    bool
	Accounts      bool
	PicturesAdded bool
	LastIP        bool
	Login         bool
	Contacts      bool
}

type OrderBy int

const (
	OrderByNone OrderBy = iota
	OrderByPicturesTotalDesc
	OrderBySpecsVolumeDesc
	OrderByDeletedName
)

// Repository Main Object.
type Repository struct {
	db              *goqu.Database
	usersSalt       string
	languages       map[string]config.LanguageConfig
	keycloak        *gocloak.GoCloak
	keycloakConfig  config.KeycloakConfig
	messageInterval int64
	imageStorage    *storage.Storage
}

// UserPreferences object.
type UserPreferences struct {
	DisableCommentsNotifications bool `db:"disable_comments_notifications" json:"disable_comments_notifications"`
}

// NewRepository constructor.
func NewRepository(
	db *goqu.Database,
	usersSalt string,
	languages map[string]config.LanguageConfig,
	keyCloak *gocloak.GoCloak,
	keyCloakConfig config.KeycloakConfig,
	messageInterval int64,
	imageStorage *storage.Storage,
) *Repository {
	return &Repository{
		db:              db,
		usersSalt:       usersSalt,
		languages:       languages,
		keycloak:        keyCloak,
		keycloakConfig:  keyCloakConfig,
		messageInterval: messageInterval,
		imageStorage:    imageStorage,
	}
}

func (s *Repository) User(
	ctx context.Context, options *query.UserListOptions, fields UserFields, orderBy OrderBy,
) (*schema.UsersRow, error) {
	users, _, err := s.Users(ctx, options, fields, orderBy)
	if err != nil {
		return nil, err
	}

	if len(users) == 0 {
		return nil, ErrUserNotFound
	}

	return &users[0], nil
}

func (s *Repository) UserIDByIdentity(ctx context.Context, identity string) (int64, error) {
	var userID int64

	success, err := s.db.From(schema.UserTable).
		Where(schema.UserTableIdentityCol.Eq(identity)).
		ScanValContext(ctx, &userID)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, nil
	}

	return userID, nil
}

func (s *Repository) Users(
	ctx context.Context, options *query.UserListOptions, fields UserFields, orderBy OrderBy,
) ([]schema.UsersRow, *util.Pages, error) {
	var err error

	result := make([]schema.UsersRow, 0)

	var row schema.UsersRow

	valuePtrs := []interface{}{
		&row.ID, &row.Name, &row.Deleted, &row.Identity, &row.LastOnline, &row.Green, &row.SpecsWeight, &row.Img,
		&row.EMail, &row.PicturesTotal, &row.SpecsVolume, &row.Language, &row.UUID, &row.TermsVersion,
		&row.ContactsPublic,
	}

	alias := query.UserTableAlias
	aliasTable := goqu.T(alias)

	columns := []interface{}{
		aliasTable.Col(schema.UserTableIDColName), aliasTable.Col(schema.UserTableNameColName),
		aliasTable.Col(
			schema.UserTableDeletedColName,
		), aliasTable.Col(schema.UserTableIdentityColName),
		aliasTable.Col(
			schema.UserTableLastOnlineColName,
		), aliasTable.Col(schema.UserTableGreenColName),
		aliasTable.Col(
			schema.UserTableSpecsWeightColName,
		), aliasTable.Col(schema.UserTableImgColName),
		aliasTable.Col(
			schema.UserTableEmailColName,
		), aliasTable.Col(schema.UserTablePicturesTotalColName),
		aliasTable.Col(
			schema.UserTableSpecsVolumeColName,
		), aliasTable.Col(schema.UserTableLanguageColName),
		aliasTable.Col(schema.UserTableUUIDColName),
		aliasTable.Col(schema.UserTableTermsVersionColName),
		aliasTable.Col(schema.UserTableContactsPublicColName),
	}

	if fields.VotesLeft {
		valuePtrs = append(valuePtrs, &row.VotesLeft)
		columns = append(columns, aliasTable.Col(schema.UserTableVotesLeftColName))
	}

	if fields.VotesPerDay {
		valuePtrs = append(valuePtrs, &row.VotesPerDay)
		columns = append(columns, aliasTable.Col(schema.UserTableVotesPerDayColName))
	}

	if fields.Language {
		valuePtrs = append(valuePtrs, &row.Language)
		columns = append(columns, aliasTable.Col(schema.UserTableLanguageColName))
	}

	if fields.Timezone {
		valuePtrs = append(valuePtrs, &row.Timezone)
		columns = append(columns, aliasTable.Col(schema.UserTableTimezoneColName))
	}

	if fields.RegDate {
		valuePtrs = append(valuePtrs, &row.RegDate)
		columns = append(columns, aliasTable.Col(schema.UserTableRegDateColName))
	}

	if fields.PicturesAdded {
		valuePtrs = append(valuePtrs, &row.PicturesAdded)
		columns = append(columns, aliasTable.Col(schema.UserTablePicturesAddedColName))
	}

	if fields.LastIP {
		valuePtrs = append(valuePtrs, &row.LastIP)
		columns = append(
			columns,
			aliasTable.Col(schema.UserTableLastIPColName),
		)
	}

	if fields.Login {
		valuePtrs = append(valuePtrs, &row.Login)
		columns = append(columns, aliasTable.Col(schema.UserTableLoginColName))
	}

	sqSelect := options.Select(s.db, alias).Select(columns...)

	switch orderBy {
	case OrderByNone:
	case OrderByPicturesTotalDesc:
		sqSelect = sqSelect.Order(aliasTable.Col(schema.UserTablePicturesTotalColName).Desc())
	case OrderBySpecsVolumeDesc:
		sqSelect = sqSelect.Order(aliasTable.Col(schema.UserTableSpecsVolumeColName).Desc())
	case OrderByDeletedName:
		sqSelect = sqSelect.Order(
			aliasTable.Col(schema.UserTableDeletedColName).Asc(),
			aliasTable.Col(schema.UserTableNameColName).Asc(),
		)
	}

	var pages *util.Pages

	if options.Limit > 0 {
		paginator := util.Paginator{
			SQLSelect:         sqSelect,
			ItemCountPerPage:  int32(options.Limit), //nolint: gosec
			CurrentPageNumber: int32(options.Page),  //nolint: gosec
		}

		pages, err = paginator.GetPages(ctx)
		if err != nil {
			return nil, nil, err
		}

		sqSelect, err = paginator.GetItemsByPage(ctx, int32(options.Page)) //nolint: gosec
		if err != nil {
			return nil, nil, err
		}
	}

	rows, err := sqSelect.Executor().QueryContext(ctx) //nolint:sqlclosecheck
	if errors.Is(err, sql.ErrNoRows) {
		return result, pages, nil
	}

	if err != nil {
		return nil, nil, err
	}

	defer util.Close(rows)

	for rows.Next() {
		err = rows.Scan(valuePtrs...)
		if err != nil {
			return nil, nil, err
		}

		result = append(result, row)
	}

	if err = rows.Err(); err != nil {
		return nil, nil, err
	}

	if fields.Contacts && len(result) > 0 {
		if err = s.fillContacts(ctx, result); err != nil {
			return nil, nil, err
		}
	}

	return result, pages, nil
}

// SetUserContacts replaces the whole set of a user's contacts with the given rows (already
// validated and normalised). Rows with an empty username are dropped.
func (s *Repository) SetUserContacts(
	ctx context.Context, userID int64, contacts []schema.UserContactRow,
) error {
	ctx = context.WithoutCancel(ctx)

	_, err := s.db.Delete(schema.UserContactTable).
		Where(schema.UserContactTableUserIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	records := make([]interface{}, 0, len(contacts))

	for _, contact := range contacts {
		if contact.Username == "" {
			continue
		}

		records = append(records, goqu.Record{
			schema.UserContactTableUserIDColName:   userID,
			schema.UserContactTablePlatformColName: contact.Platform,
			schema.UserContactTableUsernameColName: contact.Username,
		})
	}

	if len(records) == 0 {
		return nil
	}

	_, err = s.db.Insert(schema.UserContactTable).Rows(records...).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) GetVotesLeft(ctx context.Context, userID int64) (int, error) {
	var votesLeft int

	success, err := s.db.Select(schema.UserTableVotesLeftCol).
		From(schema.UserTable).
		Where(schema.UserTableIDCol.Eq(userID)).
		ScanValContext(ctx, &votesLeft)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	return votesLeft, nil
}

func (s *Repository) DecVotes(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableVotesLeftColName: goqu.L(schema.UserTableVotesLeftColName + " - 1"),
	}).Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) AfterUserCreated(ctx context.Context, userID int64) error {
	ctx = context.WithoutCancel(ctx)

	err := s.RefreshUserConflicts(ctx, userID)
	if err != nil {
		return err
	}

	err = s.UpdateUserVoteLimit(ctx, userID)
	if err != nil {
		return err
	}

	_, err = s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableVotesLeftColName: schema.UserTableVotesPerDayCol,
	}).Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) UpdateUserVoteLimit(ctx context.Context, userID int64) error {
	var age int

	success, err := s.db.Select(goqu.L("EXTRACT(YEAR FROM AGE(?))", schema.UserTableRegDateCol)).
		From(schema.UserTable).
		Where(schema.UserTableIDCol.Eq(userID)).
		ScanValContext(ctx, &age)
	if err != nil {
		return err
	}

	if !success {
		return sql.ErrNoRows
	}

	def := 10

	avgVote, err := s.UserAvgVote(ctx, userID)
	if err != nil {
		return err
	}

	picturesExists, err := s.db.From(schema.PictureTable).Where(
		schema.PictureTableOwnerIDCol.Eq(userID),
		schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted),
	).CountContext(ctx)
	if err != nil {
		return err
	}

	value := math.Round(avgVote + float64(def+age) + float64(picturesExists)/100)
	if value < 0 {
		value = 0
	}

	_, err = s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableVotesPerDayColName: value,
	}).Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	return nil
}

func (s *Repository) UserAvgVote(ctx context.Context, userID int64) (float64, error) {
	var result float64

	success, err := s.db.Select(goqu.Func("COALESCE", goqu.AVG(schema.CommentMessageTableVoteCol), 0)).
		From(schema.CommentMessageTable).
		Where(
			schema.CommentMessageTableAuthorIDCol.Eq(userID),
			schema.CommentMessageTableVoteCol.Neq(0),
		).
		ScanValContext(ctx, &result)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, sql.ErrNoRows
	}

	return result, nil
}

func (s *Repository) RefreshUserConflicts(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableSpecsWeightColName: goqu.L(
			"1.5 * (1 + ?) / (1 + ?)",
			goqu.Func(
				"COALESCE",
				s.db.Select(goqu.SUM(schema.AttrsUserValuesTableWeightCol)).
					From(schema.AttrsUserValuesTable).
					Where(
						schema.AttrsUserValuesTableUserIDCol.Eq(schema.UserTableIDCol),
						schema.AttrsUserValuesTableWeightCol.Gt(0),
					),
				0,
			),
			goqu.Func(
				"COALESCE",
				s.db.Select(goqu.Func("ABS", goqu.SUM(schema.AttrsUserValuesTableWeightCol))).
					From(schema.AttrsUserValuesTable).
					Where(
						schema.AttrsUserValuesTableUserIDCol.Eq(schema.UserTableIDCol),
						schema.AttrsUserValuesTableWeightCol.Lt(0),
					),
				0,
			),
		),
	}).
		Where(schema.UserTableIDCol.Eq(userID)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) EnsureUserImported(
	ctx context.Context,
	claims Claims,
	ip net.IP,
) (int64, error) {
	locale := strings.ToLower(claims.Locale)

	language, ok := s.languages[locale]
	if !ok {
		locale = schema.EnglishLanguageCode
		language, ok = s.languages[schema.EnglishLanguageCode]
	}

	if !ok {
		return 0, fmt.Errorf("%w: `%s`", ErrLanguageNotFound, locale)
	}

	guid := claims.Subject
	emailAddr := claims.Email
	name := fullName(claims.GivenName, claims.FamilyName, claims.PreferredUsername)

	logrus.Debugf("Ensure user `%s` imported", guid)

	ctx = context.WithoutCancel(ctx)

	st := struct {
		UserID     int64 `db:"id"`
		IsInserted bool  `db:"is_inserted"`
	}{}

	success, err := s.db.Insert(schema.UserTable).
		Rows(goqu.Record{
			schema.UserTableLoginColName:          nil,
			schema.UserTableEmailColName:          emailAddr,
			schema.UserTablePasswordColName:       nil,
			schema.UserTableEmailToCheckColName:   nil,
			schema.UserTableHideEmailColName:      true,
			schema.UserTableEmailCheckCodeColName: nil,
			schema.UserTableNameColName:           name,
			schema.UserTableRegDateColName:        goqu.Func("NOW"),
			schema.UserTableLastOnlineColName:     goqu.Func("NOW"),
			schema.UserTableTimezoneColName:       language.Timezone,
			schema.UserTableLastIPColName:         goqu.Func("INET", ip.String()),
			schema.UserTableLanguageColName:       locale,
			schema.UserTableGreenColName: util.Contains(
				claims.ResourceAccess.Autowp.Roles,
				RoleGreenUser,
			),
			schema.UserTableUUIDColName: guid,
		}).
		OnConflict(goqu.DoUpdate(schema.UserTableUUIDColName, goqu.Record{
			schema.UserTableEmailColName:  schema.Excluded(schema.UserTableEmailColName),
			schema.UserTableNameColName:   schema.Excluded(schema.UserTableNameColName),
			schema.UserTableLastIPColName: schema.Excluded(schema.UserTableLastIPColName),
			schema.UserTableGreenColName:  schema.Excluded(schema.UserTableGreenColName),
		})).
		Returning(schema.UserTableIDColName, goqu.L("xmax = 0").As("is_inserted")).
		Executor().ScanStructContext(ctx, &st)
	if err != nil {
		return 0, err
	}

	if success && st.IsInserted { // row just inserted
		err = s.AfterUserCreated(ctx, st.UserID)
		if err != nil {
			return 0, err
		}
	}

	success, err = s.db.Select(schema.UserTableIDCol).
		From(schema.UserTable).
		Where(schema.UserTableUUIDCol.Eq(guid)).
		ScanValContext(ctx, &st.UserID)
	if err != nil {
		return 0, err
	}

	if !success {
		return 0, ErrUserNotFound
	}

	return st.UserID, nil
}

func (s *Repository) PasswordMatch(
	ctx context.Context,
	userID int64,
	password string,
) (bool, error) {
	var exists bool

	succcess, err := s.db.Select(goqu.V(1)).
		From(schema.UserTable).
		Where(
			schema.UserTablePasswordCol.Eq(goqu.Func("MD5", goqu.Func("CONCAT", s.usersSalt, password))),
			schema.UserTableIDCol.Eq(userID),
			schema.UserTableDeletedCol.IsFalse(),
		).
		ScanValContext(ctx, &exists)
	if err != nil {
		return false, err
	}

	return succcess && exists, nil
}

func (s *Repository) DeleteUser(ctx context.Context, userID int64) (bool, error) {
	if userID == 0 {
		return false, nil
	}

	falseRef := false

	user, err := s.User(ctx, &query.UserListOptions{
		ID:      userID,
		Deleted: &falseRef,
	}, UserFields{}, OrderByNone)
	if err != nil {
		if errors.Is(err, ErrUserNotFound) {
			return false, nil
		}

		return false, err
	}

	if user.UUID.Status == pgtype.Present {
		token, err := s.keycloak.LoginClient(
			ctx,
			s.keycloakConfig.ClientID,
			s.keycloakConfig.ClientSecret,
			s.keycloakConfig.Realm,
		)
		if err != nil {
			return false, err
		}

		ctx = context.WithoutCancel(ctx)

		userGUID, err := uuid.FromBytes(user.UUID.Bytes[:])
		if err != nil {
			return false, err
		}

		logrus.Infof("attempt to disable user `%s` in keycloak", userGUID.String())

		err = s.keycloak.DeleteUser(
			ctx,
			token.AccessToken,
			s.keycloakConfig.Realm,
			userGUID.String(),
		)
		if err != nil {
			return false, err
		}
	}

	oldImageID := user.Img

	// Anonymise the row, don't just flag it deleted: the account's public contributions
	// (comments, pictures, catalogue edits) stay, but nothing left on this row identifies who
	// made them. Keycloak holds the authoritative credentials and is deleted above; name and
	// timezone are NOT NULL so they are reset rather than nulled.
	_, err = s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableDeletedColName:         true,
		schema.UserTableUUIDColName:            nil,
		schema.UserTableImgColName:             nil,
		schema.UserTableEmailColName:           nil,
		schema.UserTableEmailToCheckColName:    nil,
		schema.UserTableEmailCheckCodeColName:  nil,
		schema.UserTablePasswordColName:        nil,
		schema.UserTableLoginColName:           nil,
		schema.UserTableIdentityColName:        nil,
		schema.UserTableLastIPColName:          nil,
		schema.UserTableNameColName:            "",
		schema.UserTableTimezoneColName:        "UTC",
		schema.UserTableURLColName:             "",
		schema.UserTableOwnCarColName:          "",
		schema.UserTableDreamCarColName:        "",
		schema.UserTableTermsAcceptedAtColName: nil,
		schema.UserTableContactsPublicColName:  false,
	}).Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	// external-profile links the user added
	_, err = s.db.Delete(schema.UserContactTable).
		Where(schema.UserContactTableUserIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	if oldImageID != nil {
		err = s.imageStorage.RemoveImage(ctx, *oldImageID)
		if err != nil {
			return false, err
		}
	}

	_, err = s.db.Delete(schema.TelegramChatTable).
		Where(schema.TelegramChatTableUserIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	// delete linked profiles
	_, err = s.db.Delete(schema.UserAccountTable).
		Where(
			schema.UserAccountTableUserIDCol.Eq(userID),
			schema.UserAccountTableServiceIDCol.Eq(KeycloakExternalAccountID),
		).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	// unsubscribe from items
	_, err = s.db.Delete(schema.UserItemSubscribeTable).
		Where(schema.UserItemSubscribeTableUserIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	// De-identify messages this user sent: the recipient keeps the conversation, but a NULL
	// from_user_id reads as a system message (see messaging.Repository) rather than naming the
	// gone account.
	_, err = s.db.Update(schema.PersonalMessageTable).Set(goqu.Record{
		schema.PersonalMessageTableFromUserIDColName: nil,
	}).Where(schema.PersonalMessageTableFromUserIDCol.Eq(userID)).Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	// Drop the poster's IP from everything this user contributed. The content stays; only the
	// address recorded for moderation goes. AnonymizeOldContentIPs clears these on a 6-month
	// timer anyway (see SchedulerDaily); this just does it immediately for a deleted account.
	_, err = s.db.Update(schema.CommentMessageTable).
		Set(goqu.Record{schema.CommentMessageTableIPColName: nil}).
		Where(schema.CommentMessageTableAuthorIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	_, err = s.db.Update(schema.PictureTable).
		Set(goqu.Record{schema.PictureTableIPColName: nil}).
		Where(schema.PictureTableOwnerIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	_, err = s.db.Update(schema.ForumsTopicsTable).
		Set(goqu.Record{schema.ForumsTopicsTableAuthorIPColName: nil}).
		Where(schema.ForumsTopicsTableAuthorIDCol.Eq(userID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return false, err
	}

	return true, nil
}

func (s *Repository) RestoreVotes(ctx context.Context) error {
	_, err := s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableVotesLeftColName: schema.UserTableVotesPerDayCol,
	}).Where(
		schema.UserTableVotesLeftCol.Lt(schema.UserTableVotesPerDayCol),
		schema.UserTableDeletedCol.IsFalse(),
	).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) UpdateVotesLimits(ctx context.Context) (int, error) {
	var ids []int64

	ctx = context.WithoutCancel(ctx)

	err := s.db.Select(schema.UserTableIDCol).
		From(schema.UserTable).
		Where(
			schema.UserTableDeletedCol.IsFalse(),
			schema.UserTableLastOnlineCol.Gt(goqu.L("NOW() - INTERVAL '3 MONTH'")),
		).
		ScanValsContext(ctx, &ids)
	if err != nil {
		return 0, err
	}

	affected := 0

	for _, userID := range ids {
		err = s.UpdateUserVoteLimit(ctx, userID)
		if err != nil {
			return 0, err
		}

		affected++
	}

	return affected, nil
}

// InvalidateSpecsVolume marks a user's specs_volume as stale so the next
// UpdateSpecsVolumes run (scheduler-daily) recomputes it. Call whenever a
// user's attrs_user_values rows change (set or delete).
func (s *Repository) InvalidateSpecsVolume(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableSpecsVolumeValidColName: false,
	}).Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)

	return err
}

// InvalidateAllSpecsVolumes marks every non-deleted user's specs_volume as
// stale. One-off backfill for users whose specs_volume was frozen by a bug
// where attrs_user_values changes never invalidated it (see
// InvalidateSpecsVolume); the next UpdateSpecsVolumes call recomputes
// everyone marked here.
func (s *Repository) InvalidateAllSpecsVolumes(ctx context.Context) error {
	_, err := s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableSpecsVolumeValidColName: false,
	}).Where(schema.UserTableDeletedCol.IsFalse()).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) UpdateSpecsVolumes(ctx context.Context) error {
	var sts []struct {
		UserID int64 `db:"id"`
		Count  int64 `db:"count"`
	}

	err := s.db.Select(schema.UserTableIDCol, goqu.COUNT(schema.AttrsUserValuesTableUserIDCol).As("count")).
		From(schema.UserTable).
		LeftJoin(schema.AttrsUserValuesTable, goqu.On(schema.UserTableIDCol.Eq(schema.AttrsUserValuesTableUserIDCol))).
		Where(
			schema.UserTableSpecsVolumeValidCol.IsFalse(),
			schema.UserTableDeletedCol.IsFalse(),
		).
		GroupBy(schema.UserTableIDCol).
		ScanStructsContext(ctx, &sts)
	if err != nil {
		return err
	}

	ctx = context.WithoutCancel(ctx)

	for _, st := range sts {
		_, err = s.db.Update(schema.UserTable).Set(goqu.Record{
			schema.UserTableSpecsVolumeColName:      st.Count,
			schema.UserTableSpecsVolumeValidColName: true,
		}).Where(schema.UserTableIDCol.Eq(st.UserID)).Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) ExportUsersToKeycloak(ctx context.Context) error {
	var ids []int64

	err := s.db.Select(schema.UserTableIDCol).From(schema.UserTable).Where(
		goqu.Or(
			goqu.Func("LENGTH", schema.UserTableLoginCol).Gt(0),
			goqu.Func("LENGTH", schema.UserTableEmailCol).Gt(0),
			goqu.Func("LENGTH", schema.UserTableEmailToCheckCol).Gt(0),
		),
	).Order(schema.UserTableIDCol.Desc()).ScanValsContext(ctx, &ids)
	if err != nil {
		return err
	}

	for _, userID := range ids {
		guid, err := s.ensureUserExportedToKeycloak(ctx, userID)
		if err != nil {
			logrus.Debugf("Error exporting user %d", userID)

			return err
		}

		logrus.Debugf("User %d exported to keycloak as %s", userID, guid)
	}

	return nil
}

func (s *Repository) SetDisableUserCommentsNotifications(
	ctx context.Context,
	userID int64,
	toUserID int64,
	disabled bool,
) error {
	_, err := s.db.Insert(schema.UserUserPreferencesTable).
		Rows(goqu.Record{
			schema.UserUserPreferencesTableUserIDColName:   userID,
			schema.UserUserPreferencesTableToUserIDColName: toUserID,
			schema.UserUserPreferencesTableDCNColName:      disabled,
		}).
		OnConflict(
			goqu.DoUpdate(
				schema.UserUserPreferencesTableUserIDColName+", "+schema.UserUserPreferencesTableToUserIDColName,
				goqu.Record{
					schema.UserUserPreferencesTableDCNColName: schema.Excluded(
						schema.UserUserPreferencesTableDCNColName,
					),
				},
			),
		).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) UserPreferences(
	ctx context.Context,
	userID int64,
	toUserID int64,
) (*UserPreferences, error) {
	var row UserPreferences

	_, err := s.db.Select(schema.UserUserPreferencesTableDCNCol).
		From(schema.UserUserPreferencesTable).
		Where(
			schema.UserUserPreferencesTableUserIDCol.Eq(userID),
			schema.UserUserPreferencesTableToUserIDCol.Eq(toUserID),
		).ScanStructContext(ctx, &row)

	return &row, err
}

func (s *Repository) IncForumTopics(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.UserTable).
		Set(s.incForumTopicsRecord()).
		Where(schema.UserTableIDCol.Eq(userID)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) IncForumMessages(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.UserTable).
		Set(s.incForumMessagesRecord()).
		Where(schema.UserTableIDCol.Eq(userID)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) TouchLastMessage(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.UserTable).
		Set(s.touchLastMessageRecord()).
		Where(schema.UserTableIDCol.Eq(userID)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) NextMessageTime(ctx context.Context, userID int64) (time.Time, error) {
	if s.messageInterval <= 0 {
		return time.Time{}, nil
	}

	st := struct {
		LastMessageTime   sql.NullTime `db:"last_message_time"`
		RegDate           sql.NullTime `db:"reg_date"`
		MessagingInterval int64        `db:"messaging_interval"`
	}{}

	success, err := s.db.Select(
		schema.UserTableLastMessageTimeCol,
		schema.UserTableRegDateCol,
		schema.UserTableMessagingIntervalCol,
	).
		From(schema.UserTable).
		Where(schema.UserTableIDCol.Eq(userID)).
		ScanStructContext(ctx, &st)
	if err != nil {
		return time.Time{}, err
	}

	if !success {
		return time.Time{}, nil
	}

	if st.LastMessageTime.Valid {
		st.MessagingInterval = s.messagingInterval(st.RegDate.Time, st.MessagingInterval)
		if st.MessagingInterval > 0 {
			interval := time.Second * time.Duration(st.MessagingInterval)

			return st.LastMessageTime.Time.Add(interval), nil
		}
	}

	return time.Time{}, nil
}

func (s *Repository) RegisterVisit(ctx context.Context, userID int64, ip net.IP) error {
	st := struct {
		LastOnline sql.NullTime `db:"last_online"`
		LastIP     *net.IP      `db:"last_ip"`
	}{}

	success, err := s.db.Select(schema.UserTableLastOnlineCol, schema.UserTableLastIPCol).
		From(schema.UserTable).
		Where(schema.UserTableIDCol.Eq(userID)).
		ScanStructContext(ctx, &st)
	if err != nil {
		return err
	}

	if !success {
		return nil
	}

	set := goqu.Record{}

	if !st.LastOnline.Valid ||
		st.LastOnline.Time.Add(lastOnlineUpdateThreshold).Before(time.Now()) {
		set[schema.UserTableLastOnlineColName] = goqu.Func("NOW")
	}

	if ip != nil && (st.LastIP == nil || !st.LastIP.Equal(ip)) {
		set[schema.UserTableLastIPColName] = goqu.Func("INET", ip.String())
	}

	if len(set) > 0 {
		_, err = s.db.Update(schema.UserTable).
			Set(set).
			Where(schema.UserTableIDCol.Eq(userID)).
			Executor().
			ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) UserLanguage(ctx context.Context, userID int64) (string, error) {
	language := ""

	success, err := s.db.Select(schema.UserTableLanguageCol).
		From(schema.UserTable).
		Where(schema.UserTableIDCol.Eq(userID)).
		ScanValContext(ctx, &language)
	if err != nil {
		return "", err
	}

	if !success {
		return "", ErrUserNotFound
	}

	return language, nil
}

func (s *Repository) RefreshPicturesCount(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTablePicturesTotalColName: s.db.Select(goqu.COUNT(goqu.Star())).
			From(schema.PictureTable).
			Where(
				schema.PictureTableOwnerIDCol.Eq(userID),
				schema.PictureTableStatusCol.Eq(schema.PictureStatusAccepted),
			),
	}).
		Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)

	return err
}

func (s *Repository) UserAccounts(
	ctx context.Context,
	userID int64,
) ([]*schema.UserAccountRow, error) {
	var rows []*schema.UserAccountRow

	err := s.db.Select(goqu.Star()).
		From(schema.UserAccountTable).
		Where(schema.UserAccountTableUserIDCol.Eq(userID)).
		ScanStructsContext(ctx, &rows)

	return rows, err
}

func (s *Repository) HaveAccountsForOtherServices(
	ctx context.Context,
	userID int64,
	id int64,
) (bool, error) {
	var found bool

	success, err := s.db.Select(goqu.V(true)).
		From(schema.UserAccountTable).
		Where(
			schema.UserAccountTableIDCol.Neq(id),
			schema.UserAccountTableUserIDCol.Eq(userID),
		).
		ScanValContext(ctx, &found)

	return success && found, err
}

func (s *Repository) RemoveUserAccount(ctx context.Context, id int64) error {
	_, err := s.db.Delete(schema.UserAccountTable).
		Where(schema.UserAccountTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)

	return err
}

func (s *Repository) DeletePhoto(ctx context.Context, userID int64) (bool, error) {
	row, err := s.User(ctx, &query.UserListOptions{ID: userID}, UserFields{}, OrderByNone)
	if err != nil {
		return false, err
	}

	ctx = context.WithoutCancel(ctx)

	if row.Img != nil {
		_, err = s.db.Update(schema.UserTable).
			Set(goqu.Record{schema.UserTableImgColName: nil}).
			Where(schema.UserTableIDCol.Eq(userID)).
			Executor().ExecContext(ctx)
		if err != nil {
			return false, err
		}

		err = s.imageStorage.RemoveImage(ctx, *row.Img)
		if err != nil {
			return false, err
		}

		return true, nil
	}

	return false, nil
}

func (s *Repository) UpdateUser(ctx context.Context, row schema.UsersRow, mask []string) error {
	set := goqu.Record{}

	if util.Contains(mask, "language") {
		set[schema.UserTableLanguageColName] = row.Language
	}

	if util.Contains(mask, "timezone") {
		set[schema.UserTableTimezoneColName] = row.Timezone
	}

	if util.Contains(mask, "contacts_public") {
		set[schema.UserTableContactsPublicColName] = row.ContactsPublic
	}

	if len(set) > 0 {
		_, err := s.db.Update(schema.UserTable).
			Set(set).
			Where(schema.UserTableIDCol.Eq(row.ID)).
			Executor().ExecContext(ctx)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) SetUserPhoto(ctx context.Context, userID int64, file io.ReadSeeker) error {
	if userID <= 0 {
		return sql.ErrNoRows
	}

	user, err := s.User(ctx, &query.UserListOptions{
		ID:      userID,
		Deleted: util.BoolPtr(false),
	}, UserFields{}, OrderByNone)
	if err != nil {
		return err
	}

	oldImageID := user.Img

	format := s.imageStorage.Format("photo")
	if format == nil {
		return ErrFormatNotFound
	}

	mw := imagick.NewMagickWand()
	defer mw.Destroy()

	imgBytes, err := io.ReadAll(file)
	if err != nil {
		return err
	}

	err = mw.ReadImageBlob(imgBytes)
	if err != nil {
		return err
	}

	imageSampler := s.imageStorage.Sampler()

	mwConverted, err := imageSampler.ConvertImage(mw, sampler.Crop{}, *format)
	if err != nil {
		return err
	}

	defer mwConverted.Destroy()

	ctx = context.WithoutCancel(ctx)

	imageID, err := s.imageStorage.AddImageFromImagick(
		ctx,
		mwConverted,
		"user",
		storage.GenerateOptions{},
	)
	if err != nil {
		return err
	}

	_, err = s.db.Update(schema.UserTable).
		Set(goqu.Record{
			schema.UserTableImgColName: imageID,
		}).
		Where(schema.UserTableIDCol.Eq(user.ID)).
		Executor().ExecContext(ctx)
	if err != nil {
		return err
	}

	if oldImageID != nil {
		err = s.imageStorage.RemoveImage(ctx, *oldImageID)
		if err != nil {
			return err
		}
	}

	return nil
}

func (s *Repository) DeleteUnused(ctx context.Context) error {
	var ids []int64

	err := s.db.Select(schema.UserTableIDCol).
		From(schema.UserTable).
		LeftJoin(schema.AttrsUserValuesTable, goqu.On(schema.UserTableIDCol.Eq(schema.AttrsUserValuesTableUserIDCol))).
		LeftJoin(schema.CommentMessageTable, goqu.On(schema.UserTableIDCol.Eq(schema.CommentMessageTableAuthorIDCol))).
		LeftJoin(schema.ForumsTopicsTable, goqu.On(schema.UserTableIDCol.Eq(schema.ForumsTopicsTableAuthorIDCol))).
		LeftJoin(schema.PictureTable, goqu.On(schema.UserTableIDCol.Eq(schema.PictureTableOwnerIDCol))).
		LeftJoin(schema.VotingVariantVoteTable, goqu.On(
			schema.UserTableIDCol.Eq(schema.VotingVariantVoteTableUserIDCol),
		)).
		LeftJoin(schema.PersonalMessageTable.As("pmf"), goqu.On(
			schema.UserTableIDCol.Eq(goqu.T("pmf").Col(schema.PersonalMessageTableFromUserIDColName)),
		)).
		LeftJoin(schema.PersonalMessageTable.As("pmt"), goqu.On(
			schema.UserTableIDCol.Eq(goqu.T("pmt").Col(schema.PersonalMessageTableToUserIDColName)),
		)).
		LeftJoin(schema.LogEventTable, goqu.On(schema.UserTableIDCol.Eq(schema.LogEventTableUserIDCol))).
		Where(
			schema.UserTableDeletedCol.IsFalse(),
			schema.UserTableLastOnlineCol.Lt(goqu.L("NOW() - INTERVAL '2 YEAR'")),
			schema.AttrsUserValuesTableUserIDCol.IsNull(),
			schema.CommentMessageTableAuthorIDCol.IsNull(),
			schema.ForumsTopicsTableAuthorIDCol.IsNull(),
			schema.PictureTableOwnerIDCol.IsNull(),
			schema.VotingVariantVoteTableUserIDCol.IsNull(),
			goqu.T("pmf").Col(schema.PersonalMessageTableFromUserIDColName).IsNull(),
			goqu.T("pmt").Col(schema.PersonalMessageTableToUserIDColName).IsNull(),
			schema.LogEventTableUserIDCol.IsNull(),
		).
		Order(schema.UserTableIDCol.Asc()).
		Limit(deleteUnusedBatchSize).
		ScanValsContext(ctx, &ids)
	if err != nil {
		return err
	}

	for _, id := range ids {
		logrus.Warnf("Delete user %d", id)

		_, err = s.DeleteUser(ctx, id)
		if err != nil {
			return err
		}
	}

	return nil
}

// ipRetentionMonths is how long a poster's IP address is kept on their contributions before
// AnonymizeOldContentIPs clears it. It is a moderation aid (ban-evasion, sock-puppets) whose
// value decays within weeks, so the privacy policy commits to at most six months.
const ipRetentionMonths = 6

// AnonymizeOldContentIPs nulls the IP address recorded against comments, pictures and forum
// topics older than ipRetentionMonths. Run daily from SchedulerDaily; returns the number of
// rows changed across all three tables.
func (s *Repository) AnonymizeOldContentIPs(ctx context.Context) (int64, error) {
	cutoff := goqu.L("NOW() - make_interval(months => ?)", ipRetentionMonths)

	commentsAffected, err := execRowsAffected(ctx, s.db.Update(schema.CommentMessageTable).
		Set(goqu.Record{schema.CommentMessageTableIPColName: nil}).
		Where(schema.CommentMessageTableIPCol.IsNotNull(), schema.CommentMessageTableDatetimeCol.Lt(cutoff)))
	if err != nil {
		return 0, err
	}

	picturesAffected, err := execRowsAffected(ctx, s.db.Update(schema.PictureTable).
		Set(goqu.Record{schema.PictureTableIPColName: nil}).
		Where(schema.PictureTableIPCol.IsNotNull(), schema.PictureTableCreatedAtCol.Lt(cutoff)))
	if err != nil {
		return 0, err
	}

	topicsAffected, err := execRowsAffected(ctx, s.db.Update(schema.ForumsTopicsTable).
		Set(goqu.Record{schema.ForumsTopicsTableAuthorIPColName: nil}).
		Where(schema.ForumsTopicsTableAuthorIPCol.IsNotNull(), schema.ForumsTopicsTableCreatedAtCol.Lt(cutoff)))
	if err != nil {
		return 0, err
	}

	return commentsAffected + picturesAffected + topicsAffected, nil
}

func execRowsAffected(ctx context.Context, upd *goqu.UpdateDataset) (int64, error) {
	res, err := upd.Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	return res.RowsAffected()
}

func (s *Repository) IncrementUploads(ctx context.Context, id int64) error {
	_, err := s.db.Update(schema.UserTable).
		Set(goqu.Record{
			schema.UserTablePicturesAddedColName: goqu.L("? + 1", schema.UserTablePicturesAddedCol),
		}).
		Where(schema.UserTableIDCol.Eq(id)).
		Executor().ExecContext(ctx)

	return err
}

// RecordConsent appends the user's current cookie-consent choice to user_consent_log. The table
// is append-only: one row per decision, kept for GDPR Art. 7(1) accountability.
func (s *Repository) RecordConsent(ctx context.Context, userID int64, analytics bool, policyVersion int32) error {
	_, err := s.db.Insert(schema.UserConsentLogTable).Rows(goqu.Record{
		schema.UserConsentLogTableUserIDColName:        userID,
		schema.UserConsentLogTableAnalyticsColName:     analytics,
		schema.UserConsentLogTablePolicyVersionColName: policyVersion,
	}).Executor().ExecContext(ctx)

	return err
}

// AcceptTerms records that the user has accepted the current TermsVersion of the Terms of Service.
// Overwrites any earlier acceptance - only the latest version and time are kept (see the two
// columns on the users table); there is no per-version history.
func (s *Repository) AcceptTerms(ctx context.Context, userID int64) error {
	_, err := s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableTermsVersionColName:    TermsVersion,
		schema.UserTableTermsAcceptedAtColName: goqu.Func("NOW"),
	}).Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)

	return err
}

// consentLogRetentionYears is how long a consent record is kept. It survives account deletion
// (Art. 17(3)(e): defence of legal claims), but not forever - a complaint that old is out of time.
const consentLogRetentionYears = 3

// PurgeOldConsentLog deletes consent records older than consentLogRetentionYears. Run daily from
// SchedulerDaily; returns the number of rows removed.
func (s *Repository) PurgeOldConsentLog(ctx context.Context) (int64, error) {
	res, err := s.db.Delete(schema.UserConsentLogTable).
		Where(schema.UserConsentLogTableCreatedAtCol.Lt(
			goqu.L("NOW() - make_interval(years => ?)", consentLogRetentionYears),
		)).
		Executor().ExecContext(ctx)
	if err != nil {
		return 0, err
	}

	return res.RowsAffected()
}

func (s *Repository) ensureUserExportedToKeycloak(
	ctx context.Context,
	userID int64,
) (string, error) {
	logrus.Debugf("Ensure user `%d` exported to Keycloak", userID)

	st := struct {
		Deleted      bool           `db:"deleted"`
		Email        sql.NullString `db:"e_mail"`
		EmailToCheck sql.NullString `db:"email_to_check"`
		Login        sql.NullString `db:"login"`
		Name         string         `db:"name"`
		GUID         string         `db:"guid"`
	}{}

	success, err := s.db.Select(
		schema.UserTableDeletedCol,
		schema.UserTableEmailCol,
		schema.UserTableEmailToCheckCol,
		schema.UserTableLoginCol,
		schema.UserTableNameCol,
		goqu.Func("COALESCE", schema.UserTableUUIDCol, "").As("guid"),
	).
		From(schema.UserTable).
		Where(schema.UserTableIDCol.Eq(userID)).
		ScanStructContext(ctx, &st)
	if err != nil {
		return "", err
	}

	if !success {
		return "", sql.ErrNoRows
	}

	if len(st.GUID) > 0 {
		return st.GUID, nil
	}

	token, err := s.keycloak.LoginClient(
		ctx,
		s.keycloakConfig.ClientID,
		s.keycloakConfig.ClientSecret,
		s.keycloakConfig.Realm,
	)
	if err != nil {
		return "", err
	}

	var (
		keyCloakEmail = &st.Email.String
		emailVerified = true
	)

	if !st.Email.Valid || len(st.Email.String) == 0 {
		keyCloakEmail = &st.EmailToCheck.String
		emailVerified = false
	}

	hasLogin := st.Login.Valid && len(st.Login.String) > 0
	hasKeyCloakEmail := keyCloakEmail != nil && len(*keyCloakEmail) > 0

	username := st.Login.String
	if !hasLogin && hasKeyCloakEmail {
		username = *keyCloakEmail
	}

	falseRef := false
	enabled := !st.Deleted
	ctx = context.WithoutCancel(ctx)

	st.GUID, err = s.keycloak.CreateUser(
		ctx,
		token.AccessToken,
		s.keycloakConfig.Realm,
		gocloak.User{
			Enabled:       &enabled,
			Totp:          &falseRef,
			EmailVerified: &emailVerified,
			Username:      &username,
			FirstName:     &st.Name,
			Email:         keyCloakEmail,
		},
	)
	if err != nil {
		return "", err
	}

	_, err = s.db.Update(schema.UserTable).Set(goqu.Record{
		schema.UserTableUUIDColName: st.GUID,
	}).Where(schema.UserTableIDCol.Eq(userID)).Executor().ExecContext(ctx)
	if err != nil {
		return "", err
	}

	return st.GUID, err
}

func (s *Repository) incForumTopicsRecord() goqu.Record {
	r := s.incForumMessagesRecord()
	r[schema.UserTableForumsTopicsColName] = goqu.L(schema.UserTableForumsTopicsColName + " + 1")

	return r
}

func (s *Repository) incForumMessagesRecord() goqu.Record {
	r := s.touchLastMessageRecord()
	r[schema.UserTableForumsMessagesColName] = goqu.L(
		schema.UserTableForumsMessagesColName + " + 1",
	)

	return r
}

func (s *Repository) touchLastMessageRecord() goqu.Record {
	return goqu.Record{schema.UserTableLastMessageTimeColName: goqu.Func("NOW")}
}

func fullName(firstName, lastName, username string) string {
	result := strings.TrimSpace(firstName + " " + lastName)
	if len(result) == 0 {
		result = username
	}

	return result
}

func (s *Repository) messagingInterval(regDate time.Time, messagingInterval int64) int64 {
	if regDate.IsZero() {
		return s.messageInterval
	}

	tenDaysBefore := time.Now().AddDate(0, 0, -10)
	if tenDaysBefore.After(regDate) {
		return messagingInterval
	}

	return util.Max(messagingInterval, s.messageInterval)
}

// fillContacts loads user_contact rows for the given users and attaches them to each row's
// Contacts slice, ordered by platform id for a stable render.
func (s *Repository) fillContacts(ctx context.Context, rows []schema.UsersRow) error {
	ids := make([]int64, 0, len(rows))
	for i := range rows {
		ids = append(ids, rows[i].ID)
	}

	var contacts []schema.UserContactRow

	err := s.db.Select(
		schema.UserContactTableUserIDCol,
		schema.UserContactTablePlatformCol,
		schema.UserContactTable.Col(schema.UserContactTableUsernameColName),
	).
		From(schema.UserContactTable).
		Where(schema.UserContactTableUserIDCol.In(ids)).
		Order(
			schema.UserContactTableUserIDCol.Asc(),
			schema.UserContactTablePlatformCol.Asc(),
		).
		ScanStructsContext(ctx, &contacts)
	if err != nil {
		return err
	}

	byUser := make(map[int64][]schema.UserContactRow, len(rows))
	for _, contact := range contacts {
		byUser[contact.UserID] = append(byUser[contact.UserID], contact)
	}

	for i := range rows {
		rows[i].Contacts = byUser[rows[i].ID]
	}

	return nil
}
