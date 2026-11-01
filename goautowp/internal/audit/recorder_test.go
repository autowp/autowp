package audit_test

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/audit"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func TestRecorderAdd(t *testing.T) {
	t.Parallel()

	pool, err := sql.Open("postgres", config.LoadConfig("../..").PostgresDSN)
	require.NoError(t, err)
	pool.SetMaxOpenConns(1)
	t.Cleanup(func() { require.NoError(t, pool.Close()) })
	_, err = pool.ExecContext(t.Context(), `
		CREATE TEMP TABLE log_event (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
			user_id bigint, description text, created_at timestamptz);
		CREATE TEMP TABLE log_event_user (log_event_id bigint, user_id bigint);
		CREATE TEMP TABLE log_event_picture (log_event_id bigint, picture_id bigint);
		CREATE TEMP TABLE log_event_item (log_event_id bigint, item_id bigint);
	`)
	require.NoError(t, err)

	db := goqu.New("postgres", pool)
	recorder := audit.New(db)
	err = recorder.Add(t.Context(), audit.Event{
		UserID: 7, Message: "Updated picture", Users: []int64{3, 3, 4},
		Pictures: []int64{11, 12, 11}, Items: []int64{21, 21, 22},
	})
	require.NoError(t, err)

	var row struct {
		ID      int64  `db:"id"`
		UserID  int64  `db:"user_id"`
		Message string `db:"description"`
		Dated   bool   `db:"dated"`
	}

	found, err := db.From(schema.LogEventTable).
		Select(schema.LogEventTableIDCol, schema.LogEventTableUserIDCol, schema.LogEventTableDescriptionCol,
			schema.LogEventTableCreatedAtCol.IsNotNull().As("dated")).
		ScanStructContext(t.Context(), &row)
	require.NoError(t, err)
	require.True(t, found)
	require.EqualValues(t, 7, row.UserID)
	require.Equal(t, "Updated picture", row.Message)
	require.True(t, row.Dated)

	for _, relation := range []struct {
		table    string
		column   string
		expected []int64
	}{
		{schema.LogEventUserTableName, schema.LogEventUserTableUserIDColName, []int64{3, 4}},
		{schema.LogEventPictureTableName, schema.LogEventPictureTablePictureIDColName, []int64{11, 12}},
		{schema.LogEventItemTableName, schema.LogEventItemTableItemIDColName, []int64{21, 22}},
	} {
		ids := make([]int64, 0)
		err = db.From(relation.table).Select(relation.column).
			Where(goqu.C("log_event_id").Eq(row.ID)).ScanValsContext(t.Context(), &ids)
		require.NoError(t, err)
		require.ElementsMatch(t, relation.expected, ids, "duplicate relations must be removed")
	}

	err = recorder.Add(t.Context(), audit.Event{UserID: 7, Message: "Plain event"})
	require.NoError(t, err)
	count, err := db.From(schema.LogEventTable).CountContext(t.Context())
	require.NoError(t, err)
	require.EqualValues(t, 2, count)

	_, err = pool.ExecContext(t.Context(), "ALTER TABLE log_event RENAME COLUMN description TO broken_description")
	require.NoError(t, err)
	require.Error(t, recorder.Add(t.Context(), audit.Event{UserID: 7, Message: "Failed event"}))
}
