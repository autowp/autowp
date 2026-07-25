package goautowp

import (
	"bytes"
	"fmt"
	"io"
	"math/rand"
	"mime/multipart"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/util"
	"github.com/gin-gonic/gin"
	"github.com/stretchr/testify/require"
)

func SetItemLogoRequest(t *testing.T, itemID int64, file string) *http.Request {
	t.Helper()

	cfg := config.LoadConfig(".")

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	// admin
	_, adminToken := getUserWithCleanHistory(t, conn, cfg, goquDB, adminUsername, adminPassword)

	var (
		buf             = new(bytes.Buffer)
		multipartWriter = multipart.NewWriter(buf)
	)

	part, err := multipartWriter.CreateFormFile(itemLogoFileField, filepath.Base(file))
	require.NoError(t, err)

	handle, err := os.OpenFile(file, os.O_RDONLY, 0)
	require.NoError(t, err)

	defer util.Close(handle)

	fileBytes, err := io.ReadAll(handle)
	require.NoError(t, err)

	_, err = part.Write(fileBytes)
	require.NoError(t, err)

	err = multipartWriter.Close()
	require.NoError(t, err)

	req, err := http.NewRequestWithContext(t.Context(), http.MethodPost, fmt.Sprintf("/api/item/%d/logo", itemID), buf)
	require.NoError(t, err)

	req.Header.Add("Content-Type", multipartWriter.FormDataContentType())
	req.Header.Add(authorizationHeader, bearerPrefix+adminToken) //nolint:canonicalheader

	return req
}

func TestSetItemLogo(t *testing.T) {
	t.Parallel()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	brandID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", random.Int()),
	})

	itemsREST, err := cnt.ItemsREST(t.Context())
	require.NoError(t, err)

	router := gin.New()

	itemsREST.SetupRouter(router)

	req := SetItemLogoRequest(t, brandID, "./test/png.png")

	w := httptest.NewRecorder()
	router.ServeHTTP(w, req)

	require.Equal(t, http.StatusOK, w.Code)

	client := NewItemsClient(conn)

	brand, err := client.Item(t.Context(), &ItemRequest{
		Id: brandID,
		Fields: &ItemFields{
			Logo: true,
		},
	})
	require.NoError(t, err)
	require.NotEmpty(t, brand.GetLogo().GetSrc())
}

func TestSetItemLogoInvalidFile(t *testing.T) {
	t.Parallel()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	brandID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", random.Int()),
	})

	itemsREST, err := cnt.ItemsREST(t.Context())
	require.NoError(t, err)

	router := gin.New()

	itemsREST.SetupRouter(router)

	req := SetItemLogoRequest(t, brandID, "./test/dump.sql")

	resRecorder := httptest.NewRecorder()
	router.ServeHTTP(resRecorder, req)

	require.Equal(t, http.StatusBadRequest, resRecorder.Code)
}

func TestSetItemLogoInvalidFile2(t *testing.T) {
	t.Parallel()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	brandID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", random.Int()),
	})

	itemsREST, err := cnt.ItemsREST(t.Context())
	require.NoError(t, err)

	router := gin.New()

	itemsREST.SetupRouter(router)

	req := SetItemLogoRequest(t, brandID, "./test/small.jpg")

	resRecorder := httptest.NewRecorder()
	router.ServeHTTP(resRecorder, req)

	require.Equal(t, http.StatusBadRequest, resRecorder.Code)
}

func TestSetItemLogoInvalidFile3(t *testing.T) {
	t.Parallel()

	random := rand.New(rand.NewSource(time.Now().UnixNano())) //nolint:gosec

	brandID := createItem(t, conn, cnt, &Item{
		Name:       fmt.Sprintf("brand-%d", random.Int()),
		IsGroup:    true,
		ItemTypeId: ItemType_ITEM_TYPE_BRAND,
		Catname:    fmt.Sprintf("brand-%d", random.Int()),
	})

	itemsREST, err := cnt.ItemsREST(t.Context())
	require.NoError(t, err)

	router := gin.New()

	itemsREST.SetupRouter(router)

	req := SetItemLogoRequest(t, brandID, "./test/10x10.png")

	resRecorder := httptest.NewRecorder()
	router.ServeHTTP(resRecorder, req)

	body, err := io.ReadAll(resRecorder.Result().Body)
	require.NoError(t, err)

	require.Contains(t, string(body), "50x50")
	require.Equal(t, http.StatusBadRequest, resRecorder.Code)
}
