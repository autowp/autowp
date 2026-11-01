package goautowp

import (
	"testing"

	"github.com/autowp/goautowp/api/pb"
	authn "github.com/autowp/goautowp/internal/auth"
	"github.com/autowp/goautowp/internal/config"
	"github.com/autowp/goautowp/internal/schema"
	"github.com/doug-martin/goqu/v9"
	"github.com/stretchr/testify/require"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/metadata"
	"google.golang.org/grpc/status"
)

func TestGetText(t *testing.T) {
	t.Parallel()

	cfg := config.LoadConfig(".")

	ctx := t.Context()

	client := pb.NewTextClient(conn)
	kc := cnt.Keycloak()
	usersClient := pb.NewUsersClient(conn)

	// tester
	testerToken, err := kc.Login(
		ctx,
		keycloakClientID,
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
			authn.AuthorizationHeader,
			bearerPrefix+testerToken.AccessToken,
		),
		&pb.MeRequest{},
	)
	require.NoError(t, err)

	goquDB, err := cnt.GoquDB(t.Context())
	require.NoError(t, err)

	var id int64

	success, err := goquDB.Insert(schema.TextstorageTextTable).Rows(goqu.Record{
		schema.TextstorageTextTableTextColName:        "Text 2",
		schema.TextstorageTextTableLastUpdatedColName: goqu.Func("NOW"),
		schema.TextstorageTextTableRevisionColName:    2,
	}).Returning(schema.TextstorageTextTableIDCol).Executor().ScanValContext(ctx, &id)
	require.NoError(t, err)
	require.True(t, success)

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

	text, err := client.GetText(ctx, &pb.GetTextRequest{Id: id})
	require.NoError(t, err)
	require.Equal(t, "Text 2", text.GetCurrent().GetText())
	require.Equal(t, tester.GetId(), text.GetCurrent().GetUserId())
	require.Equal(t, int64(2), text.GetCurrent().GetRevision())
	require.Equal(t, "Text 1", text.GetPrev().GetText())
	require.Equal(t, tester.GetId(), text.GetPrev().GetUserId())
	require.Equal(t, int64(1), text.GetPrev().GetRevision())
	require.Equal(t, int64(0), text.GetNext().GetRevision())

	_, err = client.GetText(ctx, &pb.GetTextRequest{Id: id, Revision: 1})
	require.Equal(t, codes.PermissionDenied, status.Code(err))

	adminToken, err := kc.Login(ctx, keycloakClientID, "", cfg.Keycloak.Realm, adminUsername, adminPassword)
	require.NoError(t, err)

	moderCtx := metadata.AppendToOutgoingContext(ctx, authn.AuthorizationHeader, bearerPrefix+adminToken.AccessToken)
	text, err = client.GetText(moderCtx, &pb.GetTextRequest{Id: id, Revision: 1})
	require.NoError(t, err)
	require.Equal(t, "Text 1", text.GetCurrent().GetText())
	require.EqualValues(t, 2, text.GetNext().GetRevision())
	require.Zero(t, text.GetPrev().GetRevision())

	_, err = client.GetText(moderCtx, &pb.GetTextRequest{Id: id, Revision: 99})
	require.Equal(t, codes.NotFound, status.Code(err))
	_, err = client.GetText(ctx, &pb.GetTextRequest{Id: -1})
	require.Equal(t, codes.NotFound, status.Code(err))
}
