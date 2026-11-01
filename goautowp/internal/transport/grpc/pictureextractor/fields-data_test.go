package pictureextractor_test

import (
	"database/sql"
	"testing"

	"github.com/autowp/goautowp/internal/schema"
	"github.com/autowp/goautowp/internal/testutil"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
)

func newPictureFieldRows(t *testing.T) (*goqu.Database, *sql.DB, []*schema.PictureRow) {
	t.Helper()

	db, pool, _ := testutil.NewPostgres(t, "../../../..")
	_, err := pool.ExecContext(t.Context(), `
 CREATE TEMP TABLE picture_moder_vote (picture_id bigint,user_id bigint,vote integer);
 INSERT INTO picture_moder_vote VALUES
 (2,7,0),(3,7,1),(4,7,0),(4,8,2),(5,7,-1),(6,7,NULL),(7,7,0),(7,8,1),(99,7,0);
 CREATE TEMP TABLE comment_topic_subscribe (type_id integer,item_id bigint,user_id bigint,sent boolean);
 INSERT INTO comment_topic_subscribe VALUES (1,1,7,true),(1,2,7,false),(2,3,7,true),(1,4,8,true);
 CREATE TEMP TABLE textstorage_text (id integer,text text);
 INSERT INTO textstorage_text VALUES (0,'Zero ID'),(1,'Shared'),(2,''),(3,'<b>Copyright</b>'),(4,NULL);
 `)
	require.NoError(t, err)

	rows := make([]*schema.PictureRow, 0, 10)
	for index, status := range []schema.PictureStatus{
		schema.PictureStatusInbox, schema.PictureStatusInbox, schema.PictureStatusInbox,
		schema.PictureStatusInbox, schema.PictureStatusInbox, schema.PictureStatusInbox,
		schema.PictureStatusAccepted, schema.PictureStatusRemoving, schema.PictureStatusRemoved, schema.PictureStatusUnknown,
	} {
		rows = append(rows, &schema.PictureRow{ID: int64(index + 1), Status: status})
	}

	for index, id := range []int32{1, 1, 2, 3, 999, 0} {
		rows[index].CopyrightsTextID = sql.NullInt32{Int32: id, Valid: true}
	}

	rows = append(rows, rows[1])

	db.Logger(nil)

	return db, pool, rows
}
