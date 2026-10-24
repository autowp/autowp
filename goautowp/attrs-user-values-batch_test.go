package goautowp

import (
	"context"
	"testing"

	"github.com/autowp/goautowp/attrs"
	"github.com/autowp/goautowp/query"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
	"google.golang.org/protobuf/types/known/timestamppb"
)

func TestAttrsUserValuesBatchMatchesIndividualReads(t *testing.T) {
	t.Parallel()
	server, db, pool := newAttrsValuesTestServer(t)
	_, err := pool.ExecContext(t.Context(), `
 INSERT INTO attrs_attributes (id, name, type_id, multiple, position) VALUES
 (9, 'Missing scalar', 2, false, 9), (10, 'Missing list', 6, true, 10),
 (11, 'Empty list', 6, true, 11), (12, 'List with NULL', 6, true, 12),
 (13, 'Empty bool', 5, false, 13), (14, 'Empty float', 3, false, 14),
 (15, 'No type', NULL, false, 15), (16, 'Unknown', 99, false, 16);
 INSERT INTO attrs_user_values (attribute_id, item_id, user_id, update_date)
 SELECT id, 10, 7, '2020-01-01'::timestamptz + id * interval '1 day' FROM generate_series(9,16) id;
 INSERT INTO attrs_user_values_list (attribute_id, item_id, value, ordering, user_id) VALUES
 (11,10,NULL,1,7), (12,10,11,3,7), (12,10,NULL,2,7), (12,10,12,1,7),
 (6,10,11,1,8);
 INSERT INTO attrs_user_values_int VALUES (13,10,NULL,7);
 INSERT INTO attrs_user_values_float VALUES (14,10,NULL,7);
 INSERT INTO attrs_user_values (attribute_id,item_id,user_id,update_date) VALUES (6,10,8,NULL);
 `)
	require.NoError(t, err)
	ctx := context.WithValue(t.Context(), authenticatedUserKey{}, UserContext{UserID: 7})

	for _, lang := range []string{"en", "ru"} {
		for _, withText := range []bool{false, true} {
			for _, zone := range []int64{0, 1} {
				rows, err := server.repository.UserValueRows(
					ctx,
					query.AttrsUserValueListOptions{ItemID: 10, ZoneID: zone},
				)
				require.NoError(t, err)

				expected := &AttrUserValuesResponse{Items: make([]*AttrUserValue, 0, len(rows))}
				for _, row := range rows {
					value, err := server.repository.UserValue(ctx, row.AttributeID, row.ItemID, row.UserID)
					require.NoError(t, err)

					text := ""
					if withText {
						value, text, err = server.repository.UserValueText(
							ctx,
							row.AttributeID,
							row.ItemID,
							row.UserID,
							lang,
						)
						require.NoError(t, err)
					}

					extracted := extractAttrValue(value)

					item := &AttrUserValue{
						AttributeId: row.AttributeID,
						ItemId:      row.ItemID,
						UserId:      row.UserID,
						Value:       &extracted,
						ValueText:   text,
					}
					if row.UpdateDate.Valid {
						item.UpdateTime = timestamppb.New(row.UpdateDate.Time)
					}

					expected.Items = append(expected.Items, item)
				}

				logger := &itemBatchQueryLogger{}
				db.Logger(logger)

				actual, err := server.GetUserValues(ctx, &AttrUserValuesRequest{
					ItemId: 10, ZoneId: zone, Language: lang,
					Fields: &AttrUserValuesFields{ValueText: withText},
				})
				require.NoError(t, err)
				db.Logger(nil)

				want := 5
				if zone == 1 {
					want = 4
				}

				require.EqualValues(t, want, logger.queries.Load())
				require.True(t, proto.Equal(expected, actual))
			}
		}
	}
	// Raw values do not load formatting dictionaries, even with a cold cache.
	server.repository = attrs.NewRepository(db, nil, nil, nil, nil, nil, nil)
	_, err = pool.ExecContext(ctx, "DROP TABLE pg_temp.attrs_list_options")
	require.NoError(t, err)
	_, err = server.GetUserValues(ctx, &AttrUserValuesRequest{ItemId: 10})
	require.NoError(t, err)
	_, err = pool.ExecContext(ctx, "UPDATE pg_temp.attrs_user_values_int SET value=2 WHERE attribute_id=5")
	require.NoError(t, err)
	actual, err := server.GetUserValues(ctx, &AttrUserValuesRequest{ItemId: 10})
	require.Equal(t, codes.Internal, status.Code(err))
	require.Nil(t, actual)
}
