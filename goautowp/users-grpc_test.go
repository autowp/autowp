package goautowp

import (
	"math/rand"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/image/storage"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

const TestImageFile = "./image/storage/_files/Towers_Schiphol_small.jpg"

func TestCreateUpdateDeleteUser(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewUsersClient(conn)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	userEmail := "test" + strconv.Itoa(random.Int()) + "@example.com"

	name := "ivan"
	lastName := "ivanov"
	password := "password"

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()

	clientToken, err := kc.LoginClient(
		ctx,
		cfg.Keycloak.ClientID,
		cfg.Keycloak.ClientSecret,
		cfg.Keycloak.Realm,
	)
	require.NoError(t, err)

	_, err = kc.CreateUser(ctx, clientToken.AccessToken, cfg.Keycloak.Realm, gocloak.User{
		Enabled:       gocloak.BoolP(true),
		EmailVerified: gocloak.BoolP(true),
		Username:      &userEmail,
		FirstName:     &name,
		LastName:      &lastName,
		Email:         &userEmail,
		Credentials: &[]gocloak.CredentialRepresentation{{
			Type:  gocloak.StringP("password"),
			Value: &password,
		}},
	})
	require.NoError(t, err)

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, userEmail, password)
	require.NoError(t, err)
	require.NotNil(t, token)

	me, err := client.Me(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&MeRequest{},
	)
	require.NoError(t, err)
	require.NotNil(t, me)

	db, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	// set avatar
	imageStorage, err := cnt.ImageStorage(t.Context())
	require.NoError(t, err)

	imageID, err := imageStorage.AddImageFromFilepath(
		ctx,
		TestImageFile,
		"user",
		storage.GenerateOptions{},
	)
	require.NoError(t, err)

	_, err = db.Update(schema.UserTable).
		Set(goqu.Record{"img": imageID}).
		Where(schema.UserTableIDCol.Eq(me.GetId())).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	user, err := client.GetUser(ctx, &GetUserRequest{UserId: me.GetId()})
	require.NoError(t, err)
	require.NotEmpty(t, user)
	// require.NotEmpty(t, user.Gravatar)
	require.NotEmpty(t, user.GetAvatar().GetSrc())
	require.Equal(t, name+" "+lastName, user.GetName())

	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	_, err = client.DeleteUser(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&DeleteUserRequest{UserId: me.GetId(), Password: password},
	)
	require.NoError(t, err)
}

func TestSetDisabledUserCommentsNotifications(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	client := NewUsersClient(conn)

	// admin
	adminToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		adminUsername,
		adminPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	// tester (me)
	tester, err := client.Me(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&MeRequest{},
	)
	require.NoError(t, err)

	// disable
	_, err = client.DisableUserCommentsNotifications(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&UserPreferencesRequest{UserId: tester.GetId()},
	)
	require.NoError(t, err)

	res1, err := client.GetUserPreferences(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&UserPreferencesRequest{UserId: tester.GetId()},
	)
	require.NoError(t, err)
	require.True(t, res1.GetDisableCommentsNotifications())

	// enable
	_, err = client.EnableUserCommentsNotifications(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&UserPreferencesRequest{UserId: tester.GetId()},
	)
	require.NoError(t, err)

	res2, err := client.GetUserPreferences(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+adminToken.AccessToken,
		),
		&UserPreferencesRequest{UserId: tester.GetId()},
	)
	require.NoError(t, err)
	require.False(t, res2.GetDisableCommentsNotifications())
}

func TestGetOnlineUsers(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	client := NewUsersClient(conn)

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	// touch last_online for tester
	_, err = client.Me(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&MeRequest{},
	)
	require.NoError(t, err)

	res, err := client.GetUsers(ctx, &UsersRequest{IsOnline: true, Fields: &UserFields{
		Email:                 true,
		Timezone:              true,
		Language:              true,
		VotesPerDay:           true,
		VotesLeft:             true,
		Img:                   true,
		GravatarLarge:         true,
		Photo:                 true,
		RegDate:               true,
		PicturesAdded:         true,
		PicturesAcceptedCount: true,
		LastIp:                true,
		LastOnline:            true,
		Login:                 true,
	}})
	require.NoError(t, err)
	require.NotEmpty(t, res.GetItems())
}

func TestGetUsersPagination(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := NewUsersClient(conn)

	_, err := client.GetUsers(ctx, &UsersRequest{Page: 1, Limit: 10})
	require.NoError(t, err)
}

func TestGetUsersSearch(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	client := NewUsersClient(conn)

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	// touch last_online for tester
	me, err := client.Me(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&MeRequest{},
	)
	require.NoError(t, err)

	res, err := client.GetUsers(ctx, &UsersRequest{Search: strings.ToLower(me.GetName())})
	require.NoError(t, err)
	require.NotEmpty(t, res.GetItems())
	require.NotEmpty(t, res.GetItems()[0])

	res, err = client.GetUsers(ctx, &UsersRequest{Search: strings.ToUpper(me.GetName())})
	require.NoError(t, err)
	require.NotEmpty(t, res.GetItems())
	require.NotEmpty(t, res.GetItems()[0])
}

func TestUpdateUser(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	cfg := config.LoadConfig(".")

	kc := cnt.Keycloak()
	client := NewUsersClient(conn)

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	ctx = metadata.AppendToOutgoingContext(
		ctx,
		authorizationHeader,
		bearerPrefix+testerToken.AccessToken,
	)

	me, err := client.Me(
		ctx,
		&MeRequest{},
	)
	require.NoError(t, err)

	_, err = client.UpdateUser(
		ctx,
		&UpdateUserRequest{
			User: &User{
				Id:       me.GetId(),
				Timezone: "Europe/Dublin",
				Language: schema.RussianLanguageCode,
			},
			UpdateMask: &fieldmaskpb.FieldMask{
				Paths: []string{"language", "timezone"},
			},
		},
	)
	require.NoError(t, err)

	res, err := client.GetUser(
		ctx,
		&GetUserRequest{UserId: me.GetId(), Fields: &UserFields{Timezone: true, Language: true}},
	)
	require.NoError(t, err)
	require.Equal(t, "Europe/Dublin", res.GetTimezone())
	require.Equal(t, schema.RussianLanguageCode, res.GetLanguage())
}
