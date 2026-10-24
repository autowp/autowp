package goautowp

import (
	"context"
	"database/sql"
	"net"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/users"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
)

func TestEnsureUserImportedCreatesAndSynchronizesUser(t *testing.T) {
	t.Parallel()

	db, database, logger := newRelatedGroupTestDB(t)
	_, err := database.ExecContext(t.Context(), `CREATE TEMP TABLE users
		(LIKE public.users INCLUDING DEFAULTS INCLUDING IDENTITY INCLUDING INDEXES)`)
	require.NoError(t, err)
	// Keep the temporary user's ID away from public picture/attribute fixtures
	// consulted by the required post-insert initialization.
	_, err = database.ExecContext(t.Context(), `SELECT setval(pg_get_serial_sequence('users', 'id')::regclass,
		(SELECT COALESCE(MAX(id), 0) + 1000 FROM public.users), false)`)
	require.NoError(t, err)

	repository := users.NewRepository(db, "", map[string]config.LanguageConfig{
		"en": {Timezone: "UTC"},
	}, nil, config.KeycloakConfig{}, 0, nil)
	claims := users.Claims{}
	claims.Subject = uuid.NewString()
	claims.Email = uuid.NewString() + "@test.io"
	claims.GivenName = "First"
	claims.FamilyName = "Imported"
	claims.Locale = "EN"
	claims.ResourceAccess.Autowp.Roles = []string{users.RoleGreenUser}
	id, err := repository.EnsureUserImported(t.Context(), claims, net.ParseIP("192.0.2.1"))
	require.NoError(t, err)
	require.Positive(t, id)

	var (
		name        string
		email       string
		ip          string
		green       bool
		language    string
		timezone    string
		votesPerDay int
		votesLeft   int
		online      sql.NullTime
	)

	err = database.QueryRowContext(t.Context(), `SELECT name, e_mail, HOST(last_ip), green,
		language, timezone, votes_per_day, votes_left, last_online FROM users WHERE id = $1`, id).
		Scan(&name, &email, &ip, &green, &language, &timezone, &votesPerDay, &votesLeft, &online)
	require.NoError(t, err)
	require.Equal(t, "First Imported", name)
	require.Equal(t, claims.Email, email)
	require.Equal(t, "192.0.2.1", ip)
	require.True(t, green)
	require.Equal(t, "en", language)
	require.Equal(t, "UTC", timezone)
	require.Positive(t, votesPerDay)
	require.Equal(t, votesPerDay, votesLeft, "post-insert initialization must finish before returning the ID")
	require.True(t, online.Valid)

	_, err = database.ExecContext(t.Context(), "UPDATE users SET votes_left = 1 WHERE id = $1", id)
	require.NoError(t, err)
	logger.queries.Store(0)

	for range 2 {
		existingID, err := repository.EnsureUserImported(t.Context(), claims, net.ParseIP("192.0.2.1"))
		require.NoError(t, err)
		require.Equal(t, id, existingID)
	}

	require.EqualValues(t, 2, logger.queries.Load(), "sequential imports still synchronize with the database")

	claims.GivenName = "Changed"
	claims.Email = uuid.NewString() + "@test.io"
	claims.ResourceAccess.Autowp.Roles = nil
	existingID, err := repository.EnsureUserImported(t.Context(), claims, net.ParseIP("2001:db8::1"))
	require.NoError(t, err)
	require.Equal(t, id, existingID)
	require.EqualValues(t, 3, logger.queries.Load())
	err = database.QueryRowContext(t.Context(), `SELECT name, e_mail, HOST(last_ip), green, votes_left
 FROM users WHERE id = $1`, id).
		Scan(&name, &email, &ip, &green, &votesLeft)
	require.NoError(t, err)
	require.Equal(t, "Changed Imported", name)
	require.Equal(t, claims.Email, email)
	require.Equal(t, "2001:db8::1", ip)
	require.False(t, green)
	require.Equal(t, 1, votesLeft, "existing users must not be initialized again")

	var count int

	err = database.QueryRowContext(t.Context(), "SELECT COUNT(*) FROM users WHERE uuid = $1", claims.Subject).
		Scan(&count)
	require.NoError(t, err)
	require.Equal(t, 1, count)
	logger.queries.Store(0)

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	_, err = repository.EnsureUserImported(ctx, claims, net.ParseIP("192.0.2.1"))
	require.ErrorIs(t, err, context.Canceled)
	require.Zero(t, logger.queries.Load())
}
