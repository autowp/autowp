package integration

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
)

func TestGetArticles(t *testing.T) {
	t.Parallel()

	ctx := t.Context()

	client := pb.NewArticlesClient(conn)

	response1, err := client.GetList(
		ctx,
		&pb.ArticlesRequest{},
	)
	require.NoError(t, err)
	require.NotNil(t, response1)
	require.Len(t, response1.GetItems(), 1)
	require.Equal(t, int64(1), response1.GetItems()[0].GetId())
	require.Equal(t, "/img/articles/preview/test.jpg", response1.GetItems()[0].GetPreviewUrl())
	require.Equal(t, "Test description", response1.GetItems()[0].GetDescription())
	require.Empty(t, response1.GetItems()[0].GetHtml())

	response2, err := client.GetItemByCatname(
		ctx,
		&pb.ArticleByCatnameRequest{Catname: "test-article"},
	)
	require.NoError(t, err)
	require.NotNil(t, response2)
	require.Equal(t, int64(1), response2.GetId())
	require.Equal(t, "Test html", response2.GetHtml())
	require.Equal(t, "/img/articles/preview/test.jpg", response2.GetPreviewUrl())
}

func TestGetArticleNotFound(t *testing.T) {
	t.Parallel()

	client := pb.NewArticlesClient(conn)
	_, err := client.GetItemByCatname(t.Context(), &pb.ArticleByCatnameRequest{Catname: "missing-article"})
	require.Equal(t, codes.NotFound, status.Code(err))
}
