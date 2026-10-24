package goautowp

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/query"
	"github.com/autowp/goautowp/schema"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
)

func TestAttrsConflictsBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()
	server, db, pool := newAttrsValuesTestServer(t)
	_, err := pool.ExecContext(t.Context(), `
 ALTER TABLE pg_temp.attrs_values ADD COLUMN conflict boolean DEFAULT false;
 ALTER TABLE pg_temp.attrs_user_values ADD COLUMN conflict smallint DEFAULT 0;
 ALTER TABLE pg_temp.attrs_user_values ADD COLUMN weight integer DEFAULT 1;
 UPDATE pg_temp.attrs_values SET conflict=true WHERE attribute_id IN (1,2,3,6);
 UPDATE pg_temp.attrs_user_values SET weight=-1,conflict=-1 WHERE user_id=7 AND attribute_id IN (1,2);
 UPDATE pg_temp.attrs_user_values SET conflict=1 WHERE user_id=7 AND attribute_id=3;
 INSERT INTO attrs_values(attribute_id,item_id,update_date,conflict) VALUES(1,20,'2022-01-01',true);
 INSERT INTO attrs_user_values(attribute_id,item_id,user_id,update_date) VALUES
 (1,20,7,'2022-01-01'),(2,20,8,'2021-01-01'),(999,10,8,NULL);
 INSERT INTO attrs_user_values_string VALUES(1,20,'Other item',7);
 `)
	require.NoError(t, err)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 7})

	for _, lang := range []string{"en", "ru"} {
		for _, filter := range []AttrConflictsRequest_Filter{
			AttrConflictsRequest_ALL,
			AttrConflictsRequest_MINUS_WEIGHT,
			AttrConflictsRequest_I_DISAGREE,
			AttrConflictsRequest_DO_NOT_AGREE_WITH_ME,
		} {
			data, pages, err := server.repository.ValuesPaginated(ctx, query.AttrsValueListOptions{
				Conflict: filter == AttrConflictsRequest_ALL,
				UserValues: &query.AttrsUserValueListOptions{
					UserID:         7,
					WeightLtZero:   filter == AttrConflictsRequest_MINUS_WEIGHT,
					ConflictLtZero: filter == AttrConflictsRequest_I_DISAGREE,
					ConflictGtZero: filter == AttrConflictsRequest_DO_NOT_AGREE_WITH_ME,
				},
			}, attrs.ValuesOrderByUpdateDate, 1, 30)
			require.NoError(t, err)

			expected := &AttrConflictsResponse{Items: make([]*AttrConflict, 0, len(data)), Paginator: &Pages{
				PageCount: pages.PageCount, Current: pages.Current, TotalItemCount: pages.TotalItemCount,
			}}
			types := make(map[int]bool)

			for _, row := range data {
				users, err := server.repository.UserValueRows(
					ctx,
					query.AttrsUserValueListOptions{AttributeID: row.AttributeID, ItemID: row.ItemID},
				)
				require.NoError(t, err)

				values := make([]*AttrConflictValue, 0, len(users))
				for _, user := range users {
					value, text, err := server.repository.UserValueText(
						ctx,
						user.AttributeID,
						user.ItemID,
						user.UserID,
						lang,
					)
					require.NoError(t, err)

					values = append(
						values,
						&AttrConflictValue{Value: text, ValueIsEmpty: value.IsEmpty, UserId: user.UserID},
					)

					kind := int(value.Type)
					switch value.Type { //nolint:exhaustive // Other types already identify their table.
					case schema.AttrsAttributeTypeIDText:
						kind = int(schema.AttrsAttributeTypeIDString)
					case schema.AttrsAttributeTypeIDBoolean:
						kind = int(schema.AttrsAttributeTypeIDInteger)
					case schema.AttrsAttributeTypeIDTree:
						kind = int(schema.AttrsAttributeTypeIDList)
					}

					types[kind] = true
				}

				expected.Items = append(
					expected.Items,
					&AttrConflict{ItemId: row.ItemID, AttributeId: row.AttributeID, Values: values},
				)
			}

			logger := &itemBatchQueryLogger{}
			db.Logger(logger)

			actual, err := server.GetConflicts(ctx, &AttrConflictsRequest{Filter: filter, Page: 1, Language: lang})
			require.NoError(t, err)
			db.Logger(nil)
			require.True(t, proto.Equal(expected, actual))
			require.EqualValues(t, 3+len(types), logger.queries.Load(), "count, page, user rows and one batch per type")
		}
	}
	// Exact pairs must not admit unrelated combinations of item and attribute IDs.
	pairs := []schema.AttrsValueRow{{AttributeID: 1, ItemID: 10}, {AttributeID: 2, ItemID: 20}}
	rows, err := server.repository.UserValueRowsForValues(ctx, pairs)
	require.NoError(t, err)

	for _, row := range rows {
		require.True(t, (row.AttributeID == 1 && row.ItemID == 10) || (row.AttributeID == 2 && row.ItemID == 20))
	}

	zero, err := server.repository.UserValueRowsForValues(ctx, []schema.AttrsValueRow{{}})
	require.NoError(t, err)
	all, err := server.repository.UserValueRows(ctx, query.AttrsUserValueListOptions{})
	require.NoError(t, err)
	require.Equal(t, all, zero)
	emptyCtx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 123})
	logger := &itemBatchQueryLogger{}
	db.Logger(logger)

	empty, err := server.GetConflicts(emptyCtx, &AttrConflictsRequest{Filter: AttrConflictsRequest_ALL})
	require.NoError(t, err)
	db.Logger(nil)
	require.NotNil(t, empty.GetItems())
	require.Empty(t, empty.GetItems())
	require.EqualValues(t, 2, logger.queries.Load())
	_, err = pool.ExecContext(t.Context(), "ALTER TABLE pg_temp.attrs_user_values_string RENAME COLUMN value TO broken")
	require.NoError(t, err)
	actual, err := server.GetConflicts(ctx, &AttrConflictsRequest{Filter: AttrConflictsRequest_ALL})
	require.Equal(t, codes.Internal, status.Code(err))
	require.Nil(t, actual)
}
