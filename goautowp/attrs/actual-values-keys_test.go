package attrs

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func TestActualValueBatchReadsExactRequestedTuples(t *testing.T) {
	t.Parallel()

	pool, err := sql.Open("postgres", config.LoadConfig("..").PostgresDSN)
	require.NoError(t, err)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	pool.SetMaxOpenConns(1)
	_, err = pool.ExecContext(t.Context(), `CREATE TEMP TABLE attrs_user_values_string
 (item_id bigint,attribute_id bigint,user_id bigint,value text);
 INSERT INTO attrs_user_values_string SELECT i,a,u,'v' FROM generate_series(1,20) i
 CROSS JOIN generate_series(1,20) a CROSS JOIN generate_series(1,20) u;`)
	require.NoError(t, err)

	var requests []actualValueRequest
	for i := int64(1); i <= 20; i++ {
		requests = append(requests, actualValueRequest{itemID: i, attributeID: i, userID: i})
	}

	requests = append(requests, requests[0])
	rows, err := scanActualValueRows[typedValueRow[sql.NullString]](
		t.Context(),
		goqu.New("postgres", pool),
		schema.AttrsUserValuesStringTableName,
		requests,
		false,
	)
	require.NoError(t, err)
	require.Len(t, rows, 20, "query should read only requested triples")
	_, err = pool.ExecContext(t.Context(), `CREATE TEMP TABLE attrs_values_list
 (item_id bigint,attribute_id bigint,value bigint,ordering integer);
 INSERT INTO attrs_values_list SELECT i,a,a,1 FROM generate_series(1,20) i CROSS JOIN generate_series(1,20) a;
 INSERT INTO attrs_values_list VALUES (1,1,NULL,2);`)
	require.NoError(t, err)
	lists, err := scanActualValueRows[typedValueRow[sql.NullInt64]](
		t.Context(),
		goqu.New("postgres", pool),
		schema.AttrsValuesListTableName,
		requests,
		true,
	)
	require.NoError(t, err)
	require.Len(t, lists, 21, "duplicate requests must not multiply list entries; NULLs must remain")
	empty, err := scanActualValueRows[typedValueRow[sql.NullInt64]](
		t.Context(),
		nil,
		schema.AttrsValuesListTableName,
		nil,
		true,
	)
	require.NoError(t, err)
	require.Empty(t, empty)
}
