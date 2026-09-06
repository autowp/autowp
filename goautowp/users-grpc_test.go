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
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/types/known/emptypb"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

const TestImageFile = "./image/storage/_files/Towers_Schiphol_small.jpg"

// registerRandomUser creates a fresh Keycloak user, logs it in through the backend once (so the
// local users row is provisioned) and returns its access token and local id.
func registerRandomUser(t *testing.T) (string, int64) {
	t.Helper()

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()

	clientToken, err := kc.LoginClient(
		ctx,
		cfg.Keycloak.ClientID,
		cfg.Keycloak.ClientSecret,
		cfg.Keycloak.Realm,
	)
	require.NoError(t, err)

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec
	email := "test" + strconv.Itoa(random.Int()) + "@example.com"
	name := "ivan"
	password := "password"

	_, err = kc.CreateUser(ctx, clientToken.AccessToken, cfg.Keycloak.Realm, gocloak.User{
		Enabled:       gocloak.BoolP(true),
		EmailVerified: gocloak.BoolP(true),
		Username:      &email,
		FirstName:     &name,
		LastName:      &name,
		Email:         &email,
		Credentials: &[]gocloak.CredentialRepresentation{{
			Type:  gocloak.StringP("password"),
			Value: &password,
		}},
	})
	require.NoError(t, err)

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, email, password)
	require.NoError(t, err)
	require.NotNil(t, token)

	me, err := NewUsersClient(conn).Me(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&MeRequest{},
	)
	require.NoError(t, err)

	return token.AccessToken, me.GetId()
}

func TestUserBlacklist(t *testing.T) {
	t.Parallel()

	ctx := t.Context()
	usersClient := NewUsersClient(conn)
	messagingClient := NewMessagingClient(conn)

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()

	tokenA, _ := registerRandomUser(t)
	tokenB, userBID := registerRandomUser(t)

	ctxA := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+tokenA)
	ctxB := metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+tokenB)

	meA, err := usersClient.Me(ctxA, &MeRequest{})
	require.NoError(t, err)

	userAID := meA.GetId()

	// A blacklists B
	_, err = usersClient.AddUserToBlacklist(ctxA, &UserPreferencesRequest{UserId: userBID})
	require.NoError(t, err)

	prefsA, err := usersClient.GetUserPreferences(ctxA, &UserPreferencesRequest{UserId: userBID})
	require.NoError(t, err)
	require.True(t, prefsA.GetBlacklist())
	require.False(t, prefsA.GetBlockedByTarget())

	// B sees it is blocked by A
	prefsB, err := usersClient.GetUserPreferences(ctxB, &UserPreferencesRequest{UserId: userAID})
	require.NoError(t, err)
	require.False(t, prefsB.GetBlacklist())
	require.True(t, prefsB.GetBlockedByTarget())

	// B appears on A's blacklist
	list, err := usersClient.GetBlacklistedUsers(ctxA, &emptypb.Empty{})
	require.NoError(t, err)
	require.Contains(t, blacklistedIDs(list), userBID)

	// B cannot message A
	_, err = messagingClient.CreateMessage(ctxB, &CreateMessageRequest{Message: &Message{
		ToUserId: userAID,
		Text:     "hi",
	}})
	require.Equal(t, codes.PermissionDenied, status.Code(err))

	// the dialog view tells B that sending is blocked
	dialog, err := messagingClient.GetMessages(ctxB, &MessagingGetMessagesRequest{
		UserId: userAID,
		Folder: "dialog",
		Page:   1,
	})
	require.NoError(t, err)
	require.True(t, dialog.GetSendingBlocked())

	// an administrator bypasses the block
	adminToken, err := kc.Login(
		ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword,
	)
	require.NoError(t, err)

	_, err = messagingClient.CreateMessage(
		metadata.AppendToOutgoingContext(
			ctx, authorizationHeader, bearerPrefix+adminToken.AccessToken,
		),
		&CreateMessageRequest{Message: &Message{ToUserId: userBID, Text: "from admin"}},
	)
	require.NoError(t, err)

	// A removes B from the blacklist; B can message A again
	_, err = usersClient.RemoveUserFromBlacklist(ctxA, &UserPreferencesRequest{UserId: userBID})
	require.NoError(t, err)

	_, err = messagingClient.CreateMessage(ctxB, &CreateMessageRequest{Message: &Message{
		ToUserId: userAID,
		Text:     "hi again",
	}})
	require.NoError(t, err)

	list, err = usersClient.GetBlacklistedUsers(ctxA, &emptypb.Empty{})
	require.NoError(t, err)
	require.NotContains(t, blacklistedIDs(list), userBID)
}

func blacklistedIDs(res *UsersResponse) []int64 {
	ids := make([]int64, 0, len(res.GetItems()))
	for _, u := range res.GetItems() {
		ids = append(ids, u.GetId())
	}

	return ids
}

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
