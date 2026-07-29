package goautowp

import (
	"bytes"
	"encoding/json"
	"io"
	"mime/multipart"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strconv"
	"testing"

	"github.com/Nerzal/gocloak/v13"
	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/autowp/goautowp/util"
	"github.com/doug-martin/goqu/v9"
	"github.com/gin-gonic/gin"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

const (
	testUsername  = "tester"
	testPassword  = "123123"
	adminUsername = "admin"
	adminPassword = "123123"
)

const (
	bearerPrefix     = "Bearer "
	keycloakClientID = "frontend"
)

func addPicture(
	t *testing.T,
	cnt *Container,
	conn *grpc.ClientConn,
	filepath string, //nolint: unparam
	data PicturePostForm,
	status PictureStatus,
	token string,
) int64 {
	t.Helper()

	pictureID := CreatePicture(t, cnt, filepath, data, token)

	picturesClient := NewPicturesClient(conn)

	_, err := picturesClient.UpdatePicture(
		metadata.AppendToOutgoingContext(t.Context(), authorizationHeader, bearerPrefix+token),
		&UpdatePictureRequest{
			Picture:    &Picture{Id: pictureID, Status: status},
			UpdateMask: &fieldmaskpb.FieldMask{Paths: []string{"status"}},
		},
	)
	require.NoError(t, err)

	return pictureID
}

//nolint:unparam
func getUserWithCleanHistory(
	t *testing.T,
	conn *grpc.ClientConn,
	cfg config.Config,
	db *goqu.Database,
	username string,
	password string,
) (int64, string) {
	t.Helper()

	ctx := t.Context()
	kc := gocloak.NewClient(cfg.Keycloak.URL)

	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, username, password)
	require.NoError(t, err)
	require.NotNil(t, token)

	user, err := NewUsersClient(conn).Me(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		&MeRequest{},
	)
	require.NoError(t, err)

	const exampleVotesLeftColName = 100

	_, err = db.Update(schema.UserTable).
		Set(goqu.Record{
			schema.UserTableLastMessageTimeColName: "2000-01-01",
			schema.UserTableVotesLeftColName:       exampleVotesLeftColName,
		}).
		Where(schema.UserTableIDCol.Eq(user.GetId())).
		Executor().ExecContext(ctx)
	require.NoError(t, err)

	return user.GetId(), token.AccessToken
}

func createItem(t *testing.T, conn *grpc.ClientConn, cnt *Container, row *Item) int64 {
	t.Helper()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	itemsClient := NewItemsClient(conn)

	res, err := itemsClient.CreateItem(
		metadata.AppendToOutgoingContext(ctx, authorizationHeader, bearerPrefix+token.AccessToken),
		row,
	)
	require.NoError(t, err)

	itemID := res.GetId()
	require.NotEmpty(t, itemID)

	return itemID
}

func CreatePicture(
	t *testing.T,
	cnt *Container,
	file string,
	data PicturePostForm,
	token string,
) int64 {
	t.Helper()

	req := CreatePictureRequest(t, file, data, token)

	resRecorder := httptest.NewRecorder()
	router := gin.New()
	picturesREST, err := cnt.PicturesREST(t.Context())
	require.NoError(t, err)
	picturesREST.SetupRouter(router)
	router.ServeHTTP(resRecorder, req)

	body, err := io.ReadAll(resRecorder.Result().Body)
	require.NoError(t, err)

	require.Equal(t, http.StatusCreated, resRecorder.Code, "201 expected `%d` given. body: `%s`",
		resRecorder.Code, string(body))

	st := struct {
		ID string `json:"id"`
	}{}

	err = json.Unmarshal(body, &st)
	require.NoError(t, err, "failed to decode json. `%s` given", string(body))

	require.NotEmpty(t, st.ID, "json not contains picture.id. `%s` given", string(body))

	id, err := strconv.ParseInt(st.ID, 10, 64)
	require.NoError(t, err)

	return id
}

func CreatePictureRequest(
	t *testing.T,
	file string,
	data PicturePostForm,
	token string,
) *http.Request {
	t.Helper()

	var (
		buf             = new(bytes.Buffer)
		multipartWriter = multipart.NewWriter(buf)
	)

	part, err := multipartWriter.CreateFormFile(pictureFileField, filepath.Base(file))
	require.NoError(t, err)

	handle, err := os.OpenFile(file, os.O_RDONLY, 0)
	require.NoError(t, err)

	defer util.Close(handle)

	fileBytes, err := io.ReadAll(handle)
	require.NoError(t, err)

	_, err = part.Write(fileBytes)
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField(pictureCommentField)
	require.NoError(t, err)
	_, err = part.Write([]byte(data.Comment))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField(pictureItemIDField)
	require.NoError(t, err)
	_, err = part.Write([]byte(strconv.FormatInt(data.ItemID, 10)))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField(pictureReplacePictureIDField)
	require.NoError(t, err)
	_, err = part.Write([]byte(strconv.FormatInt(data.ReplacePictureID, 10)))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField(picturePerspectiveID)
	require.NoError(t, err)
	_, err = part.Write([]byte(strconv.FormatInt(int64(data.PerspectiveID), 10)))
	require.NoError(t, err)

	err = multipartWriter.Close()
	require.NoError(t, err)

	req, err := http.NewRequestWithContext(t.Context(), http.MethodPost, "/api/picture", buf)
	require.NoError(t, err)

	req.Header.Add("Content-Type", multipartWriter.FormDataContentType())
	req.Header.Add(authorizationHeader, bearerPrefix+token) //nolint:canonicalheader
	req.RemoteAddr = "127.0.0.1:1234"

	return req
}
