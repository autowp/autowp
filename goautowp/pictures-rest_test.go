package goautowp

import (
	"encoding/json"
	"fmt"
	"io"
	"math/rand"
	"net/http"
	"net/http/httptest"
	"strconv"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
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

func TestUploadPictureWithAuthorID(t *testing.T) {
	t.Parallel()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	picturesREST, err := cnt.PicturesREST(t.Context())
	require.NoError(t, err)

	router := gin.New()
	picturesREST.SetupRouter(router)

	ctx := t.Context()
	cfg := config.LoadConfig(".")
	kc := cnt.Keycloak()

	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	goquDB, err := cnt.GoquDB(ctx)
	require.NoError(t, err)

	brandID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("Brand %d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", random.Int()),
	})
	personID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("person-%d", random.Int()),
		ItemTypeId: ItemType_ITEM_TYPE_PERSON,
	})

	req := CreatePictureRequest(
		t,
		"./test/test.jpg",
		PicturePostForm{ItemID: brandID, AuthorID: personID},
		adminToken.AccessToken,
	)

	resRecorder := httptest.NewRecorder()
	router.ServeHTTP(resRecorder, req)

	body, err := io.ReadAll(resRecorder.Result().Body)
	require.NoError(t, err)
	require.Equal(t, http.StatusCreated, resRecorder.Result().StatusCode, string(body))

	st := struct {
		ID string `json:"id"`
	}{}
	require.NoError(t, json.Unmarshal(body, &st))

	pictureID, err := strconv.ParseInt(st.ID, 10, 64)
	require.NoError(t, err)

	found, err := goquDB.From(schema.PictureItemTable).
		Select(goqu.L("1")).
		Where(
			schema.PictureItemTablePictureIDCol.Eq(pictureID),
			schema.PictureItemTableItemIDCol.Eq(personID),
			schema.PictureItemTableTypeCol.Eq(schema.PictureItemTypeAuthor),
		).
		ScanValContext(ctx, new(int64))
	require.NoError(t, err)
	require.True(t, found, "author picture-item should have been created from author_id")
}
