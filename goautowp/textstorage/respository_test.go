package textstorage

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func TestGetText(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig("../")
	db, err := sql.Open("postgres", cfg.PostgresDSN)
	require.NoError(t, err)

	goquDB := goqu.New("postgres", db)
	ctx := t.Context()

	var id int32

	success, err := goquDB.Insert(schema.TextstorageTextTable).Rows(goqu.Record{
		schema.TextstorageTextTableTextColName:        "test",
		schema.TextstorageTextTableLastUpdatedColName: goqu.Func("NOW"),
		schema.TextstorageTextTableRevisionColName:    1,
	}).Returning(schema.TextstorageTextTableIDCol).Executor().ScanValContext(ctx, &id)
	require.NoError(t, err)
	require.True(t, success)

	repository := New(goquDB)

	_, err = repository.Text(ctx, id)
	require.NoError(t, err)

	_, err = repository.FirstText(ctx, []int32{id})
	require.NoError(t, err)
}
