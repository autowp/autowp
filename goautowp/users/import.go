package users

import (
	"context"
	"errors"
	"fmt"
	"net"
	"strings"
	"sync"
	"time"

	"github.com/autowp/goautowp/logging"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"golang.org/x/sync/singleflight"
)

const (
	userImportTimeout         = 10 * time.Second
	userImportCacheTTL        = 5 * time.Second
	userImportCacheMaxEntries = 10000
)

var errUnexpectedImportResult = errors.New("unexpected user import result")

// Only values that affect database synchronization participate in equality.
// Roles are still taken from each verified token by Auth.ValidateToken.
type userImportInput struct {
	uuid     string
	email    string
	name     string
	ip       string
	green    bool
	locale   string
	timezone string
}

type userImportResult struct {
	input    userImportInput
	id       int64
	revision uint64
}

type userImportCacheEntry struct {
	input userImportInput
	id    int64
	until time.Time
}

// User IDs are cached only after successful profile synchronization. Token
// verification and authorization roles stay outside this process-local cache.
// A revision change prevents an in-flight import from repopulating invalidated data.
type userImports struct {
	group    singleflight.Group
	mutex    sync.Mutex
	entries  map[string]userImportCacheEntry
	revision uint64
}

func (s *userImports) ensure(
	ctx context.Context, input userImportInput,
	importUser func(context.Context, userImportInput) (int64, error),
) (int64, error) {
	for {
		if err := ctx.Err(); err != nil {
			return 0, err
		}

		pending := s.group.DoChan(input.uuid, func() (interface{}, error) {
			return s.synchronize(ctx, input, importUser)
		})

		select {
		case <-ctx.Done():
			return 0, ctx.Err()
		case completed := <-pending:
			result, ok := completed.Val.(userImportResult)
			if !ok {
				return 0, errUnexpectedImportResult
			}

			if result.input != input {
				// A request with different claims/IP must apply its own update after
				// the current synchronization, rather than reusing its result or error.
				continue
			}

			s.mutex.Lock()
			current := result.revision == s.revision
			s.mutex.Unlock()

			if !current {
				continue
			}

			return result.id, completed.Err
		}
	}
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

	input := userImportInput{
		uuid:     claims.Subject,
		email:    claims.Email,
		name:     fullName(claims.GivenName, claims.FamilyName, claims.PreferredUsername),
		ip:       ip.String(),
		green:    util.Contains(claims.ResourceAccess.Autowp.Roles, RoleGreenUser),
		locale:   locale,
		timezone: language.Timezone,
	}

	return s.imports.ensure(ctx, input, s.ensureUserImported)
}

func (s *Repository) ensureUserImported(ctx context.Context, input userImportInput) (int64, error) {
	guid, emailAddr, name, green := input.uuid, input.email, input.name, input.green
	ip := input.ip

	logging.Debugf("Ensure user `%s` imported", guid)

	// Common path: the user was already imported. Update the mutable fields with a
	// plain UPDATE, which does not touch the `id` identity sequence. An
	// `INSERT ... ON CONFLICT DO UPDATE` would advance the sequence on every call
	// (Postgres evaluates the identity default before detecting the conflict).
	var userID int64

	found, err := s.db.Update(schema.UserTable).
		Set(goqu.Record{
			schema.UserTableEmailColName:  emailAddr,
			schema.UserTableNameColName:   name,
			schema.UserTableLastIPColName: goqu.Func("INET", ip),
			schema.UserTableGreenColName:  green,
		}).
		Where(schema.UserTableUUIDCol.Eq(guid)).
		Returning(schema.UserTableIDColName).
		Executor().ScanValContext(ctx, &userID)
	if err != nil {
		return 0, err
	}

	if found {
		return userID, nil
	}

	// First login for this guid: insert the user, tolerating a concurrent first
	// login via ON CONFLICT.
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
			schema.UserTableTimezoneColName:       input.timezone,
			schema.UserTableLastIPColName:         goqu.Func("INET", ip),
			schema.UserTableLanguageColName:       input.locale,
			schema.UserTableGreenColName:          green,
			schema.UserTableUUIDColName:           guid,
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

	if !success {
		return 0, ErrUserNotFound
	}

	if st.IsInserted { // row just inserted
		err = s.afterUserCreated(ctx, st.UserID)
		if err != nil {
			return 0, err
		}
	}

	return st.UserID, nil
}

func (s *Repository) afterUserCreated(ctx context.Context, userID int64) error {
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
