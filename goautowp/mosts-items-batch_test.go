package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/mosts"
	mostsgrpc "github.com/autowp/goautowp/internal/transport/grpc/mosts"
	"github.com/autowp/goautowp/internal/users"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

func TestMostsItemsBatchSharesFieldReads(t *testing.T) {
	t.Parallel()
	extractor, logger := newItemBatchTestExtractor(t)
	server := mostsgrpc.NewServer(nil, extractor, nil)
	fields := &pb.ItemFields{
		Meta: true, SpecificationsCount: true, Subscription: true, HasText: true,
		CommentsCount: true, ItemLanguageCount: true, LinksCount: true,
	}
	user := authn.User{UserID: 7, Roles: []string{users.RoleModer}}
	list := []mosts.ResultItem{
		{Item: &items.Item{ID: 2}, ValueHTML: "2 <span>kg</span>"},
		{Item: &items.Item{ID: 1}, ValueHTML: "1 <span>kg</span>"},
		{Item: &items.Item{ID: 2}, ValueHTML: "0 <span>kg</span>"},
	}

	expected := make([]*pb.MostsItem, len(list))
	for index, car := range list {
		item, err := extractor.Extract(t.Context(), car.Item, fields, "ru", user)
		require.NoError(t, err)

		expected[index] = &pb.MostsItem{Item: item, ValueHtml: car.ValueHTML, UnitId: 42}
	}

	require.EqualValues(t, 21, logger.queries.Load())
	logger.queries.Store(0)

	actual, err := server.ExtractItems(t.Context(), list, 42, fields, "ru", user)
	require.NoError(t, err)
	require.EqualValues(t, 7, logger.queries.Load())
	require.True(t, proto.Equal(&pb.MostsItems{Items: expected}, &pb.MostsItems{Items: actual}))
}
