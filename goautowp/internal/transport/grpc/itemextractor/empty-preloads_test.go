package itemextractor_test

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/transport/grpc/itemextractor"
	"github.com/stretchr/testify/require"
)

func TestItemExtractorEmptyPreloads(t *testing.T) {
	t.Parallel()

	ctx, cancel := context.WithCancel(t.Context())
	cancel()

	result, err := (&itemextractor.Extractor{}).ExtractRows(ctx, nil, &pb.ItemFields{
		PictureItems: &pb.PictureItemsRequest{}, ItemParentChilds: &pb.ItemParentsRequest{Limit: 1}, HasText: true,
	}, "en", authn.User{})
	require.NoError(t, err)
	require.NotNil(t, result)
	require.Empty(t, result)
}
