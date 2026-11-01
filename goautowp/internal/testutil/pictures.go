package testutil

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

	authn "github.com/autowp/goautowp/internal/auth"
	pictureshttp "github.com/autowp/goautowp/internal/transport/http/pictures"
	"github.com/autowp/goautowp/internal/util"
	"github.com/stretchr/testify/require"
)

func CreatePicture(
	t *testing.T,
	handler http.Handler,
	file string,
	data pictureshttp.PicturePostForm,
	token string,
) int64 {
	t.Helper()

	req := CreatePictureRequest(t, file, data, token)

	resRecorder := httptest.NewRecorder()
	handler.ServeHTTP(resRecorder, req)

	response := resRecorder.Result()
	defer util.Close(response.Body)

	body, err := io.ReadAll(response.Body)
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
	req.Header.Add(authn.AuthorizationHeader, "Bearer "+token)
	req.RemoteAddr = "127.0.0.1:1234"

	return req
}
