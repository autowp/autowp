package goautowp

import (
	"net"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/users"
	"github.com/google/uuid"
	"github.com/stretchr/testify/require"
)

func TestUserImportCacheInvalidatesPersistentIdentityChanges(t *testing.T) {
	t.Parallel()

	for _, mutation := range []string{"delete_with_cleanup_error", "rebind_uuid"} {
		t.Run(mutation, func(t *testing.T) {
			t.Parallel()

			newUUID := uuid.NewString()

			keycloakServer := httptest.NewServer(
				http.HandlerFunc(func(writer http.ResponseWriter, request *http.Request) {
					switch {
					case strings.HasSuffix(request.URL.Path, "/token"):
						writer.Header().Set("Content-Type", "application/json")

						_, err := writer.Write(
							[]byte(`{"access_token":"test-token","token_type":"Bearer","expires_in":300}`),
						)
						if err != nil {
							t.Error(err)
						}
					case request.Method == http.MethodDelete:
						writer.WriteHeader(http.StatusNoContent)
					case request.Method == http.MethodPost && strings.HasSuffix(request.URL.Path, "/users"):
						writer.Header().Set("Location", "http://"+request.Host+request.URL.Path+"/"+newUUID)
						writer.WriteHeader(http.StatusCreated)
					default:
						t.Errorf("unexpected Keycloak request: %s %s", request.Method, request.URL.Path)
						writer.WriteHeader(http.StatusInternalServerError)
					}
				}),
			)
			defer keycloakServer.Close()

			db, database, logger := newRelatedGroupTestDB(t)
			_, err := database.ExecContext(t.Context(), `CREATE TEMP TABLE users
				(LIKE public.users INCLUDING DEFAULTS INCLUDING IDENTITY INCLUDING INDEXES)`)
			require.NoError(t, err)
			_, err = database.ExecContext(t.Context(), `SELECT setval(pg_get_serial_sequence('users', 'id')::regclass,
				(SELECT COALESCE(MAX(id), 0) + 1000 FROM public.users), false)`)
			require.NoError(t, err)

			repository := users.NewRepository(db, "", map[string]config.LanguageConfig{
				"en": {Timezone: "UTC"},
			}, gocloak.NewClient(keycloakServer.URL), config.KeycloakConfig{
				Realm: "test", ClientID: "test", ClientSecret: "test",
			}, 0, nil)
			claims := users.Claims{}
			claims.Subject = uuid.NewString()
			claims.Email = uuid.NewString() + "@test.io"
			claims.GivenName = "Imported User"
			ip := net.ParseIP("192.0.2.1")
			id, err := repository.EnsureUserImported(t.Context(), claims, ip)
			require.NoError(t, err)
			logger.queries.Store(0)

			cachedID, err := repository.EnsureUserImported(t.Context(), claims, ip)
			require.NoError(t, err)
			require.Equal(t, id, cachedID)
			require.Zero(t, logger.queries.Load())

			if mutation == "delete_with_cleanup_error" {
				// Make cleanup fail after anonymization commits, to ensure cache
				// invalidation is not postponed until the whole deletion succeeds.
				_, err = database.ExecContext(t.Context(), "CREATE TEMP TABLE user_contact (unexpected integer)")
				require.NoError(t, err)
				_, err = repository.DeleteUser(t.Context(), id)
				require.Error(t, err)

				var deleted bool

				err = database.QueryRowContext(t.Context(), "SELECT deleted AND uuid IS NULL FROM users WHERE id = $1", id).
					Scan(&deleted)
				require.NoError(t, err)
				require.True(t, deleted)
			} else {
				_, err = database.ExecContext(t.Context(), "UPDATE users SET uuid = NULL WHERE id = $1", id)
				require.NoError(t, err)
				require.NoError(t, repository.ExportUsersToKeycloak(t.Context()))

				var storedUUID string

				err = database.QueryRowContext(
					t.Context(), "SELECT uuid FROM users WHERE id = $1", id,
				).Scan(&storedUUID)
				require.NoError(t, err)
				require.Equal(t, newUUID, storedUUID)
			}

			// A stale cache would silently return the previous ID. Force an SQL
			// error to prove that the same old claims are checked against the DB.
			_, err = database.ExecContext(t.Context(), "ALTER TABLE users RENAME COLUMN uuid TO previous_uuid")
			require.NoError(t, err)
			logger.queries.Store(0)

			_, err = repository.EnsureUserImported(t.Context(), claims, ip)
			require.Error(t, err)
			require.EqualValues(t, 1, logger.queries.Load())
		})
	}
}
