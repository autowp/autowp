package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/config"
	"github.com/autowp/goautowp/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/metadata"
)

func TestGetText(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	ctx := t.Context()

	client := NewTextClient(conn)
	kc := cnt.Keycloak()
	usersClient := NewUsersClient(conn)

	// tester
	testerToken, err := kc.Login(
		ctx,
		"frontend",
		"",
		cfg.Keycloak.Realm,
		testUsername,
		testPassword,
	)
	require.NoError(t, err)
	require.NotNil(t, testerToken)

	// tester (me)
	tester, err := usersClient.Me(
		metadata.AppendToOutgoingContext(
			ctx,
			authorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&APIMeRequest{},
	)
	require.NoError(t, err)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	res, err := goquDB.Insert(schema.TextstorageTextTable).Rows(goqu.Record{
		schema.TextstorageTextTableTextColName:        "Text 2",
		schema.TextstorageTextTableLastUpdatedColName: goqu.Func("NOW"),
		schema.TextstorageTextTableRevisionColName:    2,
	}).Executor().ExecContext(ctx)
	require.NoError(t, err)

	id, err := res.LastInsertId()
	require.NoError(t, err)

	_, err = goquDB.Insert(schema.TextstorageRevisionTable).Rows(goqu.Record{
		schema.TextstorageRevisionTableTextIDColName:    id,
		schema.TextstorageRevisionTableRevisionColName:  1,
		schema.TextstorageRevisionTableTextColName:      "Text 1",
		schema.TextstorageRevisionTableTimestampColName: goqu.Func("NOW"),
		schema.TextstorageRevisionTableUserIDColName:    tester.GetId(),
	}).Executor().ExecContext(ctx)
	require.NoError(t, err)

	_, err = goquDB.Insert(schema.TextstorageRevisionTable).Rows(goqu.Record{
		schema.TextstorageRevisionTableTextIDColName:    id,
		schema.TextstorageRevisionTableRevisionColName:  2,
		schema.TextstorageRevisionTableTextColName:      "Text 2",
		schema.TextstorageRevisionTableTimestampColName: goqu.Func("NOW"),
		schema.TextstorageRevisionTableUserIDColName:    tester.GetId(),
	}).Executor().ExecContext(ctx)
	require.NoError(t, err)

	text, err := client.GetText(ctx, &APIGetTextRequest{Id: id})
	require.NoError(t, err)
	require.Equal(t, "Text 2", text.GetCurrent().GetText())
	require.Equal(t, tester.GetId(), text.GetCurrent().GetUserId())
	require.Equal(t, int64(2), text.GetCurrent().GetRevision())
	require.Equal(t, "Text 1", text.GetPrev().GetText())
	require.Equal(t, tester.GetId(), text.GetPrev().GetUserId())
	require.Equal(t, int64(1), text.GetPrev().GetRevision())
	require.Equal(t, int64(0), text.GetNext().GetRevision())
}
