package pictureextractor_test

import (
	"context"
	"database/sql"
	"testing"
	"time"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/items"
	"github.com/autowp/goautowp/internal/pictures"
	"github.com/autowp/goautowp/internal/query"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/autowp/goautowp/internal/transport/grpc/itemparentextractor"
	"github.com/autowp/goautowp/internal/transport/grpc/picturemapper"
	"github.com/autowp/goautowp/internal/transport/grpc/querymapper"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/protobuf/proto"
)

type descendantPictureFixture struct {
	*itemparentextractor.Extractor

	pictures *pictureFieldFixture
}

func newDescendantPicturesExtractor(t *testing.T) (*descendantPictureFixture, *goqu.Database, *sql.DB) {
	t.Helper()
	fixture, db, pool, _ := newRemainingPictureExtractor(t)
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE item_parent_cache (item_id bigint,parent_id bigint,
 tuning boolean,sport boolean,design boolean,diff integer);
 INSERT INTO item_parent_cache VALUES
 (100,10,false,false,false,0),(200,10,false,false,false,1),(200,20,false,false,false,1),
 (300,20,false,false,false,1),(400,30,false,false,false,1),(500,40,false,false,false,1);
 UPDATE pg_temp.picture SET width=id,height=id+1,filesize=id*100;
 `)
	require.NoError(t, err)

	return &descendantPictureFixture{
		Extractor: fixture.itemParents,
		pictures:  fixture,
	}, db, pool
}

func legacyDescendantPictures(
	t *testing.T,
	extractor *descendantPictureFixture,
	id int64,
	request *pb.PicturesRequest,
	lang string,
) *pb.PicturesList {
	t.Helper()
	repository, err := extractor.pictures.dependencies.PicturesRepository(t.Context())
	require.NoError(t, err)
	options, err := querymapper.PictureListOptionsFromProto(request.GetOptions())
	require.NoError(t, err)

	if options == nil {
		options = &query.PictureListOptions{}
	}

	if options.PictureItem == nil {
		options.PictureItem = &query.PictureItemListOptions{}
	}

	if options.PictureItem.ItemParentCacheAncestor == nil {
		options.PictureItem.ItemParentCacheAncestor = &query.ItemParentCacheListOptions{}
	}

	options.PictureItem.ItemParentCacheAncestor.ParentID = id
	rows, _, err := repository.Pictures(
		t.Context(),
		options,
		picturemapper.FieldsFromProto(request.GetFields()),
		picturemapper.OrderFromProto(request.GetOrder()),
		false,
	)
	require.NoError(t, err)
	values, err := extractor.pictures.
		ExtractRows(t.Context(), rows, request.GetFields(), lang, authn.User{UserID: 7})
	require.NoError(t, err)

	return &pb.PicturesList{Items: values}
}

func TestItemParentDescendantPicturesBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()
	extractor, db, _ := newDescendantPicturesExtractor(t)

	rows := make([]*items.ItemParent, 0, 6)
	for _, id := range []int64{20, 10, 30, 50, 10, 0} {
		rows = append(rows, &items.ItemParent{ItemParentRow: schema.ItemParentRow{ItemID: id, ParentID: 99}})
	}

	for _, order := range []pb.PicturesRequest_Order{
		pb.PicturesRequest_ORDER_CREATED_AT_DESC, pb.PicturesRequest_ORDER_CREATED_AT_ASC,
		pb.PicturesRequest_ORDER_RESOLUTION_DESC, pb.PicturesRequest_ORDER_RESOLUTION_ASC,
	} {
		for _, options := range []*pb.PictureListOptions{
			nil,
			{Status: pb.PictureStatus_PICTURE_STATUS_ACCEPTED},
			{
				Ids:         []int64{1, 3, 5, 6, 7},
				PictureItem: &pb.PictureItemListOptions{TypeId: pb.PictureItemType_PICTURE_ITEM_CONTENT},
			},
		} {
			request := &pb.PicturesRequest{
				Options: options,
				Order:   order,
				Limit:   1,
				Fields:  &pb.PictureFields{Rights: true, Subscribed: true},
			}
			fields := &pb.ItemParentFields{ChildDescendantPictures: request}
			original := proto.Clone(fields)

			expected := make([]*pb.PicturesList, len(rows))
			for index, row := range rows {
				expected[index] = legacyDescendantPictures(t, extractor, row.ItemID, request, "en")
			}

			logger := &testutil.QueryLogger{}
			db.Logger(logger)

			result, err := extractor.ExtractRows(t.Context(), rows, fields, "en", authn.User{UserID: 7})
			require.NoError(t, err)
			db.Logger(nil)

			wantQueries := 2

			for _, group := range expected {
				for _, picture := range group.GetItems() {
					if picture.GetStatus() == pb.PictureStatus_PICTURE_STATUS_INBOX {
						wantQueries = 3
					}
				}
			}

			require.EqualValues(t, wantQueries, logger.Queries.Load())

			for index, row := range result {
				require.Equal(t, rows[index].ItemID, row.GetItemId())
				require.True(t, proto.Equal(expected[index], row.GetChildDescendantPictures()))
			}

			require.NotNil(t, result[3].GetChildDescendantPictures().GetItems())
			require.Empty(t, result[3].GetChildDescendantPictures().GetItems())
			require.Greater(
				t,
				len(result[1].GetChildDescendantPictures().GetItems()),
				1,
				"retain the original ignored request limit",
			)
			require.True(t, proto.Equal(original, fields))
		}
	}
}

func TestGroupedPicturesLimitsAndErrors(t *testing.T) {
	t.Parallel()
	extractor, db, pool := newDescendantPicturesExtractor(t)
	repository, err := extractor.pictures.dependencies.PicturesRepository(t.Context())
	require.NoError(t, err)

	groups := map[int64]*query.PictureListOptions{
		10: {
			Limit: 1,
			PictureItem: &query.PictureItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{ParentID: 10},
			},
		},
		20: {
			Limit: 2,
			PictureItem: &query.PictureItemListOptions{
				ItemParentCacheAncestor: &query.ItemParentCacheListOptions{ParentID: 20},
			},
		},
	}
	logger := &testutil.QueryLogger{}
	db.Logger(logger)

	actual, err := repository.GroupedPictures(t.Context(), groups, nil, pictures.OrderByCreatedAtDesc)
	require.NoError(t, err)
	db.Logger(nil)
	require.EqualValues(t, 1, logger.Queries.Load())

	for id, options := range groups {
		expected, _, err := repository.Pictures(t.Context(), options, nil, pictures.OrderByCreatedAtDesc, false)
		require.NoError(t, err)
		require.Equal(t, expected, actual[id])
	}

	logger.Queries.Store(0)
	db.Logger(logger)

	empty, err := extractor.ExtractRows(
		t.Context(),
		nil,
		&pb.ItemParentFields{ChildDescendantPictures: &pb.PicturesRequest{}},
		"en",
		authn.User{},
	)
	require.NoError(t, err)
	require.NotNil(t, empty)
	require.Zero(t, logger.Queries.Load())
	db.Logger(nil)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.item_parent_cache RENAME COLUMN parent_id TO broken")
	require.NoError(t, err)
	result, err := extractor.ExtractRows(
		t.Context(),
		[]*items.ItemParent{{ItemParentRow: schema.ItemParentRow{ItemID: 10}}},
		&pb.ItemParentFields{ChildDescendantPictures: &pb.PicturesRequest{}},
		"en",
		authn.User{},
	)
	require.Error(t, err)
	require.Nil(t, result)
}

func TestDescendantPicturesBatchCancellationAndNestedError(t *testing.T) {
	t.Parallel()
	extractor, db, pool := newDescendantPicturesExtractor(t)

	ctx, cancel := context.WithTimeout(t.Context(), 10*time.Second)
	defer cancel()

	connection, err := pool.Conn(ctx)
	require.NoError(t, err)

	started := make(chan struct{}, 3)

	db.Logger(&testutil.QueryLogger{BeforeQuery: func() { started <- struct{}{} }})

	rows := []*items.ItemParent{{ItemParentRow: schema.ItemParentRow{ItemID: 10, ParentID: 20}}}
	fields := &pb.ItemParentFields{
		Item: &pb.ItemFields{NameText: true}, Parent: &pb.ItemFields{NameText: true},
		ChildDescendantPictures: &pb.PicturesRequest{Fields: &pb.PictureFields{Rights: true}},
	}
	done := make(chan error, 1)

	go func() { _, err := extractor.ExtractRows(ctx, rows, fields, "en", authn.User{}); done <- err }()

	for range 3 {
		select {
		case <-started:
		case <-ctx.Done():
			t.Fatal("child, parent and picture selections did not start together")
		}
	}

	cancel()
	require.ErrorIs(t, <-done, context.Canceled)
	require.NoError(t, connection.Close())
	db.Logger(nil)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.picture_moder_vote RENAME COLUMN vote TO broken")
	require.NoError(t, err)
	result, err := extractor.ExtractRows(t.Context(), rows, &pb.ItemParentFields{
		ChildDescendantPictures: &pb.PicturesRequest{Fields: &pb.PictureFields{Rights: true}},
	}, "en", authn.User{})
	require.Error(t, err)
	require.Nil(t, result)
}
