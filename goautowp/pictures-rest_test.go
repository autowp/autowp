package goautowp

import (
	"encoding/json"
	"fmt"
	"io"
	"math/rand"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/gin-gonic/gin"
	"github.com/stretchr/testify/require"
)

func TestUploadPictureTooSmall(t *testing.T) {
	t.Parallel()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	brandID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", random.Int()),
	})

	picturesREST, err := cnt.PicturesREST(t.Context())
	require.NoError(t, err)

	router := gin.New()
	kc := cnt.Keycloak()
	ctx := t.Context()
	cfg := config.LoadConfig(".")

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

	picturesREST.SetupRouter(router)

	req := CreatePictureRequest(
		t,
		"./test/10x10.png",
		PicturePostForm{ItemID: brandID},
		adminToken.AccessToken,
	)

	resRecorder := httptest.NewRecorder()
	router.ServeHTTP(resRecorder, req)

	body, err := io.ReadAll(resRecorder.Result().Body)
	require.NoError(t, err)

	require.Contains(t, string(body), "640x360")
	require.Equal(t, http.StatusBadRequest, resRecorder.Code)
}

func TestUploadPicture(t *testing.T) {
	t.Parallel()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	picturesREST, err := cnt.PicturesREST(t.Context())
	require.NoError(t, err)

	router := gin.New()
	kc := cnt.Keycloak()
	ctx := t.Context()
	cfg := config.LoadConfig(".")

	// admin
	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)
	require.NotNil(t, adminToken)

	picturesREST.SetupRouter(router)

	cases := []struct {
		name    string
		catname string
	}{
		{
			name:    fmt.Sprintf("Brand %d", random.Int()),
			catname: fmt.Sprintf("brand-%d", random.Int()),
		},
		{
			name:    fmt.Sprintf("Brand %d (Foo)", random.Int()),
			catname: fmt.Sprintf("brand-%d-foo", random.Int()),
		},
	}

	for _, test := range cases {
		t.Run(test.name, func(t *testing.T) {
			t.Parallel()

			brandID := createItem(t, conn, cnt, &Item{
				Name:       test.name,
				IsGroup:    true,
				ItemTypeId: ItemType_ITEM_TYPE_BRAND,
				Catname:    test.catname,
			})

			req := CreatePictureRequest(
				t,
				"./test/test.jpg",
				PicturePostForm{ItemID: brandID},
				adminToken.AccessToken,
			)

			resRecorder := httptest.NewRecorder()
			router.ServeHTTP(resRecorder, req)

			body, err := io.ReadAll(resRecorder.Result().Body)
			require.NoError(t, err)

			if resRecorder.Result().StatusCode != http.StatusCreated {
				require.Equal(t, http.StatusCreated, resRecorder.Result().StatusCode, string(body))
			}

			st := struct {
				ID string `json:"id"`
			}{}

			err = json.Unmarshal(body, &st)
			require.NoError(t, err, "failed to decode json. `%s` given", string(body))

			require.NotEmpty(t, st.ID, "json not contains picture.id. `%s` given", string(body))
		})
	}
}
