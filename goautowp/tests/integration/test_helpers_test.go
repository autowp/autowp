package integration

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/testutil"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
	"github.com/gin-gonic/gin"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
	"google.golang.org/protobuf/types/known/fieldmaskpb"
)

const (
	testUsername     = "tester"
	testPassword     = "123123"
	adminUsername    = "admin"
	adminPassword    = "123123"
	bearerPrefix     = "Bearer "
	keycloakClientID = "frontend"
)

func createItem(t *testing.T, row *pb.Item) int64 {
	t.Helper()

	ctx := t.Context()

	cfg := cnt.Config()
	kc := cnt.Keycloak()
	token, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, token)

	return testutil.CreateItem(
		metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+token.AccessToken),
		t,
		pb.NewItemsClient(conn),
		row,
	)
}

func createPicture(t *testing.T, file string, data pictureshttp.PicturePostForm, token string) int64 {
	t.Helper()

	router := gin.New()
	picturesREST, err := cnt.PicturesREST(t.Context())
	require.NoError(t, err)
	picturesREST.SetupRouter(router)

	return testutil.CreatePicture(
		t,
		router,
		file,
		data,
		token,
	)
}

func addPicture(
	t *testing.T,
	file string, //nolint:unparam // Keep the fixture path explicit at call sites.
	data pictureshttp.PicturePostForm,
	status pb.PictureStatus,
	token string,
) int64 {
	t.Helper()

	pictureID := createPicture(t, file, data, token)

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
