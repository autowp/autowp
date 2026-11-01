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
	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/schema"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
	"github.com/autowp/goautowp/internal/util"
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
	data pictureshttp.PicturePostForm,
	status pb.PictureStatus,
	token string,
) int64 {
	t.Helper()

	pictureID := CreatePicture(t, cnt, filepath, data, token)

	picturesClient := pb.NewPicturesClient(conn)

	_, err := picturesClient.UpdatePicture(
		metadata.AppendToOutgoingContext(t.Context(), authn.AuthorizationHeader, bearerPrefix+token),
		&pb.UpdatePictureRequest{
			Picture:    &pb.Picture{Id: pictureID, Status: status},
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

	user, err := pb.NewUsersClient(conn).Me(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		&pb.MeRequest{},
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

func createItem(t *testing.T, conn *grpc.ClientConn, cnt *Container, row *pb.Item) int64 {
	t.Helper()

	ctx := t.Context()

	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	itemsClient := pb.NewItemsClient(conn)

	res, err := itemsClient.CreateItem(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
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
	data pictureshttp.PicturePostForm,
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
	data pictureshttp.PicturePostForm,
	token string,
) *http.Request {
	t.Helper()

	var (
		buf             = new(bytes.Buffer)
		multipartWriter = multipart.NewWriter(buf)
	)

	part, err := multipartWriter.CreateFormFile("file", filepath.Base(file))
	require.NoError(t, err)

	handle, err := os.OpenFile(file, os.O_RDONLY, 0)
	require.NoError(t, err)

	defer util.Close(handle)

	fileBytes, err := io.ReadAll(handle)
	require.NoError(t, err)

	_, err = part.Write(fileBytes)
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField("comment")
	require.NoError(t, err)
	_, err = part.Write([]byte(data.Comment))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField("item_id")
	require.NoError(t, err)
	_, err = part.Write([]byte(strconv.FormatInt(data.ItemID, 10)))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField("replace_picture_id")
	require.NoError(t, err)
	_, err = part.Write([]byte(strconv.FormatInt(data.ReplacePictureID, 10)))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField("perspective_id")
	require.NoError(t, err)
	_, err = part.Write([]byte(strconv.FormatInt(int64(data.PerspectiveID), 10)))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField("author_id")
	require.NoError(t, err)
	_, err = part.Write([]byte(strconv.FormatInt(data.AuthorID, 10)))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField("license_id")
	require.NoError(t, err)
	_, err = part.Write([]byte(strconv.FormatInt(int64(data.LicenseID), 10)))
	require.NoError(t, err)

	part, err = multipartWriter.CreateFormField("source_url")
	require.NoError(t, err)
	_, err = part.Write([]byte(data.SourceURL))
	require.NoError(t, err)

	err = multipartWriter.Close()
	require.NoError(t, err)

	req, err := http.NewRequestWithContext(t.Context(), http.MethodPost, "/api/picture", buf)
	require.NoError(t, err)

	req.Header.Add("Content-Type", multipartWriter.FormDataContentType())
	req.Header.Add(authn.AuthorizationHeader, bearerPrefix+token)
	req.RemoteAddr = "127.0.0.1:1234"

	return req
}
