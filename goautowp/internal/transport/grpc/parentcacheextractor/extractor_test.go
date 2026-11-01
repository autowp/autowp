package parentcacheextractor

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/stretchr/testify/require"
)

func TestItemParentCacheBatchUnselectedAndEmpty(t *testing.T) {
	t.Parallel()

	extractor := &Extractor{}
	unselected, err := extractor.preloadItems(t.Context(), nil, []int64{7}, "en", authn.User{})
	require.NoError(t, err)
	require.Nil(t, unselected)
	empty, err := extractor.preloadItems(t.Context(), &pb.ItemsRequest{}, nil, "en", authn.User{})
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Empty(t, empty)
	result, err := extractor.ExtractRows(t.Context(), []*schema.ItemParentCacheRow{{ItemID: 1, ParentID: 7}},
		nil, "en", authn.User{})
	require.NoError(t, err)
	require.Nil(t, result[0].GetParentItem())
}
